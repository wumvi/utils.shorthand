<?php
declare(strict_types=1);

namespace Wumvi\Utils\Shorthand;

class SessionBase
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
