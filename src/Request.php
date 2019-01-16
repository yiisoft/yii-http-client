<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\httpclient;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;
use yii\helpers\ArrayHelper;
use yii\helpers\FileHelper;
use yii\helpers\Yii;
use yii\http\MemoryStream;
use yii\http\Uri;

/**
 * Request represents HTTP request.
 *
 * @property string $method Request method.
 * @property array $options Request options. This property is read-only.
 * @property string|array $url Target URL or URL parameters.
 * @property UriInterface $uri the PSR URI instance.
 * @property array|null $params request params.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.0
 */
class Request extends Message implements RequestInterface
{
    /**
     * @var string|array target URL.
     */
    private $_url;
    /**
     * @var UriInterface URI instance.
     * @since 2.1.0
     */
    private $_uri;
    /**
     * @var string request method.
     */
    private $_method = 'GET';
    /**
     * @var array|null request params
     * @since 2.1.0
     */
    private $_params;
    /**
     * @var array multipart body parts information.
     * @since 2.1.0
     */
    private $_bodyParts = [];
    /**
     * @var array request options.
     */
    private $_options = [];
    /**
     * @var bool whether request object has been prepared for sending or not.
     * @see prepare()
     */
    private $isPrepared = false;


    /**
     * {@inheritdoc}
     * @since 2.1.0
     */
    public function getRequestTarget()
    {
        return $this->getUri()->__toString();
    }

    /**
     * {@inheritdoc}
     * @since 2.1.0
     */
    public function withRequestTarget($requestTarget)
    {
        if ($requestTarget === $this->getRequestTarget()) {
            return $this;
        }

        $newInstance = clone $this;
        $newInstance->setUri(new Uri(['string' => $requestTarget]));
        return $newInstance;
    }

    /**
     * {@inheritdoc}
     * @since 2.1.0
     */
    public function getUri()
    {
        if (!$this->_uri instanceof UriInterface) {
            if ($this->_uri === null) {
                $uri = Uri::fromString($this->createFullUrl($this->getUrl()));
            } elseif ($this->_uri instanceof \Closure) {
                $uri = call_user_func($this->_uri, $this);
            } else {
                $uri = $this->_uri;
            }

            $this->_uri = Yii::ensureObject($uri, UriInterface::class);
        }

        return $this->_uri;
    }

    /**
     * Specifies the URI instance.
     * @param UriInterface|\Closure|array $uri URI instance or its DI compatible configuration.
     * @since 2.1.0
     */
    public function setUri($uri)
    {
        $this->_uri = $uri;
    }

    /**
     * {@inheritdoc}
     * @since 2.1.0
     */
    public function withUri(UriInterface $uri, $preserveHost = false)
    {
        if ($this->getUri() === $uri) {
            return $this;
        }

        $newInstance = clone $this;

        $newInstance->setUri($uri);
        if (!$preserveHost) {
            return $newInstance->withHeader('host', $uri->getHost());
        }

        return $newInstance;
    }

    /**
     * Sets target URL.
     * @param string|array $url use a string to represent a URL (e.g. `http://some-domain.com`, `item/list`),
     * or an array to represent a URL with query parameters (e.g. `['item/list', 'param1' => 'value1']`).
     * @return $this self reference.
     */
    public function setUrl($url)
    {
        $this->_url = $url;
        $this->setUri(null);
        return $this;
    }

    /**
     * Returns target URL.
     * @return string|array target URL or URL parameters
     */
    public function getUrl()
    {
        return $this->_url;
    }

