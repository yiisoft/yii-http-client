<?php

namespace yii\httpclient\tests\unit;

use yii\helpers\FileHelper;
use yii\httpclient\Client;
use yii\httpclient\Request;
use yii\httpclient\RequestEvent;
use yii\httpclient\Response;
use yii\httpclient\ClientEvent;

/**
 * This is the base class for HTTP message transport unit tests.
 */
abstract class TransportTestCase extends \yii\tests\TestCase
{
    protected function setUp()
    {
        $this->mockApplication();
    }

    /**
     * @return mixed transport configuration.
     */
    abstract protected function transport();

    /**
     * @return Client http client instance
     */
    protected function createClient()
    {
        return new Client($this->transport());
    }

    public function testSend()
    {
        $client = $this->createClient();
        $client->baseUrl = 'http://php.net';
        $response = $client->createRequest()
            ->setMethod('GET')
            ->setUrl('docs.php')
            ->send();

        $this->assertTrue($response->getIsOk());
        $content = $response->getBody()->__toString();
        $this->assertNotEmpty($content);
        $this->assertContains('<h1>Documentation</h1>', $content);
    }

    /**
     * @depends testSend
     */
    public function testSendPost()
    {
        $client = $this->createClient();
        $client->baseUrl = 'http://php.net';
        $response = $client->createRequest()
            ->setMethod('POST')
            ->setUrl('search.php')
            ->setParams(['pattern' => 'curl'])
            ->send();
        $this->assertTrue($response->getIsOk());
    }

    /**
     * @depends testSend
     */
    public function testBatchSend()
    {
        $client = $this->createClient();
        $client->baseUrl = 'http://php.net';

        $requests = [];
        $requests['docs'] = $client->createRequest()
            ->setMethod('GET')
            ->setUrl('docs.php');
        $requests['support'] = $client->createRequest()
            ->setMethod('GET')
            ->setUrl('support.php');

        $responses = $client->batchSend($requests);
        $this->assertCount(count($requests), $responses);

        foreach ($responses as $name => $response) {
            $this->assertTrue($response->getIsOk());
        }

        $this->assertTrue($responses['docs'] instanceof Response);
        $this->assertTrue($responses['support'] instanceof Response);

        $this->assertContains('<h1>Documentation</h1>', $responses['docs']->getBody()->__toString());
        $this->assertContains('Mailing Lists', $responses['support']->getBody()->__toString());
    }

    /**
     * @depends testSend
     */
    public function testFollowLocation()
    {
        $client = $this->createClient();
        $client->baseUrl = 'http://php.net';

        $request = $client->createRequest()
            ->setMethod('GET')
            ->setUrl('search.php')
            ->setParams([
                'show' => 'quickref',
                'pattern' => 'array_merge'
            ]);

        $response = $request->setOptions([
            'followLocation' => false,
        ])->send();
        $this->assertEquals('302', $response->statusCode);

        $response = $request->setOptions([
            'followLocation' => true,
        ])->send();
        $this->assertTrue($response->getIsOk());
    }

    /**
     * @depends testSend
     */
    public function testSendError()
    {
        $client = $this->createClient();
        $client->baseUrl = 'http://unexisting.domain';
        $request = $client->createRequest()
            ->setMethod('GET')
            ->setUrl('unexisting.php')
            ->addOptions(['timeout' => 1]);

        $this->expectException(\yii\httpclient\Exception::class);

        $request->send();
    }

    /**
     * @depends testSend
     */
    public function testSendEvents()
    {
        $client = $this->createClient();
        $client->baseUrl = 'http://php.net';

        $request = $client->createRequest()
            ->setMethod('GET')
            ->setUrl('docs.php');

        $beforeSendEvent = null;
        $request->on(RequestEvent::BEFORE_SEND, function(RequestEvent $event) use (&$beforeSendEvent) {
            $beforeSendEvent = $event;
        });

        $afterSendEvent = null;
        $request->on(RequestEvent::AFTER_SEND, function(RequestEvent $event) use (&$afterSendEvent) {
            $afterSendEvent = $event;
        });

        $response = $request->send();

        $this->assertTrue($beforeSendEvent instanceof RequestEvent);
        $this->assertSame($request, $beforeSendEvent->request);
        $this->assertNull($beforeSendEvent->response);

        $this->assertTrue($afterSendEvent instanceof RequestEvent);
        $this->assertSame($request, $afterSendEvent->request);
        $this->assertSame($response, $afterSendEvent->response);
    }

