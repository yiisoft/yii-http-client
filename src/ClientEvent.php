<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\httpclient;

use yii\base\Event;

/**
 * ClientEvent represents the event parameter used for a client events.
 *
 * @author Fabrizio Caldarelli <fabrizio.caldarelli@gmail.com>
 * @since 3.0.0
 */
class ClientEvent extends RequestEvent
{
    /**
     * @event ClientEvent an event raised right before sending request.
     */
    const BEFORE_SEND = 'yii\httpclient\ClientEvent::BEFORE_SEND';

    /**
     * @event ClientEvent an event raised right after request has been sent.
     */
    const AFTER_SEND = 'yii\httpclient\ClientEvent::AFTER_SEND';
}