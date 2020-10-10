<?php
declare(strict_types=1);

namespace Wumvi\Utils\Shorthand;

use Wumvi\Utils\Response;
use Wumvi\Utils\Request;
use Wumvi\Utils\Sign;
use Wumvi\DI\DI;
use Wumvi\Errors\Errors;
use Symfony\Component\DependencyInjection\Container;

class Shorthand
{
    private DI $diBuilder;
    private bool $isDev;

    public function __construct()
    {
        Errors::attachExceptionHandler();
        $this->diBuilder = new DI();
        $this->isDev = $_SERVER['APP_ENV'] === 'dev';
    }

    public function isDev(): bool
    {
        return $this->isDev;
    }

    public function getDI(string $root = '', bool $resolveEnvPlaceholders = true): Container
    {
        $root = $root ?: $_SERVER['DOCUMENT_ROOT'];
        return $this->diBuilder->getDi(
            $root . '/conf/services.yml',
            $root . '/.env',
            $resolveEnvPlaceholders,
            $this->isDev
        );
    }

    public function getPostJsonData(): \stdClass
    {
        $jsonRaw = Request::getPostRaw();

        return json_decode($jsonRaw, false, 4, JSON_THROW_ON_ERROR);
    }

    public function decodeSafeData(
        string $signDataRaw,
        Salt $saltStorage,
        $class,
        array $saltNameAllow = []
    )
    {
        $signData = Sign::decodeSignData($signDataRaw);
        Errors::conditionExit($signData === null, 'wrong-session', 'wrong-data');
        $saltValue = $saltStorage->getSaltByName($signData->getSaltName());
        // Если данные от другого сервиса и это прямое сравнение ключей без хеширования
        if ($signData->getSaltName() === Salt::SERVICE && $signData->getAlgo() === Sign::DIRECT) {
            Errors::conditionExit($saltValue !== $signData->getKey(), 'wrong-service-key');

            return new $class(json_decode($signData->getData(), false, 2, JSON_THROW_ON_ERROR));
        }

        $isAllow = in_array($signData->getSaltName(), $saltNameAllow) || in_array('all', $saltNameAllow);
        $hint = 'check-salt-name-allow-variable: ' . implode(',', $saltNameAllow);
        Errors::conditionExit(!$isAllow, 'access-denied', $hint);

        $isRight = Sign::checkSignData($signData, $saltValue);
        Errors::conditionExit(!$isRight, 'wrong-session', 'wrong-sign');

        $jsonRaw = $signData->getData();
        if ($jsonRaw[0] !== '[' && $jsonRaw[0] !== '{') {
            $jsonRaw = base64_decode($jsonRaw);
            Errors::conditionExit(empty($jsonRaw), 'wrong-data', 'check-base64');
        }

        $session = new $class(json_decode($jsonRaw, false, 2, JSON_THROW_ON_ERROR));

        $isExpired = $session->getTtl() !== -1 && $session->getTtl() < time();
        Errors::conditionExit($isExpired, 'wrong-session', 'session-expired');

        return $session;
    }

    public function successResponse(array $data)
    {
        header('Access-Control-Allow-Origin: *');
        Response::flush(Response::jsonSuccess($data));
        exit;
    }
}
