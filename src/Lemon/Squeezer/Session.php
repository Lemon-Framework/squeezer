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
     * Removes expiration.
     */
    public function dontExpire(): static
    {
        return $this->expireAt(0);
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
     * Removes key.
     */
    public function remove(string $key): static
    {
        $this->session->delete($key);
        return $this;
    }

    /**
     * Clears session.
     */
    public function clear(): void
    {
        $this->session->flush();
    }
}
