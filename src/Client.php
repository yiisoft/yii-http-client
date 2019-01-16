<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\httpclient;

use yii\base\Component;
use yii\exceptions\InvalidArgumentException;
use yii\helpers\Yii;
use yii\http\MemoryStream;

/**
 * Client provide high level interface for HTTP requests execution.
 *
 * @property Transport $transport HTTP message transport instance. Note that the type of this property differs
 * in getter and setter. See [[getTransport()]] and [[setTransport()]] for details.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.0
 */
class Client extends Component
{
    /**
     * JSON format
     */
    const FORMAT_JSON = 'json';
    /**
     * urlencoded by RFC1738 query string, like name1=value1&name2=value2
     * @see http://php.net/manual/en/function.urlencode.php
     */
    const FORMAT_URLENCODED = 'urlencoded';
    /**
     * urlencoded by PHP_QUERY_RFC3986 query string, like name1=value1&name2=value2
     * @see http://php.net/manual/en/function.rawurlencode.php
     */
    const FORMAT_RAW_URLENCODED = 'raw-urlencoded';
    /**
     * XML format
     */
    const FORMAT_XML = 'xml';

    /**
     * @var string base request URL.
     */
    public $baseUrl;
    /**
     * @var array the formatters for converting data into the content of the specified [[Message::$format]].
     * The array keys are the format names, and the array values are the corresponding configurations
     * for creating the formatter objects.
     */
    public $formatters = [];
    /**
     * @var array the parsers for converting content of the specified [[Message::$format]] into the data.
     * The array keys are the format names, and the array values are the corresponding configurations
     * for creating the parser objects.
     */
    public $parsers = [];
    /**
     * @var array request object configuration.
     */
    public $requestConfig = [];
    /**
     * @var array response config configuration.
     */
    public $responseConfig = [];
    /**
     * @var int maximum symbols count of the request content, which should be taken to compose a
     * log and profile messages. Exceeding content will be truncated.
     * @see createRequestLogToken()
     */
    public $contentLoggingMaxSize = 2000;

    /**
     * @var Transport|array|string|callable HTTP message transport.
     */
    private $_transport = StreamTransport::class;

    /**
     * Constructor
     * @param Transport|array|string|callable HTTP message transport.
     */
    public function __construct($transport = null)
    {
        if($transport !== null) $this->_transport = $transport;
    }

    /**
     * Sets the HTTP message transport. It can be specified in one of the following forms:
     *
     * - an instance of `Transport`: actual transport object to be used
     * - a string: representing the class name of the object to be created
     * - a configuration array: the array must contain a `class` element which is treated as the object class,
     *   and the rest of the name-value pairs will be used to initialize the corresponding object properties
     * - a PHP callable: either an anonymous function or an array representing a class method (`[$class or $object, $method]`).
     *   The callable should return a new instance of the object being created.
     * @param Transport|array|string $transport HTTP message transport
     */
    public function setTransport($transport)
    {
        $this->_transport = $transport;
    }

    /**
     * @return Transport HTTP message transport instance.
     */
    public function getTransport()
    {
        if (!is_object($this->_transport)) {
            $this->_transport = Yii::createObject($this->_transport);
        }
        return $this->_transport;
    }

    /**
     * Returns HTTP message formatter instance for the specified format.
     * @param string $format format name.
     * @return FormatterInterface formatter instance.
     * @throws InvalidArgumentException on invalid format name.
     */
    public function getFormatter($format)
    {
        static $defaultFormatters = [
            self::FORMAT_JSON => JsonFormatter::class,
            self::FORMAT_URLENCODED => [
                '__class' => UrlEncodedFormatter::class,
                'encodingType' => PHP_QUERY_RFC1738
            ],
            self::FORMAT_RAW_URLENCODED => [
                '__class' => UrlEncodedFormatter::class,
                'encodingType' => PHP_QUERY_RFC3986
            ],
            self::FORMAT_XML => XmlFormatter::class,
        ];

        if (!isset($this->formatters[$format])) {
            if (!isset($defaultFormatters[$format])) {
                throw new InvalidArgumentException("Unrecognized format '{$format}'");
            }
            $this->formatters[$format] = $defaultFormatters[$format];
        }

        if (!is_object($this->formatters[$format])) {
            $this->formatters[$format] = Yii::createObject($this->formatters[$format]);
        }

        return $this->formatters[$format];
    }

