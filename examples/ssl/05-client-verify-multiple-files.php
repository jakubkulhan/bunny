<?php

/*
 *  Use case
 *    - client certificate should be used
 *    - file `client.cert` is a  client certificate
 *    - file `client.key`:
 *      - is a private key client certificate
 *      - encoded with a passphrase
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
        'local_cert'        => 'client.cert',
        'local_pk'          => 'client.key',
        'passphrase'        => 'passphrase-for-client.key',
    ],
];

