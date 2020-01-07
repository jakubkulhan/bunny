<?php

/*
 *  Use case
 *    - self-signed certificates
 *    - peer name (for certificates checks) will be taken from `host`
 *
 *   See also RabbitMQ config: tests/ssl/rabbitmq.ssl.verify_none.conf
 */
$clientConfig = [
    'host' => 'rabbitmq.example.com',
    // ...
    'ssl'  => [
        'cafile'            => 'ca.pem',
        'allow_self_signed' => true,
        'verify_peer'       => true,
    ],
];
