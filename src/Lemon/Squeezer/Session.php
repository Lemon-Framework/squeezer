<?php

namespace Lemon\Squeezer;

use Lemon\Contracts\Http\Session as SessionContract;
use Workerman\Protocols\Http\Session as HttpSession;

class Session implements SessionContract
{
    private HttpSession $session;

    public function __construct($id)
    {
        $this->session = new HttpSession($id);
        HttpSession::$httpOnly = true;
    }

    /**
     * Sets expiration.
     */
    public function expireAt(int $seconds): static
    {
        HttpSession::$lifetime = $seconds;
        return $this;
    }

    /**
     * Returns value of given key.
     */
    public function get(string $key): string
    {
        return $this->session->get($key);
    }

    /**
     * Sets value for given key.
     */
    public function set(string $key, mixed $value): static
    {
        $this->session->set($key, $value);
        return $this;
    }

    /**
     * Determins whenever key exists.
     */
    public function has(string $key): bool
    {
        return $this->session->has($key);
    }

    /**
     * Clears session.
     */
    public function clear(): void
    {
        $this->session->flush();
    }
}
