<?php

namespace Bunny;

use Bunny\Async\Client as AsyncClient;
use Bunny\Exception\ClientException;
use Bunny\Test\Exception\TimeoutException;
use PHPUnit\Framework\TestCase;

use React\EventLoop\Factory;

use function dirname;
use function file_exists;
use function getenv;
use function is_file;
use function putenv;

class TLSTest extends TestCase
{

    public function testConnect()
    {
        $options = $this->getOptions();

        $client = new Client($options);
        $client->connect();
        $client->disconnect();

        $this->assertTrue(true);
    }

    public function testConnectAsync() {
        $options = $this->getOptions();
        $loop = Factory::create();

        $loop->addTimer(5, function () {
            throw new TimeoutException();
        });

        $client = new AsyncClient($loop, $options);
        $client->connect()->then(function (AsyncClient $client) {
            return $client->disconnect();
        })->then(function () use ($loop) {
            $loop->stop();
        })->done();

        $loop->run();

        $this->assertTrue(true);
    }

    public function testConnectWithMissingClientCert()
    {
        $options = $this->getOptions();
        if (!isset($options['tls']['local_cert'])) {
            $this->markTestSkipped('No client certificate is used');
        }

        // let's try without client certificate - it should fail
        unset($options['tls']['local_cert'], $options['tls']['local_pk']);

        $this->expectException(ClientException::class);

        $client = new Client($options);
        $client->connect();
        $client->disconnect();
    }

    public function testConnectToTcpPort()
    {
        $options = $this->getOptions();
        unset($options['port']);

        $this->expectException(ClientException::class);

        $client = new Client($options);
        $client->connect();
        $client->disconnect();
    }

    public function testConnectWithWrongPeerName()
    {
        putenv('TLS_PEER_NAME=not-existsing-peer-name' . time());
        $options = $this->getOptions();

        $this->expectException(ClientException::class);

        $client = new Client($options);
        $client->connect();
        $client->disconnect();
    }

    protected function getOptions()
    {
        // should we do TLS-tests
        if (empty(getenv('TLS_TEST'))) {
            $this->markTestSkipped('Skipped due empty ENV-variable "TLS_TEST"');
        }

        // checking CA-file
        $caFile = getenv('TLS_CA');
        if (empty($caFile)) {
            $this->fail('Missing CA file ENV-variable: "TLS_CA"');
        }
        $testsDir = dirname(__DIR__);
        $caFile   = $testsDir . '/' . $caFile;
        if (!file_exists($caFile) || !is_file($caFile)) {
            $this->fail('Missing CA file: "' . $caFile . '"');
        }

        $peerName = getenv('TLS_PEER_NAME');
        if (empty($peerName)) {
            // setting default value from tests/tls/Makefile
            $peerName = 'server.rmq';
        }

        // minimal TLS-options
        $options = [
            'port' => 5673,
            'tls'  => [
                // for tests we are using self-signed certificates
                'allow_self_signed' => true,
                'cafile'            => $caFile,
                'peer_name'         => $peerName,
            ],
        ];


        $certFile = getenv('TLS_CLIENT_CERT');
        $keyFile  = getenv('TLS_CLIENT_KEY');
        if (!empty($certFile) && !empty($keyFile)) {
            $certFile = $testsDir . '/' . $certFile;
            $keyFile  = $testsDir . '/' . $keyFile;
            if (!file_exists($certFile) || !is_file($certFile)) {
                $this->fail('Missing certificate file: "' . $certFile . '"');
            }
            if (!file_exists($keyFile) || !is_file($keyFile)) {
                $this->fail('Missing key file: "' . $keyFile . '"');
            }
            $options['tls']['local_cert'] = $certFile;
            $options['tls']['local_pk']   = $keyFile;
        }
        return $options;
    }
}
