RPCKyotoTycoon
=====================================

RPCKyotoTycoon is a lightweight PHP 5.3 library for issuing Http RPC KyotoTycoon requests.

Installation
--------------------
1. Download the [`composer.phar`](http://getcomposer.org/composer.phar).

``` sh
$ curl -s http://getcomposer.org/installer | php
```
2. Run Composer: `php composer.phar install`

Usage
--------------------
```php
<?php

$kt = new RPCKyotoTycoon(
    array(
        'host'    => 'localhost',
        'port'    => 1978,
        'db'      => 'casket.kct',
        'timeout' => array(10, 0)
    )
);
echo $kt->get('key');
```


