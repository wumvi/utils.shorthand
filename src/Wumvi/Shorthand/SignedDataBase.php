<?php
declare(strict_types=1);

namespace Wumvi\Shorthand;

class SignedDataBase
{
    public function __construct(
        public \stdClass $raw
    ) {
    }

    public function getTtl(): int
    {
        return $this->raw->ttl ?? -1;
    }
}
