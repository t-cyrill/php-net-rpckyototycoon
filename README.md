RPCKyotoTycoon is a lightweight PHP 5.3 library for issuing Http RPC KyotoTycoon requests.

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


