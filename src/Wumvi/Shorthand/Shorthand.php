<?php
declare(strict_types=1);

namespace Wumvi\Shorthand;

use JetBrains\PhpStorm\NoReturn;
use Wumvi\Errors\ErrorResponse;
use Wumvi\Sign\Decode;
use Wumvi\Sign\Encode;
use Wumvi\Sign\Check;
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
    public const SIGN_QUERY_PARAM_NAME = 's';
    public const SIGN_COOKIE_NAME = 'x-session';
    public const SIGN_HEADER_NAME = 'X_SDATA';
    public const JSON_MAX_DEPTH = 6;

    public function __construct(array $customDataForErrorLog = [])
    {
        Errors::attachExceptionHandler($customDataForErrorLog);
        $this->isDev = ($_SERVER['APP_ENV'] ?? self::DEV_MODE) === self::DEV_MODE;
    }

    /**
     * Check valid sign in the url or not
     *
     * @param SaltStorage $saltStorage Salt storage
     * @param string $requestUri Path and query
     * @param string[] $exceptParams Except params
     * @param string $signKey Name of param for sign
     *
     * @return bool Valid sign or not
     */
    public function checkSignedUrl(
        SaltStorage $saltStorage,
        string $requestUri = '',
        array $exceptParams = [],
        string $signKey = self::SIGN_QUERY_PARAM_NAME,
    ): bool {
        $urlInfo = parse_url($requestUri ?: $_SERVER['REQUEST_URI']);
        $query = $urlInfo['query'] ?? '';
        if (empty($query)) {
            return false;
        }

        parse_str($query, $urlQuery);
        if (isset($urlQuery['sksg']) && $this->isDev) {
            return true;
        }

        if (!array_key_exists($signKey, $urlQuery)) {
            return false;
        }

        foreach ($exceptParams as $exceptParamName) {
            unset($urlQuery[$exceptParamName]);
        }

        $sign = $urlQuery[$signKey];
        unset($urlQuery[$signKey]);
        $url = $urlInfo['path'] . '?' . http_build_query($urlQuery);

        return Check::checkSign($sign, $url, $saltStorage);
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

    /**
     * @param string $name
     * @param int $jsonDepth
     *
     * @return \stdClass
     *
     * @throws
     */
    public function getPostJson(string $name = '', int $jsonDepth = self::JSON_MAX_DEPTH): \stdClass
    {
        $data = $name ? Request::post($name) : Request::getPostRaw();

        return json_decode($data, false, $jsonDepth, JSON_THROW_ON_ERROR);
    }

    /**
     * @param string|array $data
     * @param string $saltName
     * @param SaltStorage $saltStorage
     * @param string $algo
     * @param bool $isBase64
     *
     * @return string
     */
    public function createSignedData(
        string|array $data,
        string $saltName,
        SaltStorage $saltStorage,
        string $algo = Encode::MD5,
        bool $isBase64 = true
    ): string {
        $saltValue = $saltStorage->getSaltByName($saltName);
        $text = is_string($data) ? $data : json_encode($data);
        $text = $isBase64 ? base64_encode($text) : $text;

        return Encode::createSignWithData($text, $saltName, $saltValue, $algo);
    }

    /**
     * @param string $url
     * @param string $saltName
     * @param SaltStorage $saltStorage
     * @param string $algo
     * @param string $signKey
     * @return string
     */
    public function createSignedUrl(
        string $url,
        string $saltName,
        SaltStorage $saltStorage,
        string $algo = Encode::MD5,
        string $signKey = self::SIGN_QUERY_PARAM_NAME,
    ): string {
        $saltValue = $saltStorage->getSaltByName($saltName);
        $sign = Encode::createSign($url, $saltName, $saltValue, $algo);

        return $url . '&' . $signKey . '=' . $sign;
    }

    /**
     * @param SaltStorage $saltStorage
     * @param $class
     * @param bool $isBase64
     * @param string $name
     * @param array $saltNameAllow
     * @param int $jsonDepth
     *
     * @return \stdClass|ErrorResponse|string
     *
     * @throws
     */
    public function decodeSignedPostData(
        SaltStorage $saltStorage,
        $class,
        bool $isBase64 = true,
        string $name = '',
        array $saltNameAllow = [],
        int $jsonDepth = self::JSON_MAX_DEPTH
    ): \stdClass|ErrorResponse|string {
        $data = empty($name) ? Request::getPostRaw() : Request::post($name);

        return $this->decodeSignedData($data, $saltStorage, $class, $isBase64, $saltNameAllow, $jsonDepth);
    }

    /**
     * @param SaltStorage $saltStorage
     * @param $class
     * @param bool $isBase64
     * @param string $name
     * @param array $saltNameAllow
     * @param int $jsonDepth
     *
     * @return \stdClass|ErrorResponse|string
     *
     * @throws
     */
    public function decodeSignedHeader(
        SaltStorage $saltStorage,
        $class,
        bool $isBase64 = true,
        string $name = self::SIGN_HEADER_NAME,
        array $saltNameAllow = [],
        int $jsonDepth = self::JSON_MAX_DEPTH
    ): \stdClass|ErrorResponse|string {
        $signedData = $_SERVER['HTTP_' . $name] ?? '';

        return $this->decodeSignedData($signedData, $saltStorage, $class, $isBase64, $saltNameAllow, $jsonDepth);
    }

    /**
     * @param SaltStorage $saltStorage
     * @param $class
     * @param bool $isBase64
     * @param string $name
     * @param array $saltNameAllow
     * @param int $jsonDepth
     *
     * @return \stdClass|ErrorResponse|string
     *
     * @throws
     */
    public function decodeSignedCookie(
        SaltStorage $saltStorage,
        $class,
        bool $isBase64 = true,
        string $name = self::SIGN_COOKIE_NAME,
        array $saltNameAllow = [],
        int $jsonDepth = self::JSON_MAX_DEPTH
    ): \stdClass|ErrorResponse|string {
        $signedData = $_COOKIE[$name] ?? '';
        return $this->decodeSignedData($signedData, $saltStorage, $class, $isBase64, $saltNameAllow, $jsonDepth);
    }

    /**
     * @param string $signDataRaw
     * @param SaltStorage $saltStorage
     * @param null $class
     * @param bool $isBase64
     * @param array $saltNameAllow
     * @param int $jsonDepth
     *
     * @return \stdClass|ErrorResponse|string
     *
     * @throws
     */
    public function decodeSignedData(
        string $signDataRaw,
        SaltStorage $saltStorage,
        $class = null,
        bool $isBase64 = true,
        array $saltNameAllow = [],
        int $jsonDepth = self::JSON_MAX_DEPTH
    ): \stdClass|ErrorResponse|string {
        if (empty($signDataRaw)) {
            return new ErrorResponse('empty-data');
        }
        $signData = Decode::decodeSignWithData($signDataRaw);
        if ($signData === null) {
            return new ErrorResponse('wrong-session', 'wrong-data');
        }
        $saltValue = $saltStorage->getSaltByName($signData->getSaltName());
        // Если данные от другого сервиса и это прямое сравнение ключей без хеширования
        if ($signData->getSaltName() === SaltStorage::SERVICE && $signData->getAlgo() === Encode::DIRECT) {
            if ($saltValue !== $signData->getHash()) {
                return new ErrorResponse('wrong-service-key');
            }

            if ($class === null) {
                return $signData->getData();
            }

            $json = json_decode($signData->getData(), false, $jsonDepth, JSON_THROW_ON_ERROR);
            return new $class($json);
        }

        $isAllow = count($saltNameAllow) === 0 || in_array($signData->getSaltName(), $saltNameAllow);
        if (!$isAllow) {
            $hint = 'check-salt-name-allow-variable: ' . implode(',', $saltNameAllow);
            return new ErrorResponse('access-denied', $hint);
        }

        $serverHash = Encode::createRawSign($signData->getData(), $saltValue, $signData->getAlgo());
        if ($serverHash !== $signData->getHash()) {
            return new ErrorResponse('wrong-session', 'wrong-sign');
        }

        $jsonRaw = $signData->getData();
        if ($isBase64) {
            $jsonRaw = base64_decode($jsonRaw);
            if (empty($jsonRaw)) {
                return new ErrorResponse('wrong-data', 'check-base64');
            }
        }

        if ($class === null) {
            return $jsonRaw;
        }

        $json = json_decode($jsonRaw, false, $jsonDepth, JSON_THROW_ON_ERROR);
        $session = new $class(empty($json) ? new \stdClass() : $json);

        $isExpired = $session->getTtl() !== -1 && $session->getTtl() < time();
        if ($isExpired) {
            return new ErrorResponse('wrong-session', 'session-expired');
        }

        return $session;
    }

    /**
     * @param array $data
     */
    #[NoReturn] public static function successResponse(array $data): void
    {
        header('Access-Control-Allow-Origin: *');
        Response::flush(Response::jsonSuccess($data));
        exit;
    }


    //    public function serviceRequest(string $url, Salt $salt, array $safeData,
    //    array $userData, $algo = Salt::SERVICE): ?string
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
