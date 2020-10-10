<?php
declare(strict_types=1);

namespace Wumvi\Utils\Shorthand;

class SafeDataModel
{
    protected \stdClass $raw;

    public function __construct(\stdClass $raw)
    {
        $this->raw = $raw;
    }

    public function getTtl(): int
    {
        return $this->raw->ttl ?? -1;
    }
}
