<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\httpclient;

use yii\base\BaseObject;
use yii\helpers\Json;

/**
 * JsonParser parses HTTP message content as JSON.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.0
 */
class JsonParser extends BaseObject implements ParserInterface
{
    /**
     * @var bool $asArray whether to return objects in terms of associative arrays.
     */
    public $asArray = true;

    /**
     * @var int $depth the recursion depth.
     */
    public $depth = 512;

    /**
     * @var int $options the decode options.
     */
    public $options = 0;

    /**
     * {@inheritdoc}
     */
    public function parse(Response $response)
    {
        return Json::decode($response->getBody()->__toString(), $this->asArray, $this->depth, $this->options);
    }
}
