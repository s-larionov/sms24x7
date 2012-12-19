### Requirements
* PHP >= 5.4

###Example of use
```php
require_once 'Sms24x7.php';
$api = new Sms24x7(['server' => 'https://api.sms24x7.ru/']);

try {
	$api->authenticate('login@email.com', 'pAsSwOrD');
	$response = $api->sendMessage(['79111111111', '79222222222', ...], 'Text of sms message', 'SENDER');
} catch (Sms24x7Exception $e) {
	echo "{$e->getCode()}: {$e->getMessage()}\n";
}
```
