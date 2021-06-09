<?php
declare(strict_types=1);

namespace Wumvi\Utils\Shorthand;

class UserRequestBase
{
    public function __construct(
        public \stdClass $raw
    ) {
    }

    public function getSafeDataRaw(): string
    {
        return $this->raw->safe;
    }

    public function getRid(): int
    {
        return $this->raw->rid;
    }
}
