<?php

namespace yii2swoole\webserver;

use Yii;
use yii\base\InvalidConfigException;
use yii\web\HeaderCollection;
use yii\web\RequestParserInterface;
use yii\web\NotFoundHttpException;
use yii\web\Cookie;
use Swoole\Http\Request as SwooleRequest;

/**
 * Class Request
 * @package $webIndexFile
 * @package yii2swoole\webserver
 */
class Request extends \yii\web\Request
{
    /**
     * @var SwooleRequest
     */
    public $swooleRequest;

    public $webIndexFile;

    private $_hostInfo;

    /**
     * init
     */
    public function init()
    {
        $this->webIndexFile = Yii::getAlias($this->webIndexFile);
        $this->setScriptFile($this->webIndexFile);
        $this->setScriptUrl(basename($this->webIndexFile));
        if(empty($this->cookieValidationKey)){
            $this->cookieValidationKey = md5($this->webIndexFile);
        }
        parent::init();
    }

    /**
     * setSwooleRequest
     * @param SwooleRequest $request
     */
    public function setSwooleRequest(SwooleRequest $request)
    {
        $this->swooleRequest = $request;
        $this->clear();
    }

    /**
     * @return SwooleRequest
     */
    public function getSwooleRequest()
    {
        return $this->swooleRequest;
    }

    /**
     * @inheritdoc
     */
    public function resolve()
    {
        $result = Yii::$app->getUrlManager()->parseRequest($this);
        if ($result !== false) {
            [$route, $params] = $result;
            if ($this->getQueryParams() === null) {
                $this->_queryParams = $params;
            } else {
                $this->_queryParams = $params + $this->_queryParams;
            }
            return [$route, $this->getQueryParams()];
        }

        throw new NotFoundHttpException(Yii::t('yii', 'Page not found.'));
    }

    private $_headers;

    /**
     * @inheritdoc
     */
    public function getHeaders()
    {
        if ($this->_headers === null) {
            $this->_headers = new HeaderCollection();
            foreach ($this->swooleRequest->header as $name => $value) {
                $this->_headers->add($name, $value);
            }
        }
        return $this->_headers;
    }

    /**
     * @inheritdoc
     */
    public function getMethod()
    {
        if (isset($this->swooleRequest->post[$this->methodParam])) {
            return strtoupper($this->swooleRequest->post[$this->methodParam]);
        }

        if ($this->headers->has('X-Http-Method-Override')) {
            return strtoupper($this->headers->get('X-Http-Method-Override'));
        }

        if (isset($this->swooleRequest->server['request_method'])) {
            return strtoupper($this->swooleRequest->server['request_method']);
        }

        return 'GET';
    }

    private $_rawBody;

    /**
     * @inheritdoc
     */
    public function getRawBody()
    {
        if ($this->_rawBody === null) {
            $this->_rawBody = $this->swooleRequest->rawContent();
        }
        return $this->_rawBody;
    }

    public $_bodyParams;

    /**
     * @inheritdoc
     */
    public function getBodyParams()
    {
        if ($this->_bodyParams === null) {
            if (isset($this->swooleRequest->post[$this->methodParam])) {
                $this->_bodyParams = $this->swooleRequest->post;
                unset($this->_bodyParams[$this->methodParam]);
                return $this->_bodyParams;
            }
            $contentType = $this->getContentType();
            if (($pos = strpos($contentType, ';')) !== false) {
                // e.g. application/json; charset=UTF-8
                $contentType = substr($contentType, 0, $pos);
            }
            if (isset($this->parsers[$contentType])) {
                $parser = Yii::createObject($this->parsers[$contentType]);
                if (!($parser instanceof RequestParserInterface)) {
                    throw new InvalidConfigException("The '$contentType' request parser is invalid. It must implement the yii\\web\\RequestParserInterface.");
                }
                $this->_bodyParams = $parser->parse($this->getRawBody(), $contentType);
            } elseif (isset($this->parsers['*'])) {
                $parser = Yii::createObject($this->parsers['*']);
                if (!($parser instanceof RequestParserInterface)) {
                    throw new InvalidConfigException("The fallback request parser is invalid. It must implement the yii\\web\\RequestParserInterface.");
                }
                $this->_bodyParams = $parser->parse($this->getRawBody(), $contentType);
            } elseif ($this->getMethod() === 'POST') {
                // PHP has already parsed the body so we have all params in $this->swoole->post
                $this->_bodyParams = $this->swooleRequest->post;
            } else {
                $this->_bodyParams = [];
                mb_parse_str($this->getRawBody(), $this->_bodyParams);
            }
        }
        return $this->_bodyParams;
    }

    private $_queryParams;

    /**
     * @inheritdoc
     */
    public function getQueryParams()
    {
        if ($this->_queryParams === null) {
            $this->_queryParams = $this->swooleRequest ? $this->swooleRequest->get : [];
        }

        return $this->_queryParams;
    }

    /**
     * getHostInfo
     * @return string|null
     */
    public function getHostInfo()
    {
        if ($this->_hostInfo === null) {
            $secure = $this->getIsSecureConnection();
            $http = $secure ? 'https' : 'http';

            if ($this->getSecureForwardedHeaderTrustedPart('host') !== null) {
                $this->_hostInfo = $http . '://' . $this->getSecureForwardedHeaderTrustedPart('host');
            } elseif ($this->headers->has('X-Forwarded-Host')) {
                $this->_hostInfo = $http . '://' . trim(explode(',', $this->headers->get('X-Forwarded-Host'))[0]);
            } elseif ($this->headers->has('X-Original-Host')) {
                $this->_hostInfo = $http . '://' . trim(explode(',', $this->headers->get('X-Original-Host'))[0]);
            } elseif ($this->headers->has('Host')) {
                $this->_hostInfo = $http . '://' . $this->headers->get('Host');
            } elseif ($this->getServerName()) {
                $this->_hostInfo = $http . '://' . $this->getServerName();
                $port = $secure ? $this->getSecurePort() : $this->getPort();
                if (($port !== 80 && !$secure) || ($port !== 443 && $secure)) {
                    $this->_hostInfo .= ':' . $port;
                }
            }
        }

        return $this->_hostInfo;
    }

