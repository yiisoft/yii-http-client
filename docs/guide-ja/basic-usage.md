基本的な使用方法
================

このエクステンションは [PSR-7 HTTP メッセージ標準](https://www.php-fig.org/psr/psr-7/) と互換です。
このエクステンションを使う前に、必ず、この標準によって導入された諸概念に慣れ親しんでおいて下さい。

HTTP リクエストを送信するためには、[[\yii\httpclient\Client]] をインスタンス化して、その `createRequest()`
メソッドを使って、HTTP リクエストを作成する必要があります。
次に、あなたの目的に従ってリクエストの全てのパラメータを構成して、リクエストを送信します。
結果として、あなたは、レスポンスの全ての情報とデータを保持する [[\yii\httpclient\Response]] のインスタンスを受け取ることになります。
例えば、

```php
use yii\httpclient\Client;

$client = new Client();
$response = $client->createRequest()
    ->setMethod('post')
    ->setUrl('http://example.com/api/1.0/users')
    ->setParams(['name' => 'John Doe', 'email' => 'johndoe@example.com'])
    ->send();
if ($response->isOk) {
    $newUserId = $response->parsedBody['id'];
}
```

新しいリクエストを準備する作業を単純化するために、`get()`、`post()`、`put()` などのショートカットメソッドを使っても構いません。
同一のドメインに対して複数のリクエストを送信する場合 (例えば REST API 使用する場合) は、
単一の [[\yii\httpclient\Client]] インスタンスを使って、その `baseUrl` プロパティにそのドメインを設定することが出来ます。
そのようにすると、新しいリクエストを作成するときに、相対 URL だけを指定することが出来るようになります。
従って、何らかの REST API に対する数個のリクエストは、下記のように書くことが出来ます。

```php
use yii\httpclient\Client;

$client = new Client(['baseUrl' => 'http://example.com/api/1.0']);

$newUserResponse = $client->post('users', ['name' => 'John Doe', 'email' => 'johndoe@example.com'])->send();
$articleResponse = $client->get('articles', ['name' => 'Yii 2.0'])->send();
$client->post('subscriptions', ['user_id' => $newUserResponse->data['id'], 'article_id' => $articleResponse->data['id']])->send();
```


## さまざまなコンテント形式を使う

デフォルトでは、HTTP リクエストデータは 'form-urlencoded'、例えば、`param1=value1&param2=value2` として送信されます。
これはウェブフォームでは一般的な形式ですが、REST API にとってはそうではなく、
通常はコンテントが JSON または XML の形式であることが要求されます。
リクエストコンテントに使用される形式は、`format` プロパティまたは `setFormat()` メソッドを使用して設定することが出来ます。
下記の形式がサポートされています。

 - [[\yii\httpclient\Client::FORMAT_JSON]] - JSON 形式
 - [[\yii\httpclient\Client::FORMAT_URLENCODED]] - RFC1738 によって urlencode されたクエリ文字列
 - [[\yii\httpclient\Client::FORMAT_RAW_URLENCODED]] - PHP_QUERY_RFC3986 によって urlencode されたクエリ文字列
 - [[\yii\httpclient\Client::FORMAT_XML]] - XML 形式

例えば、

```php
use yii\httpclient\Client;

$client = new Client(['baseUrl' => 'http://example.com/api/1.0']);
$response = $client->createRequest()
    ->setFormat(Client::FORMAT_JSON)
    ->setUrl('articles/search')
    ->setParams([
        'query_string' => 'Yii',
        'filter' => [
            'date' => ['>' => '2015-08-01']
        ],
    ])
    ->send();
```

レスポンスオブジェクトは、'Content-Type' ヘッダとコンテント自体に基づいて、コンテント形式を自動的に検出します。
従って、ほとんどの場合レスポンスの形式を指定する必要はなく、単純に `getData()` メソッドまたは `data` プロパティを使えば、レスポンスを解析することが出来ます。
上記の例の続きとして、レスポンスデータを取得するには次のようにすることが出来ます。

```php
$responseData = $response->getParsedBody(); // 全ての記事を取得
count($response->parsedBody) // 記事の数を取得
$article = $response->parsedBody[0] // 最初の記事を取得
```


## 生のコンテントを扱う

内蔵された形式に依存することを誰もあなたに対して強制するものではありません。HTTP リクエストに生のコンテントを使用する事も、
レスポンスの生のコンテントを PSR-7 のストリームを使って処理することも可能です。例えば、

```php
use yii\httpclient\Client;
use yii\http\MemoryStream;

$client = new Client(['baseUrl' => 'http://example.com/api/1.0']);

$body = new MemoryStream();
$body->write('{query_string: "Yii"}');

$response = $client->createRequest()
    ->setUrl('articles/search')
    ->addHeaders(['content-type' => 'application/json'])
    ->setBody($body)
    ->send();

echo 'Search results:<br>';
echo $response->getBody();
```

[[\yii\httpclient\Request]] は、`content` が設定されていない場合にだけ、指定された `params` をフォーマットします。
[[\yii\httpclient\Response]] は、`parseBody` が要求された場合にだけ、`body` を解析します。

デフォルトでは [[\yii\httpclient\Request]] は [[\yii\http\MemoryStream]] のインスタンスによってボディをインスタンス化します。
従って、`getBody()` を使うと、新しく作成されたリクエストから空のボディにアクセスすることが出来ます。例えば、

```php
use yii\httpclient\Client;

$client = new Client(['baseUrl' => 'http://example.com/api/1.0']);

$request = $client->createRequest()
    ->setUrl('articles/search')
    ->addHeaders(['content-type' => 'application/json']);

$request->getBody() // 空のボディを返す
    ->write('{query_string: "Yii"}'); // ボディのコンテントを設定

$response = $request->send();
```


## リクエストとレスポンスのオブジェクトを事前に構成する

いくつかの似たようなリクエストを単一の [[\yii\httpclient\Client]] インスタンスを使って処理する場合、
例えば REST API を扱うような場合は、リクエストとレスポンスのオブジェクトのためにあなた自身の構成情報を宣言することによって、
コードを単純化して高速化することが出来ます。
そのためには、[[\yii\httpclient\Client]] の `requestConfig` および `responsConfig` のフィールドを使用します。
例えば、特定のクライアントによって作成される全てのリクエストに対して JSON 形式をセットアップしたい場合は、次のようにします。

```php
use yii\httpclient\Client;

$client = new Client([
    'baseUrl' => 'http://example.com/api/1.0',
    'requestConfig' => [
        'format' => Client::FORMAT_JSON
    ],
    'responseConfig' => [
        'format' => Client::FORMAT_JSON
    ],
]);

$request = $client->createRequest();
echo $request->format; // 出力: 'json'
```

> Tip: 何らかの追加の機能を利用するために、構成情報配列の '__class' キーを使って、
  リクエストとレスポンスのオブジェクトにあなた自身のクラスを指定することも可能です。


## ヘッダを扱う

`setHeaders()` メソッドと `addHeaders()` メソッドを使って、リクエストヘッダを指定することが出来ます。
また、`getHeaders()` メソッドまたは `headers` プロパティを使うと、既に定義されているヘッダを [[\yii\web\HeaderCollection]] のインスタンスとして取得することが出来ます。例えば、

```php
use yii\httpclient\Client;

$client = new Client(['baseUrl' => 'http://example.com/api/1.0']);
$request = $client->createRequest()
    ->setHeaders(['content-type' => 'application/json'])
    ->addHeaders(['user-agent' => 'My User Agent']);

$request->setHeader('content-type', 'type/override');
$request->addHeader('accept-language', 'en-US;en');
```

レスポンス・オブジェクトを取得した後は、`getHeaders()` メソッドまたは `headers` プロパティを使って、
すべてのレスポンスヘッダにアクセスすることが出来ます。

```php
$response = $request->send();
echo $response->getHeaderLine('content-encoding');
var_dump($response->getHeaders());
```


## クッキーを扱う

クッキーはヘッダの値として送受信されるだけのものですが、[[\yii\httpclient\Request]] と [[\yii\httpclient\Response]] は、
[[\yii\http\Cookie]] および [[\yii\http\CookieCollection]] を使ってクッキーを扱うための独立したインターフェイスを提供しています。

リクエストのクッキーは `setCookies()` または `addCookies()` メソッドで指定することが出来ます。
また、`getCookies()` メソッドまたは `cookies` プロパティを使うと、既に定義されているクッキーを [[\yii\http\CookieCollection]] のインスタンスとして取得することが出来ます。
例えば、

```php
use yii\httpclient\Client;
use yii\http\Cookie;

$client = new Client(['baseUrl' => 'http://example.com/api/1.0']);
$request = $client->createRequest()
    ->setCookies([
        ['name' => 'country', 'value' => 'USA'],
        new Cookie(['name' => 'language', 'value' => 'en-US']),
    ])
    ->addCookies([
        ['name' => 'view_mode', 'value' => 'full']
    ]);

$request->cookies->add(['name' => 'display-notification', 'value' => '0']);
```

レスポンスオブジェクトを取得した後は、`getCookies()` メソッドまたは `cookies` プロパティを使って、
レスポンスのクッキー全てにアクセスすることが出来ます。

```php
$response = $request->send();
echo $response->getCookies()->get('country');
echo $response->cookies->get('PHPSESSID');
```

単純なコピーを使って、レスポンスオブジェクトからリクエストオブジェクトにクッキーを転送することが出来ます。
例えば、何かのウェブアプリケーションでユーザのプロファイルを編集する必要があるとしましょう。
ユーザのプロファイルはログイン後にのみアクセスできますので、最初にログインして、そこで生成されたセッションを使って更に作業をします。

```php
use yii\httpclient\Client;

$client = new Client(['baseUrl' => 'http://example.com']);

$loginResponse = $client->post('login', [
    'username' => 'johndoe',
    'password' => 'somepassword',
])->send();

// $loginResponse->cookies->get('PHPSESSID') が新しいセッション ID を保持している

$client->post('account/profile', ['birthDate' => '10/11/1982'])
    ->setCookies($loginResponse->cookies) // レスポンスのクッキーをリクエストのクッキーに転送
    ->send();
```
