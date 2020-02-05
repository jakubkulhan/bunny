<?php

/*
 *  Use case
 *    - client certificate should be used
 *    - file `client.pem`:
 *      - contents both certificate and key
 *
 *   See also RabbitMQ config: tests/ssl/rabbitmq.ssl.verify_peer.conf
 */
$clientConfig = [
    'host' => 'rabbitmq.example.com',
    // ...
    'ssl'  => [
        'cafile'            => 'ca.pem',
        'allow_self_signed' => true,
        'verify_peer'       => true,
        'local_cert'        => 'client.pem',
    ],
];
