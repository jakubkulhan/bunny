<?php

/*
 *  Use case
 *    - self-signed certificates
 *    - peer name (for certificates checks) should not depend on `host`
 *      'rabbitmq.company.ltd' will be used
 *
 *   See also RabbitMQ config: tests/tls/rabbitmq.tls.verify_none.conf
 */
$clientConfig = [
    'host' => 'rabbitmq.example.com',
    // ...
    'tls'  => [
        'cafile'            => 'ca.pem',
        'allow_self_signed' => true,
        'verify_peer'       => true,
        'peer_name'         => 'rabbitmq.company.ltd',
    ],
];
