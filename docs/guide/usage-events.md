Events
======

[[\yii\httpclient\Request]] provides several events, which can be handled via event handler or behavior:

- [[\yii\httpclient\Request::EVENT_BEFORE_SEND]] - raised before sending request.
- [[\yii\httpclient\Request::EVENT_AFTER_SEND]] - raised after sending request.

These events may be used to adjust request parameters or received response.
For example:

```php
use yii\httpclient\Client;
use yii\httpclient\Request;
use yii\httpclient\RequestEvent;

$client = new Client();

$request = $client->createRequest()
    ->setMethod('GET')
    ->setUrl('http://api.domain.com')
    ->setParams(['param' => 'value']);

// Ensure signature generation based on final data set:
$request->on(Request::EVENT_BEFORE_SEND, function (RequestEvent $event) {
    $params = $event->request->getParams();

    $signature = md5(http_build_query($params));
    $params['signature'] = $signature;

    $event->request->setParams($params);
});

// Normalize response data:
$request->on(Request::EVENT_AFTER_SEND, function (RequestEvent $event) {
    $data = $event->response->getParsedBody();

    $data['content'] = base64_decode($data['encoded_content']);

    $event->response->getParsedBody($data);
});

$response = $request->send();
```

> Warning: PSR-7 interfaces methods `with*()` cause object cloning to keep immutability, and thus will strip new object
  from any attached behaviors or event handlers.

Attaching event handlers to the [[\yii\httpclient\Request]] instance is not very practical.
You may handle same use cases using events of [[\yii\httpclient\Client]] class:

- [[\yii\httpclient\Client::EVENT_BEFORE_SEND]] - raised before sending request.
- [[\yii\httpclient\Client::EVENT_AFTER_SEND]] - raised after sending request.

These events are triggered for all requests created via client in the same way and with the same signature as
the ones from [[\yii\httpclient\Request]].
For example:

```php
use yii\httpclient\Client;
use yii\httpclient\RequestEvent;

$client = new Client();

$client->on(Client::EVENT_BEFORE_SEND, function (RequestEvent $event) {
    // ...
});
$client->on(Client::EVENT_AFTER_SEND, function (RequestEvent $event) {
    // ...
});
```

> Note: [[\yii\httpclient\Client]] and [[\yii\httpclient\Request]] share the same names for `EVENT_BEFORE_SEND` and
  `EVENT_AFTER_SEND` events, so you can create behavior which can be applied for both of these classes.
