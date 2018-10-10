<?php

namespace yii\httpclient\tests\unit;

use yii\httpclient\UrlEncodedParser;
use yii\httpclient\Response;

class UrlEncodedParserTest extends \yii\tests\TestCase
{
    public function testParse()
    {
        $response = new Response();
        $data = [
            'name1' => 'value1',
            'name2' => 'value2',
        ];
        $response->getBody()->write(http_build_query($data));

        $parser = new UrlEncodedParser();
        $this->assertEquals($data, $parser->parse($response));
    }
} 
