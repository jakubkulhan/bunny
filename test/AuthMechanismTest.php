<?php

declare(strict_types=1);

namespace Bunny\Test;

use Bunny\AuthMechanism;
use Bunny\Exception\ClientException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AuthMechanismTest extends TestCase
{
    #[DataProvider('providerNegotiate')]
    public function testNegotiate(string $offered, AuthMechanism $expected): void
    {
        self::assertSame($expected, AuthMechanism::negotiate($offered));
    }

    /**
     * @return iterable<string, array<string, mixed>>
     */
    public static function providerNegotiate(): iterable
    {
        yield 'both' => ['offered' => 'PLAIN AMQPLAIN', 'expected' => AuthMechanism::AmqPlain];

        yield 'amqplain only' => ['offered' => 'AMQPLAIN', 'expected' => AuthMechanism::AmqPlain];

        yield 'plain only' => ['offered' => 'PLAIN', 'expected' => AuthMechanism::Plain];

        yield 'plain among others' => ['offered' => 'EXTERNAL PLAIN', 'expected' => AuthMechanism::Plain];
    }

    #[DataProvider('providerNegotiateRejectsUnsupported')]
    public function testNegotiateRejectsUnsupported(string $offered): void
    {
        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('Server supports neither AMQPLAIN nor PLAIN mechanism (supported: ' . $offered . ').');

        AuthMechanism::negotiate($offered);
    }

    /**
     * @return iterable<string, array<string, mixed>>
     */
    public static function providerNegotiateRejectsUnsupported(): iterable
    {
        yield 'other mechanisms' => ['offered' => 'EXTERNAL ANONYMOUS'];

        yield 'plain as a substring' => ['offered' => 'XPLAIN'];

        yield 'none' => ['offered' => ''];
    }

    #[DataProvider('providerResponse')]
    public function testResponse(AuthMechanism $mechanism, string $expected): void
    {
        self::assertSame($expected, $mechanism->response('user', 'pass'));
    }

    /**
     * @return iterable<string, array<string, mixed>>
     */
    public static function providerResponse(): iterable
    {
        yield 'amqplain' => [
            'mechanism' => AuthMechanism::AmqPlain,
            'expected' => "\x05LOGINS\x00\x00\x00\x04user\x08PASSWORDS\x00\x00\x00\x04pass",
        ];

        yield 'plain' => ['mechanism' => AuthMechanism::Plain, 'expected' => "\x00user\x00pass"];
    }
}