    /**
     * @inheritdoc
     */
    protected function loadCookies()
    {
        $cookies = [];
        if ($this->enableCookieValidation) {
            if ($this->cookieValidationKey == '') {
                throw new InvalidConfigException(get_class($this) . '::cookieValidationKey must be configured with a secret key.');
            }
            foreach ($this->getSwooleRequest()->cookie??[] as $name => $value) {
                if (!is_string($value)) {
                    continue;
                }
                $data = Yii::$app->getSecurity()->validateData($value, $this->cookieValidationKey);
                if ($data === false) {
                    continue;
                }
                $data = @unserialize($data);
                if (is_array($data) && isset($data[0], $data[1]) && $data[0] === $name) {
                    $cookies[$name] = new Cookie([
                        'name' => $name,
                        'value' => $data[1],
                        'expire' => null,
                    ]);
                }
            }
        } else {
            foreach ($this->getSwooleRequest()->cookie??[] as $name => $value) {
                $cookies[$name] = new Cookie([
                    'name' => $name,
                    'value' => $value,
                    'expire' => null,
                ]);
            }
        }

        return $cookies;
    }

    /**
     * @inheritdoc
     */
    protected function resolveRequestUri()
    {
        if (isset($this->swooleRequest->server['request_uri'])) {
            $requestUri = $this->swooleRequest->server['request_uri'];
            if ($requestUri !== '' && $requestUri[0] !== '/') {
                $requestUri = preg_replace('/^(http|https):\/\/[^\/]+/i', '', $requestUri);
            }
        } else {
            throw new InvalidConfigException('Unable to determine the request URI.');
        }
        return $requestUri;
    }

    /**
     * @inheritdoc
     */
    public function getQueryString()
    {
        return isset($this->swooleRequest->server['query_string']) ? $this->swooleRequest->server['query_string'] : '';
    }

    /**
     * @inheritdoc
     */
    public function getIsSecureConnection()
    {
        if (isset($this->swooleRequest->server['https']) && (strcasecmp($this->swooleRequest->server['https'], 'on') === 0 || $this->swooleRequest->server['https'] == 1)) {
            return true;
        }
        foreach ($this->secureProtocolHeaders as $header => $values) {
            if (($headerValue = $this->headers->get($header, null)) !== null) {
                foreach ($values as $value) {
                    if (strcasecmp($headerValue, $value) === 0) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /**
     * @inheritdoc
     */
    public function getServerName()
    {
        return $this->headers->get('server_name');
    }

    /**
     * @inheritdoc
     */
    public function getServerPort()
    {
        return isset($this->swooleRequest->server['server_port']) ? (int)$this->swooleRequest->server['server_port'] : null;
    }

    /**
     * @inheritdoc
     */
    public function getRemoteIP()
    {
        if($this->headers->get('x-real-ip')){
            return $this->headers->get('x-real-ip');
        }
        return isset($this->swooleRequest->server['remote_addr']) ? $this->swooleRequest->server['remote_addr'] : null;
    }

    /**
     * @inheritdoc
     */
    public function getRemoteHost()
    {
        return isset($this->swooleRequest->server['remote_host']) ? $this->swooleRequest->server['remote_host'] : null;
    }

    /**
     * @inheritdoc
     */
    public function getAuthCredentials()
    {
        $auth_token = $this->getHeaders()->get('Authorization');
        if ($auth_token !== null && strncasecmp($auth_token, 'basic', 5) === 0) {
            $parts = array_map(function ($value) {
                return strlen($value) === 0 ? null : $value;
            }, explode(':', base64_decode(mb_substr($auth_token, 6)), 2));

            if (count($parts) < 2) {
                return [$parts[0], null];
            }

            return $parts;
        }

        return [null, null];
    }

    /**
     * Encodes an ISO-8859-1 string to UTF-8
     * @param string $s
     * @return string the UTF-8 translation of `s`.
     * @see https://github.com/symfony/polyfill-php72/blob/master/Php72.php#L24
     */
    private function utf8Encode($s)
    {
        $s .= $s;
        $len = \strlen($s);
        for ($i = $len >> 1, $j = 0; $i < $len; ++$i, ++$j) {
            switch (true) {
                case $s[$i] < "\x80": $s[$j] = $s[$i]; break;
                case $s[$i] < "\xC0": $s[$j] = "\xC2"; $s[++$j] = $s[$i]; break;
                default: $s[$j] = "\xC3"; $s[++$j] = \chr(\ord($s[$i]) - 64); break;
            }
        }
        return substr($s, 0, $j);
    }

    /**
     * 清理变量
     */
    public function clear()
    {
        $this->_headers = null;
        $this->_bodyParams = null;
        $this->_queryParams = null;
        $this->_rawBody = null;
        $this->setHostInfo(null);
        $this->setPathInfo(null);
        $this->setUrl(null);
        $this->setAcceptableContentTypes(null);
        $this->setAcceptableLanguages(null);
        $this->getCsrfToken(true);
    }

}