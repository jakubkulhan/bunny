<?php

namespace Bunny;

use Bunny\Async\Client as AsyncClient;
use Bunny\Exception\ClientException;
use Bunny\Test\Exception\TimeoutException;
use Bunny\Test\Library\AsynchronousClientHelper;
use Bunny\Test\Library\SynchronousClientHelper;
use PHPUnit\Framework\TestCase;

use React\EventLoop\Factory;

use function dirname;
use function file_exists;
use function getenv;
use function is_file;
use function putenv;

class SSLTest extends TestCase
{
    /**
     * @var SynchronousClientHelper
     */
    private $helper;

    /**
     * @var AsynchronousClientHelper
     */
    private $asyncHelper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->helper = new SynchronousClientHelper();
        $this->asyncHelper = new AsynchronousClientHelper();
    }

    public function testConnect()
    {
        $options = $this->getOptions();

        $client = $this->helper->createClient($options);
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

        $client = $this->asyncHelper->createClient($loop, $options);
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
        if (!isset($options['ssl']['local_cert'])) {
            $this->markTestSkipped('No client certificate is used');
        }

        // let's try without client certificate - it should fail
        unset($options['ssl']['local_cert'], $options['ssl']['local_pk']);

        $this->expectException(ClientException::class);

        $client = $this->helper->createClient($options);
        $client->connect();
        $client->disconnect();
    }

    public function testConnectToTcpPort()
    {
        $options = $this->getOptions();
        unset($options['port']);

        $this->expectException(ClientException::class);

        $client = $this->helper->createClient($options);
        $client->connect();
        $client->disconnect();
    }

    public function testConnectWithWrongPeerName()
    {
        putenv('SSL_PEER_NAME=not-existsing-peer-name' . time());
        $options = $this->getOptions();

        $this->expectException(ClientException::class);

        $client = $this->helper->createClient($options);
        $client->connect();
        $client->disconnect();
    }

    protected function getOptions()
    {
        // should we do SSL-tests
        if (empty(getenv('SSL_TEST')) || getenv('SSL_TEST') !== 'yes') {
            $this->markTestSkipped('Skipped due empty ENV-variable "SSL_TEST"');
        }

        // checking CA-file
        $caFile = getenv('SSL_CA');
        if (empty($caFile)) {
            $this->fail('Missing CA file ENV-variable: "SSL_CA"');
        }
        $testsDir = dirname(__DIR__);
        $caFile   = $testsDir . '/' . $caFile;
        if (!file_exists($caFile) || !is_file($caFile)) {
            $this->fail('Missing CA file: "' . $caFile . '"');
        }

        $peerName = getenv('SSL_PEER_NAME');
        if (empty($peerName)) {
            // setting default value from tests/ssl/Makefile
            $peerName = 'server.rmq';
        }

        // minimal SSL-options
        $options = [
            'port' => 5673,
            'ssl'  => [
                // for tests we are using self-signed certificates
                'allow_self_signed' => true,
                'cafile'            => $caFile,
                'peer_name'         => $peerName,
            ],
        ];


        $certFile = getenv('SSL_CLIENT_CERT');
        $keyFile  = getenv('SSL_CLIENT_KEY');
        if (!empty($certFile) && !empty($keyFile)) {
            $certFile = $testsDir . '/' . $certFile;
            $keyFile  = $testsDir . '/' . $keyFile;
            if (!file_exists($certFile) || !is_file($certFile)) {
                $this->fail('Missing certificate file: "' . $certFile . '"');
            }
            if (!file_exists($keyFile) || !is_file($keyFile)) {
                $this->fail('Missing key file: "' . $keyFile . '"');
            }
            $options['ssl']['local_cert'] = $certFile;
            $options['ssl']['local_pk']   = $keyFile;
        }
        return $options;
    }
}
