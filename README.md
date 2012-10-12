RPCKyotoTycoon
=====================================

RPCKyotoTycoon is a lightweight PHP 5.3 library for issuing Http RPC KyotoTycoon requests.

Installation
--------------------

Download the [`composer.phar`](http://getcomposer.org/composer.phar).

``` sh
$ curl -s http://getcomposer.org/installer | php
```

Run Composer: `php composer.phar install`

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


