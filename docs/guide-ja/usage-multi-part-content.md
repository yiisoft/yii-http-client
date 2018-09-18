マルチパートコンテント
======================

HTTP のメッセージコンテントは、コンテントタイプの異なるいくつかの部分から成る場合があります。
通常、ファイルのアップロードをリクエストする場合に、それが必要になります。
[[\yii\httpclient\Request]] の `addBodyPart()`、`addFile()` または`addFileContent()` メソッドを使って、マルチパートのコンテントを作成することが出来ます。
例えば、ウェブフォームを使うファイルのアップロードをエミュレートしたい場合は、次のようなコードを使用する事が出来ます。

```php
use yii\httpclient\Client;

$client = new Client();
$response = $client->createRequest()
    ->setMethod('post')
    ->setUrl('http://domain.com/file/upload')
    ->addFile('file', '/path/to/source/file.jpg')
    ->send();
```

リクエストがマルチパートであるとマークされている場合であっても、[[\yii\httpclient\Request::$params]] が指定されている場合は、
その値がコンテントの一部として自動的に送信されます。
例えば、次のようなフォームの送信をエミュレートしたいと仮定しましょう。

```html
<form name="profile-form" method="post" action="http://domain.com/user/profile" enctype="multipart/form-data">
    <input type="text" name="username" value="">
    <input type="text" name="email" value="">
    <input type="file" name="avatar">
    <!-- ... -->
</form>
```

これは、次のようなコードを使って実行することが出来ます。

```php
use yii\httpclient\Client;

$client = new Client();
$response = $client->createRequest()
    ->setMethod('post')
    ->setUrl('http://domain.com/user/profile')
    ->setParams([
        'username' => 'johndoe',
        'email' => 'johndoe@domain.com',
    ])
    ->addFile('avatar', '/path/to/source/image.jpg')
    ->send();
```

複数のファイルを同じ名前で添付すると、最後のファイルが他のファイルを上書きすることに注意して下さい。
添付ファイルについて表形式入力のインデックスがあり得る場合は、自分で制御しなければなりません。例えば、

```php
use yii\httpclient\Client;

$client = new Client();
$response = $client->createRequest()
    ->setMethod('POST')
    ->setUrl('http://domain.com/gallery')
    ->addFile('avatar[0]', '/path/to/source/image1.jpg')
    ->addFile('avatar[1]', '/path/to/source/image2.jpg')
    ...
    ->send();
```
