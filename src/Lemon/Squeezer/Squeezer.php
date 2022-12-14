<?php

declare(strict_types=1);

namespace Lemon\Squeezer;

use Closure;
use Lemon\Contracts\Http\Session as SessionContract;
use Lemon\Debug\Handling\Reporter;
use Lemon\Http\Request as LemonRequest;
use Lemon\Http\Response as LemonResponse;
use Lemon\Http\Responses\TemplateResponse;
use Lemon\Kernel\Application;
use Lemon\Terminal\Terminal;
use Throwable;
use Workerman\Connection\ConnectionInterface;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use Workerman\Worker;

class Squeezer
{
    const CONTENT_TYPES = [
        'css' => 'text/css',
        'js' => 'text/javascript',
        'txt' => 'text/plain',
        'html' => 'text/html',
        'gif' => 'image/gif',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'json' => 'application/json',
        'pdf' => 'application/pdf',
        'ogg' => 'application/ogg',
        'mpeg' => 'video/mpeg',
        'mp4' => 'video/mp4',
        'mov' => 'video/quicktime',
        'webm' => 'video/webm',
    ];

    public function __construct(
        private Application $application
    ) {
    }

    public static function init(Application $application): static
    {
        $squezer = new self($application);
        $application->get('terminal')
                    ->command(
                        'squeeze {host?} {port?}',
                        Closure::fromCallable([$squezer, 'command']),
                        'Executes app using Lemon Squeezer'
                    );
        return $squezer;
    }

    public function boot(string $host, string $port)
    {
        $worker = new Worker("http://{$host}:{$port}");

        $worker->onMessage = [$this, 'handleIncomming'];

        $worker->listen();
        $worker->run();
    }

    public function handleIncomming(ConnectionInterface $connection, Request $worker_request) 
    {
        $path = $this->application->directory.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.$worker_request->path();
        if ($response = $this->handleFiles($path)) {
            $connection->send($response);
            return;
        }
        $request = $this->captureRequest($worker_request);
        $this->application->add(LemonRequest::class, $request);
        $this->application->add(Session::class, new Session($worker_request->sessionId()));

        if (!$this->application->hasAlias('request')) {
            $this->application->alias('request', LemonRequest::class);
            $this->application->alias('session', Session::class);
            $this->application->alias(SessionContract::class, Session::class);
        }

        try {
            $response = $this->application->get('routing')->dispatch($request);
            $connection->send($this->convertResponse($response));
        } catch (Throwable $e) {
            $connection->send($this->handle($e));
        }
    }

    public function handleFiles(string $path): ?Response
    {
        if (file_exists($path) && !is_dir($path)) {
            $extension = explode('.', $path)[1];

            if ($extension === 'php') {
                return null;
            }

            $content_type = isset(self::CONTENT_TYPES[$extension]) 
                ? self::CONTENT_TYPES[$extension] 
                : 'text/plain'
            ;

            return new Response(200, ['Content-Type' => $content_type], file_get_contents($path));
        }
        
        return null;
    }

    public function captureRequest(Request $request): LemonRequest
    {
        return new LemonRequest(
            $request->path(),
            $request->queryString(),
            $request->method(),
            $request->header(),
            $request->rawBody(),
            $request->cookie()
        );
    }

    public function convertResponse(LemonResponse $response): Response
    {
        $body = $response->parseBody();
        $result = new Response($response->status_code, $response->headers(), $body);
        foreach ($response->cookies() as [$cookie, $value, $expires]) {
            $result->cookie($cookie, $value, $expires - time());
        } 

        return $result;
    }

    public function handle(Throwable $problem): Response
    {
        if ($this->application->get('config')->get('debug.debug')) {
            $reporter = new Reporter($problem, $this->application->get('request'));
            $response = new TemplateResponse($reporter->getTemplate(), 500);
        } else {
            $response = $this->application->get('response')->error(500);
        }

        return $this->convertResponse($response);
    }

    public function command(Terminal $terminal, $host = 'localhost', $port = '8000')
    {
        $terminal->out('<div class="text-yellow">Application started on '.$host.':'.$port);
        $this->boot($host, $port);
    }
}
