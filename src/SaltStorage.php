<?php
declare(strict_types=1);

namespace Wumvi\Utils\Shorthand;

class SaltStorage
{
    public const PUBLIC = 'pb';
    public const SERVICE = 'sr';
    public const CLIENT = 'cl';
    public const SUPPORT = 'sp';
    public const ALL = 'all';

    public function __construct(
        public array $salts
    ) {
    }

    public function getSaltByName(string $name): string
    {
        return $this->salts[$name] ?? '';
    }

    public function getClient(): string
    {
        return $this->salts[self::CLIENT];
    }
}