    /**
     * Returns HTTP message parser instance for the specified format.
     * @param string $format format name
     * @return ParserInterface parser instance.
     * @throws InvalidArgumentException on invalid format name.
     */
    public function getParser($format)
    {
        static $defaultParsers = [
            self::FORMAT_JSON => JsonParser::class,
            self::FORMAT_URLENCODED => UrlEncodedParser::class,
            self::FORMAT_RAW_URLENCODED => UrlEncodedParser::class,
            self::FORMAT_XML => XmlParser::class,
        ];

        if (!isset($this->parsers[$format])) {
            if (!isset($defaultParsers[$format])) {
                throw new InvalidArgumentException("Unrecognized format '{$format}'");
            }
            $this->parsers[$format] = $defaultParsers[$format];
        }

        if (!is_object($this->parsers[$format])) {
            $this->parsers[$format] = Yii::createObject($this->parsers[$format]);
        }

        return $this->parsers[$format];
    }

    /**
     * @return Request request instance.
     */
    public function createRequest()
    {
        $config = $this->requestConfig;
        if (!isset($config['__class'])) {
            $config['__class'] = Request::class;
        }
        $config['client'] = $this;
        return Yii::createObject($config);
    }

    /**
     * Creates a response instance.
     * @param \Psr\Http\Message\StreamInterface|string $body raw content
     * @param array $headers headers list.
     * @return Response request instance.
     */
    public function createResponse($body = null, array $headers = [])
    {
        $config = $this->responseConfig;
        if (!isset($config['__class'])) {
            $config['__class'] = Response::class;
        }
        $config['client'] = $this;
        $response = Yii::createObject($config);
        if (is_string($body)) {
            $stream = new MemoryStream();
            $stream->write($body);
            $response->setBody($stream);
        } else {
            $response->setBody($body);
        }
        $response->setHeaders($headers);
        return $response;
    }

    /**
     * Performs given request.
     * @param Request $request request to be sent.
     * @return Response response instance.
     * @throws Exception on failure.
     */
    public function send($request)
    {
        return $this->getTransport()->send($request);
    }

    /**
     * Performs multiple HTTP requests in parallel.
     * This method accepts an array of the [[Request]] objects and returns an array of the  [[Response]] objects.
     * Keys of the response array correspond the ones from request array.
     *
     * ```php
     * $client = new Client();
     * $requests = [
     *     'news' => $client->get('http://domain.com/news'),
     *     'friends' => $client->get('http://domain.com/user/friends', ['userId' => 12]),
     * ];
     * $responses = $client->batchSend($requests);
     * var_dump($responses['news']->isOk);
     * var_dump($responses['friends']->isOk);
     * ```
     *
     * @param Request[] $requests requests to perform.
     * @return Response[] responses list.
     */
    public function batchSend(array $requests)
    {
        return $this->getTransport()->batchSend($requests);
    }

    /**
     * Composes the log/profiling message token for the given HTTP request parameters.
     * This method should be used by transports during request sending logging.
     * @param Request $request request instance.
     * @return string log token.
     */
    public function createRequestLogToken($request)
    {
        $token = $request->getMethod() . ' ' . $request->getUri()->__toString();
        $headers = $request->getHeaders();
        if (!empty($headers)) {
            $token .= "\n" . implode("\n", $request->composeHeaderLines());
        }
        if ($request->hasBody()) {
            $body = $request->getBody();
            if ($body->isSeekable()) {
                // log body only if its pointer can be rewind
                $body->seek(0);
                if ($body->getSize() > $this->contentLoggingMaxSize) {
                    $token .= "\n\n" . $body->read($this->contentLoggingMaxSize) . '...';
                } else {
                    $token .= "\n\n" . $body->__toString();
                }
            }
        }

        return $token;
    }

