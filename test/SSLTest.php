<?php

declare(strict_types=1);

namespace Bunny\Test;

use Bunny\Exception\ClientException;
use Bunny\Test\Library\ClientFactory;
use Bunny\Test\Library\Environment;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use function file_exists;
use function in_array;
use function is_file;
use function putenv;
use function time;

final class SSLTest extends TestCase
{
    /**
     * @return iterable<string, array<string>>
     */
    public static function provideKeys(): iterable
    {
        yield 'tls' => ['tls'];
        yield 'ssl' => ['ssl'];
    }

    #[DataProvider('provideKeys')]
    public function testConnect(string $key): void
    {
        $this->expectNotToPerformAssertions();

        $options = $this->getOptions($key);

        $client = ClientFactory::createClient($options);

        $client->connect();
        $client->disconnect();
    }

    #[DataProvider('provideKeys')]
    public function testConnectWithMissingClientCert(string $key): void
    {
        $options = $this->getOptions($key);
        if (!isset($options[$key]['local_cert'])) {
            $this->markTestSkipped('No client certificate is used');
        }

        // let's try without client certificate - it should fail
        unset($options[$key]['local_cert'], $options[$key]['local_pk']);

        if (Environment::getSslTest() === 'client') {
            $this->expectException(ClientException::class);
        }

        $client = ClientFactory::createClient($options);

        $client->connect();
        $client->disconnect();
    }

    #[DataProvider('provideKeys')]
    public function testConnectToTcpPort(string $key): void
    {
        $options = $this->getOptions($key);
        unset($options['port']);

        $this->expectException(ClientException::class);

        $client = ClientFactory::createClient($options);

        $client->connect();
        $client->disconnect();
    }

    #[DataProvider('provideKeys')]
    public function testConnectWithWrongPeerName(string $key): void
    {
        putenv('SSL_PEER_NAME=not-existsing-peer-name' . time());
        $options = $this->getOptions($key);

        $this->expectException(ClientException::class);

        $client = ClientFactory::createClient($options);

        $client->connect();
        $client->disconnect();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(string $key): array
    {
        // should we do SSL-tests
        if (!in_array(Environment::getSslTest(), ['yes', 'client'], true)) {
            $this->markTestSkipped('Skipped because env var SSL_TEST not set to "yes" or "client"');
        }

        // checking CA-file
        $caFile = Environment::getSslCa();

        $testsDir = __DIR__;
        $caFile   = $testsDir . '/' . $caFile;
        if (!file_exists($caFile) || !is_file($caFile)) {
            $this->fail('Missing CA file: "' . $caFile . '"');
        }

        $peerName = Environment::getSslPeerName();

        /**
         * minimal SSL-options
         *
         * @var array{allow_self_signed: true, cafile: non-falsy-string, peer_name: string}|array{allow_self_signed: true, cafile: non-falsy-string, peer_name: string, local_cert: string, local_pk: string} $options
         */
        $options = [
            // for tests we are using self-signed certificates
            'allow_self_signed' => true,
            'cafile'            => $caFile,
            'peer_name'         => $peerName,
        ];

        $certFile = Environment::getSslClientCert();
        $keyFile  = Environment::getSslClientKey();

        if (!empty($certFile) && !empty($keyFile)) {
            $certFile = $testsDir . '/' . $certFile;
            $keyFile  = $testsDir . '/' . $keyFile;
            if (!file_exists($certFile) || !is_file($certFile)) {
                $this->fail('Missing certificate file: "' . $certFile . '"');
            }

            if (!file_exists($keyFile) || !is_file($keyFile)) {
                $this->fail('Missing key file: "' . $keyFile . '"');
            }

            $options['local_cert'] = $certFile;
            $options['local_pk']   = $keyFile;
        }

        return [
            'port' => 5673,
            $key  => $options,
        ];
    }
}
