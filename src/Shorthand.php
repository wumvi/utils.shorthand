<?php
declare(strict_types=1);

namespace Wumvi\Utils\Shorthand;

use Wumvi\Sign\Decode;
use Wumvi\Sign\Encode;
use Wumvi\Sign\SaltStorage;
use Wumvi\Utils\Response;
use Wumvi\Utils\Request;
use Wumvi\DI\DI;
use Wumvi\Errors\Errors;
use Symfony\Component\DependencyInjection\Container;

class Shorthand
{
    protected ?DI $diBuilder = null;
    protected bool $isDev;

    public const DEV_MODE = 'dev';

    public function __construct()
    {
        Errors::attachExceptionHandler();
        $this->isDev = $_SERVER['APP_ENV'] === self::DEV_MODE;
    }

    public function isDev(): bool
    {
        return $this->isDev;
    }

    public function getDI(
        string $root = '',
        bool $resolveEnvPlaceholders = true,
        string $configFile = '/conf/services.yml',
        string $envFile = '/.env'
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

    public function getPostJson(int $jsonDepth = 6): \stdClass
    {
        $jsonRaw = Request::getPostRaw();

        return json_decode($jsonRaw, false, $jsonDepth, JSON_THROW_ON_ERROR);
    }

    public function decodeSignedData(
        string $signDataRaw,
        SaltStorage $saltStorage,
        $class,
        array $saltNameAllow = [],
        int $jsonDepth = 6
    ) {
        $signData = Decode::decodeSignWithData($signDataRaw);
        Errors::conditionExit($signData === null, 'wrong-session', 'wrong-data');
        $saltValue = $saltStorage->getSaltByName($signData->getSaltName());
        // Если данные от другого сервиса и это прямое сравнение ключей без хеширования
        if ($signData->getSaltName() === SaltStorage::SERVICE && $signData->getAlgo() === Encode::DIRECT) {
            Errors::conditionExit($saltValue !== $signData->getHash(), 'wrong-service-key');

            return new $class(json_decode($signData->getData(), false, 2, JSON_THROW_ON_ERROR));
        }

        $isAllow = in_array($signData->getSaltName(), $saltNameAllow) || in_array('all', $saltNameAllow);
        $hint = 'check-salt-name-allow-variable: ' . implode(',', $saltNameAllow);
        Errors::conditionExit(!$isAllow, 'access-denied', $hint);

        $serverHash = Encode::createRawSign($signData->getData(), $saltValue, $signData->getAlgo());
        Errors::conditionExit($serverHash !== $signData->getHash(), 'wrong-session', 'wrong-sign');

        $jsonRaw = $signData->getData();
        if ($jsonRaw[0] !== '[' && $jsonRaw[0] !== '{') {
            $jsonRaw = base64_decode($jsonRaw);
            Errors::conditionExit(empty($jsonRaw), 'wrong-data', 'check-base64');
        }

        $json = json_decode($jsonRaw, false, $jsonDepth, JSON_THROW_ON_ERROR);
        $session = new $class(empty($json) ? new \stdClass() : $json);

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


//    public function serviceRequest(string $url, Salt $salt, array $safeData, array $userData, $algo = Salt::SERVICE): ?string
//    {
//        $signData = base64_encode(json_encode($safeData));
//        $safe = Sign::getSignWithData($signData, $salt->getSaltByName($algo), $algo);
//        $postData = json_encode(['safe' => $safe,] + $userData);
//
//        try {
//            $curl = new Curl();
//            $curl->setTimeout(2);
//            $postPipe = new PostMethodPipe();
//            $postPipe->setData($postData);
//            $curl->applyPipe($postPipe);
//            $curl->setUrl($url);
//            $response = $curl->exec();
//            $code = $response->getHttpCode();
//            if ($code < 200 || 299 < $code) {
//                return null;
//            }
//        } catch (\Exception $ex) {
//            return null;
//        }
//
//        return $response->getData();
//    }
}