    // Create request shortcut methods :

    /**
     * Creates 'GET' request.
     * @param array|string $url target URL.
     * @param array|string $data if array - request data, otherwise - request content.
     * @param array $headers request headers.
     * @param array $options request options.
     * @return Request request instance.
     */
    public function get($url, $data = null, $headers = [], $options = [])
    {
        return $this->createRequestShortcut('GET', $url, $data, $headers, $options);
    }

    /**
     * Creates 'POST' request.
     * @param array|string $url target URL.
     * @param array|string $data if array - request data, otherwise - request content.
     * @param array $headers request headers.
     * @param array $options request options.
     * @return Request request instance.
     */
    public function post($url, $data = null, $headers = [], $options = [])
    {
        return $this->createRequestShortcut('POST', $url, $data, $headers, $options);
    }

    /**
     * Creates 'PUT' request.
     * @param array|string $url target URL.
     * @param array|string $data if array - request data, otherwise - request content.
     * @param array $headers request headers.
     * @param array $options request options.
     * @return Request request instance.
     */
    public function put($url, $data = null, $headers = [], $options = [])
    {
        return $this->createRequestShortcut('PUT', $url, $data, $headers, $options);
    }

    /**
     * Creates 'PATCH' request.
     * @param array|string $url target URL.
     * @param array|string $data if array - request data, otherwise - request content.
     * @param array $headers request headers.
     * @param array $options request options.
     * @return Request request instance.
     */
    public function patch($url, $data = null, $headers = [], $options = [])
    {
        return $this->createRequestShortcut('PATCH', $url, $data, $headers, $options);
    }

    /**
     * Creates 'DELETE' request.
     * @param array|string $url target URL.
     * @param array|string $data if array - request data, otherwise - request content.
     * @param array $headers request headers.
     * @param array $options request options.
     * @return Request request instance.
     */
    public function delete($url, $data = null, $headers = [], $options = [])
    {
        return $this->createRequestShortcut('DELETE', $url, $data, $headers, $options);
    }

    /**
     * Creates 'HEAD' request.
     * @param array|string $url target URL.
     * @param array $headers request headers.
     * @param array $options request options.
     * @return Request request instance.
     */
    public function head($url, $headers = [], $options = [])
    {
        return $this->createRequestShortcut('HEAD', $url, null, $headers, $options);
    }

    /**
     * Creates 'OPTIONS' request.
     * @param array|string $url target URL.
     * @param array $options request options.
     * @return Request request instance.
     */
    public function options($url, $options = [])
    {
        return $this->createRequestShortcut('OPTIONS', $url, null, [], $options);
    }

    /**
     * This method is invoked right before request is sent.
     * The method will trigger the [[EVENT_BEFORE_SEND]] event.
     * @param Request $request request instance.
     * @since 2.0.1
     */
    public function beforeSend($request)
    {
        $this->trigger(ClientEvent::beforeSend($request));
    }

    /**
     * This method is invoked right after request is sent.
     * The method will trigger the [[EVENT_AFTER_SEND]] event.
     * @param Request $request request instance.
     * @param Response $response received response instance.
     * @since 2.0.1
     */
    public function afterSend($request, $response)
    {
        $this->trigger(ClientEvent::afterSend($request, $response));
    }

    /**
     * @param string $method
     * @param array|string $url
     * @param array|string $params
     * @param array $headers
     * @param array $options
     * @return Request request instance.
     */
    private function createRequestShortcut($method, $url, $params, $headers, $options)
    {
        $request = $this->createRequest()
            ->setMethod($method)
            ->setUrl($url)
            ->addHeaders($headers)
            ->addOptions($options);

        if (is_array($params)) {
            $request->setParams($params);
        } else {
            if (is_string($params)) {
                $body = new MemoryStream();
                $body->write($params);
                $request->setBody($body);
            } else {
                $request->setBody($params);
            }
        }

        return $request;
    }
}