    /**
     * @depends testSendEvents
     */
    public function testClientSendEvents()
    {
        $client = $this->createClient();
        $client->baseUrl = 'http://php.net';

        $request = $client->createRequest()
            ->setMethod('GET')
            ->setUrl('docs.php');

        $beforeSendEvent = null;
        $client->on(ClientEvent::BEFORE_SEND, function(RequestEvent $event) use (&$beforeSendEvent) {
            $beforeSendEvent = $event;
        });

        $afterSendEvent = null;
        $client->on(ClientEvent::AFTER_SEND, function(RequestEvent $event) use (&$afterSendEvent) {
            $afterSendEvent = $event;
        });

        $response = $request->send();

        $this->assertTrue($beforeSendEvent instanceof RequestEvent);
        $this->assertSame($request, $beforeSendEvent->request);
        $this->assertNull($beforeSendEvent->response);

        $this->assertTrue($afterSendEvent instanceof RequestEvent);
        $this->assertSame($request, $afterSendEvent->request);
        $this->assertSame($response, $afterSendEvent->response);
    }

    /**
     * @depends testBatchSend
     * @depends testClientSendEvents
     */
    public function testBatchSendEvents()
    {
        $client = $this->createClient();
        $client->baseUrl = 'http://php.net';

        $beforeSendUrls = [];
        $client->on(ClientEvent::BEFORE_SEND, function(RequestEvent $event) use (&$beforeSendUrls) {
            $beforeSendUrls[] = $event->request->getUri()->__toString();
        });

        $afterSendUrls = [];
        $client->on(ClientEvent::AFTER_SEND, function(RequestEvent $event) use (&$afterSendUrls) {
            $afterSendUrls[] = $event->request->getUri()->__toString();
        });

        $requests = [];
        $requests['docs'] = $client->createRequest()
            ->setMethod('GET')
            ->setUrl('docs.php');
        $requests['support'] = $client->createRequest()
            ->setMethod('GET')
            ->setUrl('support.php');

        $responses = $client->batchSend($requests);

        $expectedUrls = [
            $client->baseUrl . '/docs.php',
            $client->baseUrl . '/support.php',
        ];
        $this->assertEquals($expectedUrls, $beforeSendUrls);
        $this->assertEquals($expectedUrls, $afterSendUrls);
    }

    public function testInvalidUrl()
    {
        $client = $this->createClient();
        $request = $client->get('htp:/example.com');
        $this->assertEquals('htp:/example.com', $request->getUri()->__toString());

        $this->expectException(\yii\httpclient\Exception::class);
        $request->send();
    }

    /**
     * @depends testSend
     */
    public function testCustomSslCertificate()
    {
        if (!function_exists('openssl_pkey_new')) {
            $this->markTestSkipped('OpenSSL PHP extension required.');
        }

        $dn = [
            'countryName' => 'GB',
            'stateOrProvinceName' => 'State',
            'localityName' => 'SomewhereCity',
            'organizationName' => 'MySelf',
            'organizationalUnitName' => 'Whatever',
            'commonName' => 'mySelf',
            'emailAddress' => 'user@domain.com'
        ];
        $passphrase = '1234';

        $res = openssl_pkey_new();
        $csr = openssl_csr_new($dn, $res);
        $sscert = openssl_csr_sign($csr, null, $res, 365);
        openssl_x509_export($sscert, $publicKey);
        openssl_pkey_export($res, $privateKey, $passphrase);
        openssl_csr_export($csr, $csrStr);

        $filePath = $this->app->getAlias('@runtime');
        FileHelper::createDirectory($filePath);

        $privateKeyFilename = $filePath . DIRECTORY_SEPARATOR . 'private.pem';
        $publicKeyFilename = $filePath . DIRECTORY_SEPARATOR . 'public.pem';

        file_put_contents($publicKeyFilename, $publicKey);
        file_put_contents($privateKeyFilename, $privateKey);

        $client = $this->createClient();
        $client->baseUrl = 'https://secure.php.net/';
        $response = $client->createRequest()
            ->setMethod('GET')
            ->setUrl('docs.php')
            ->setOptions([
                'sslLocalCert' => $publicKeyFilename,
                'sslLocalPk' => $privateKeyFilename,
                'sslPassphrase' => $passphrase,
            ])
            ->send();

        $this->assertTrue($response->getIsOk());
        $content = $response->getBody()->__toString();
        $this->assertNotEmpty($content);
        $this->assertContains('<h1>Documentation</h1>', $content);
    }
}
