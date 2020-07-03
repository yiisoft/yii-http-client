<?php
/**
 * @link http://www.yiiframework.com/
 *
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\httpclient;

use yii\base\Event;

/**
 * RequestEvent represents the event parameter used for an request events.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 *
 * @since 2.0.1
 */
class RequestEvent extends Event
{
    /**
     * @event RequestEvent an event raised right before sending request.
     */
    const BEFORE_SEND = 'yii\httpclient\RequestEvent::BEFORE_SEND';

    /**
     * @event RequestEvent an event raised right after request has been sent.
     */
    const AFTER_SEND = 'yii\httpclient\RequestEvent::AFTER_SEND';

    /**
     * @var Request related HTTP request instance.
     */
    public $request;
    /**
     * @var Response|null related HTTP response.
     *                    This field will be filled only in case some response is already received, e.g. after request is sent.
     */
    public $response;

    /**
     * @param string $name event name
     * @param Request related HTTP request instance.
     * @param Response|null related HTTP response.
     */
    public function __construct(string $name, Request $request, Response $response = null)
    {
        parent::__construct($name);
        $this->request = $request;
        $this->response = $response;
    }

    public static function beforeSend($request): self
    {
        return new static(static::BEFORE_SEND, $request);
    }

    public static function afterSend($request, $response): self
    {
        return new static(static::AFTER_SEND, $request, $response);
    }
}
