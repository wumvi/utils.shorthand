<?php

namespace Wumvi\Shorthand;

class Session
{
    public function __construct(private readonly array $raw = [])
    {
    }

    public function getUserId(): int
    {
        return (int)$this->raw['uid'];
    }

    public function getClientId(): int
    {
        return (int)$this->raw['cid'];
    }
}
