<?php
declare(strict_types=1);

namespace Wumvi\Shorthand;

use Wumvi\DI\DI;
use Wumvi\Errors\Errors;
use Symfony\Component\DependencyInjection\Container;

class Shorthand
{
    protected ?DI $diBuilder = null;
    protected bool $isDev;

    public const DEV_MODE = 'dev';
    public const SID_COOKIE_NAME = 'xsid';
    public const SID_HEADER_NAME = 'XSID';

    public function __construct(array $customDataForErrorLog = [], bool $isDev = false)
    {
        Errors::attachExceptionHandler($customDataForErrorLog);
        $this->isDev = $isDev;
    }

    /**
     * @return bool
     */
    public function isDev(): bool
    {
        return $this->isDev;
    }

    /**
     * @param string $root
     * @param bool $resolveEnvPlaceholders
     * @param string $configFile
     * @param string $envFile
     *
     * @return Container
     */
    public function getDI(
        string $root = '',
        bool $resolveEnvPlaceholders = true,
        string $configFile = '/app/src/config/services.yml',
        string $envFile = '/app/src/config/.env'
    ): Container {
        if ($this->diBuilder === null) {
            $this->diBuilder = new DI();
        }

        $root = $root ?: $_SERVER['DOCUMENT_ROOT'];
        return $this->diBuilder->getDi(
            $root . $configFile,
            $root . $envFile,
            $resolveEnvPlaceholders,
            $this->isDev
        );
    }
}
