<?php

declare(strict_types=1);

namespace Lemon\Lemon\Squeezer;

use Closure;
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
    public function __construct(
        private Application $application
    ) {
    }

    public static function init(Application $application): static
    {
        $squezer = new self($application);
        $application->get('terminal')
                    ->command(
                        'start {host?} {port?}',
                        Closure::fromCallable([$squezer, 'command']),
                        'Executes app using Lemon Squeezer'
                    );
        return $squezer;
    }

    public function boot(string $host, string $port)
    {
        $worker = new Worker("http://{$host}:{$port}");

        $worker->onMessage = [$this, 'handleIncomming'];

        $worker->runAll();
    }

    public function handleIncomming(ConnectionInterface $connection, Request $request) 
    {
        $path = $this->application->directory.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.$request->path();
        if (file_exists($path) && !is_dir($path)) {
            $connection->send(file_get_contents($path));
            return;
        }

        $request = $this->captureRequest($request);
        $this->application->add(LemonRequest::class, $request);
        if (!$this->application->has('request')) {
            $this->application->alias('request', LemonRequest::class);
        }
        try {
            $response = $this->application->get('routing')->dispatch($request);
            $connection->send($this->convertResponse($response));
        } catch (Throwable $e) {
            $connection->send($this->handle($e));
        }
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