    /**
     * @param string $method request method
     * @return $this self reference.
     */
    public function setMethod($method)
    {
        $this->_method = $method;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getMethod()
    {
        return $this->_method;
    }

    /**
     * {@inheritdoc}
     * @since 2.1.0
     */
    public function withMethod($method)
    {
        if ($this->getMethod() === $method) {
            return $this;
        }

        $newInstance = clone $this;
        $newInstance->setMethod($method);
        return $newInstance;
    }

    /**
     * Following options are supported:
     *
     * - timeout: int, the maximum number of seconds to allow request to be executed.
     * - proxy: string, URI specifying address of proxy server. (e.g. tcp://proxy.example.com:5100).
     * - userAgent: string, the contents of the "User-Agent: " header to be used in a HTTP request.
     * - followLocation: bool, whether to follow any "Location: " header that the server sends as part of the HTTP header.
     * - maxRedirects: int, the max number of redirects to follow.
     * - protocolVersion: float|string, HTTP protocol version.
     * - sslVerifyPeer: bool, whether verification of the peer's certificate should be performed.
     * - sslCafile: string, location of Certificate Authority file on local filesystem which should be used with
     *   the 'sslVerifyPeer' option to authenticate the identity of the remote peer.
     * - sslCapath: string, a directory that holds multiple CA certificates.
     *
     * You may set options using keys, which are specific to particular transport, like `[CURLOPT_VERBOSE => true]` in case
     * there is a necessity for it.
     *
     * @param array $options request options.
     * @return $this self reference.
     */
    public function setOptions(array $options)
    {
        $this->_options = $options;
        return $this;
    }

    /**
     * @return array request options.
     */
    public function getOptions()
    {
        return $this->_options;
    }

    /**
     * Adds more options to already defined ones.
     * Please refer to [[setOptions()]] on how to specify options.
     * @param array $options additional options
     * @return $this self reference.
     */
    public function addOptions(array $options)
    {
        // `array_merge()` will produce invalid result for cURL options,
        // while `ArrayHelper::merge()` is unable to override cURL options
        foreach ($options as $key => $value) {
            if (is_array($value) && isset($this->_options[$key])) {
                $value = ArrayHelper::merge($this->_options[$key], $value);
            }
            $this->_options[$key] = $value;
        }
        return $this;
    }

    /**
     * Sets the request params, which composes HTTP message.
     * @param array|null $params request params.
     * @return $this self reference.
     * @since 2.1.0
     */
    public function setParams($params)
    {
        if ($this->isPrepared) {
            $this->setBody(null);
            $this->isPrepared = false;
        }

        $this->_params = $params;
        return $this;
    }

    /**
     * Returns the request params.
     * @return array|null request params.
     * @since 2.1.0
     */
    public function getParams()
    {
        return $this->_params;
    }

    /**
     * Adds request params to the existing ones.
     * @param array $params additional request params.
     * @return $this self reference.
     * @since 2.1.0
     */
    public function addParams($params)
    {
        if ($this->isPrepared) {
            $this->setBody(null);
            $this->isPrepared = false;
        }

        if (empty($this->_params)) {
            $this->_params = $params;
        } else {
            $this->_params = array_merge($this->_params, $params);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setBody($body)
    {
        $this->_bodyParts = [];
        parent::setBody($body);
    }

    /**
     * {@inheritdoc}
     */
    public function getBody()
    {
        if (!empty($this->_bodyParts)) {
            $this->prepareMultiPartContent($this->_bodyParts);
        }
        return parent::getBody();
    }

    /**
     * Returns parts for multipart body.
     * @return array body parts.
     * @since 2.1.0
     */
    public function getBodyParts()
    {
        return $this->_bodyParts;
    }

    /**
     * Adds a content part for multi-part content request.
     * @param string $name part (form input) name.
     * @param \Psr\Http\Message\StreamInterface|string $bodyPart body part content.
     * @param array $options content part options, valid options are:
     *
     *  - contentType - string, part content type
     *  - fileName - string, name of the uploading file
     *  - mimeType - string, part content type in case of file uploading
     *
     * @return $this self reference.
     */
    public function addBodyPart($name, $bodyPart, array $options = [])
    {
        $options['content'] = $bodyPart;
        $this->_bodyParts[$name] = $options;

        return $this;
    }

    /**
     * Adds a file for upload as multi-part content.
     * @see addContent()
     * @param string $name part (form input) name
     * @param string $fileName full name of the source file.
     * @param array $options content part options, valid options are:
     *
     *  - fileName - string, base name of the uploading file, if not set it base name of the source file will be used.
     *  - mimeType - string, file mime type, if not set it will be determine automatically from source file.
     *
     * @return $this self reference.
     */
    public function addFile($name, $fileName, $options = [])
    {
        $content = file_get_contents($fileName);
        if (!isset($options['mimeType'])) {
            $options['mimeType'] = FileHelper::getMimeType($fileName);
        }
        if (!isset($options['fileName'])) {
            $options['fileName'] = basename($fileName);
        }

        return $this->addBodyPart($name, $content, $options);
    }

    /**
     * Adds a string as a file upload.
     * @see addContent()
     * @param string $name part (form input) name
     * @param string $content file content.
     * @param array $options content part options, valid options are:
     *
     *  - fileName - string, base name of the uploading file.
     *  - mimeType - string, file mime type, if not set it 'application/octet-stream' will be used.
     *
     * @return $this self reference.
     */
    public function addFileContent($name, $content, $options = [])
    {
        if (!isset($options['mimeType'])) {
            $options['mimeType'] = 'application/octet-stream';
        }
        if (!isset($options['fileName'])) {
            $options['fileName'] = $name . '.dat';
        }
        return $this->addBodyPart($name, $content, $options);
    }

    /**
     * Prepares this request instance for sending.
     * This method should be invoked by transport before sending a request.
     * Do not call this method unless you know what you are doing.
     * @return $this self reference.
     */
    public function prepare()
    {
        if (!empty($this->_bodyParts)) {
            $this->prepareMultiPartContent($this->_bodyParts);
        } elseif (!$this->hasBody()) {
            $this->getFormatter()->format($this);
        }

        $this->isPrepared = true;

        return $this;
    }

    /**
     * Normalizes given URL value, filling it with actual string URL value.
     * @param array|string $url raw URL.
     * @return string full URL.
     */
    private function createFullUrl($url)
    {
        if (is_array($url)) {
            $params = $url;
            if (isset($params[0])) {
                $url = (string)$params[0];
                unset($params[0]);
            } else {
                $url = '';
            }
        }

        if (!empty($this->client->baseUrl)) {
            if (empty($url)) {
                $url = $this->client->baseUrl;
            } elseif (!preg_match('/^https?:\\/\\//i', $url)) {
                $url = rtrim($this->client->baseUrl, '/') . '/' . ltrim($url, '/');
            }
        }

        if (!empty($params)) {
            if (strpos($url, '?') === false) {
                $url .= '?';
            } else {
                $url .= '&';
            }
            $url .= http_build_query($params);
        }

        return $url;
    }

    /**
     * Prepares multi-part content.
     * @param array $bodyParts multi body parts.
     * @see https://tools.ietf.org/html/rfc7578
     * @see https://tools.ietf.org/html/rfc2616#section-19.5.1 for the Content-Disposition header
     * @see https://tools.ietf.org/html/rfc6266 for more details on the Content-Disposition header
     */
    private function prepareMultiPartContent(array $bodyParts)
    {
        static $disallowedChars = ["\0", '"', "\r", "\n"];

        $contentParts = [];

        $data = $this->getParams();
        if (!empty($data)) {
            foreach ($this->composeFormInputs($data) as $name => $value) {
                $name = str_replace($disallowedChars, '_', $name);
                $contentDisposition = 'Content-Disposition: form-data; name="' . $name . '"';
                $contentParts[] = implode("\r\n", [$contentDisposition, '', $value]);
            }
        }

        // process content parts :
        foreach ($bodyParts as $name => $contentParams) {
            $headers = [];
            $name = str_replace($disallowedChars, '_', $name);
            $contentDisposition = 'Content-Disposition: form-data; name="' . $name . '"';
            if (isset($contentParams['fileName'])) {
                $fileName = str_replace($disallowedChars, '_', $contentParams['fileName']);
                $contentDisposition .= '; filename="' . $fileName . '"';
            }
            $headers[] = $contentDisposition;
            if (isset($contentParams['contentType'])) {
                $headers[] = 'Content-Type: ' . $contentParams['contentType'];
            } elseif (isset($contentParams['mimeType'])) {
                $headers[] = 'Content-Type: ' . $contentParams['mimeType'];
            }
            $contentParts[] = implode("\r\n", [implode("\r\n", $headers), '', $contentParams['content']]);
        }

        // generate safe boundary :
        do {
            $boundary = '---------------------' . md5(mt_rand() . microtime());
        } while (preg_grep("/{$boundary}/", $contentParts));

        // add boundary for each part :
        array_walk($contentParts, function (&$part) use ($boundary) {
            $part = "--{$boundary}\r\n{$part}";
        });

        // add final boundary :
        $contentParts[] = "--{$boundary}--";
        $contentParts[] = '';

        $this->setHeader('content-type', "multipart/form-data; boundary={$boundary}");

        $body = new MemoryStream();
        $body->write(implode("\r\n", $contentParts));
        $this->setBody($body);
    }

    /**
     * Composes given data as form inputs submitted values, taking in account nested arrays.
     * Converts `['form' => ['name' => 'value']]` to `['form[name]' => 'value']`.
     * @param array $data
     * @param string $baseKey
     * @return array
     */
    private function composeFormInputs(array $data, $baseKey = '')
    {
        $result = [];
        foreach ($data as $key => $value) {
            if (!empty($baseKey)) {
                $key = $baseKey . '[' . $key . ']';
            }
            if (is_array($value)) {
                $result = array_merge($result, $this->composeFormInputs($value, $key));
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function composeHeaderLines()
    {
        $headers = parent::composeHeaderLines();
        if ($this->hasCookies()) {
            $headers[] = $this->composeCookieHeader();
        }
        return $headers;
    }

    /**
     * Sends this request.
     * @return Response response instance.
     */
    public function send()
    {
        return $this->client->send($this);
    }

    /**
     * This method is invoked right before this request is sent.
     * The method will invoke [[Client::beforeSend()]] and trigger the [[EVENT_BEFORE_SEND]] event.
     * @since 2.0.1
     */
    public function beforeSend()
    {
        $this->client->beforeSend($this);

        $this->trigger(RequestEvent::beforeSend($this));
    }

    /**
     * This method is invoked right after this request is sent.
     * The method will invoke [[Client::afterSend()]] and trigger the [[EVENT_AFTER_SEND]] event.
     * @param Response $response received response instance.
     * @since 2.0.1
     */
    public function afterSend($response)
    {
        $this->client->afterSend($this, $response);

        $this->trigger(RequestEvent::afterSend($this, $response));

    }

    /**
     * {@inheritdoc}
     */
    public function toString()
    {
        if (!$this->isPrepared) {
            $this->prepare();
        }

        $result = strtoupper($this->getMethod()) . ' ' . $this->getUri();

        $parentResult = parent::toString();
        if ($parentResult !== '') {
            $result .= "\n" . $parentResult;
        }

        return $result;
    }

    /**
     * @return string cookie header value.
     */
    private function composeCookieHeader()
    {
        $parts = [];
        foreach ($this->getCookies() as $cookie) {
            $parts[] = $cookie->name . '=' . $cookie->value;
        }
        return 'Cookie: ' . implode(';', $parts);
    }

    /**
     * @return FormatterInterface message formatter instance.
     */
    private function getFormatter()
    {
        return $this->client->getFormatter($this->getFormat());
    }

    /**
     * {@inheritdoc}
     */
    public function __clone()
    {
        parent::__clone();

        if (is_object($this->_uri)) {
            $this->_uri = clone $this->_uri;
        }
    }
}
