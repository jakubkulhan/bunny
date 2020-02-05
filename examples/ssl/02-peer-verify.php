<?php

/*
 *  Use case
 *    - self-signed certificates
 *    - peer name (for certificates checks) should not depend on `host`
 *      'rabbitmq.company.ltd' will be used
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
        'peer_name'         => 'rabbitmq.company.ltd',
    ],
];
