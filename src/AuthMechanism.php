<?php

declare(strict_types=1);

namespace Bunny;

use Bunny\Exception\ClientException;
use Bunny\Protocol\Buffer;
use Bunny\Protocol\ProtocolWriter;
use function explode;
use function in_array;
use function sprintf;

/**
 * @internal
 */
enum AuthMechanism: string
{
    case AmqPlain = 'AMQPLAIN';
    case Plain = 'PLAIN';

    public static function negotiate(string $offered): self
    {
        $mechanisms = explode(' ', $offered);

        foreach (self::cases() as $mechanism) {
            if (in_array($mechanism->value, $mechanisms, true)) {
                return $mechanism;
            }
        }

        throw new ClientException(sprintf('Server supports neither AMQPLAIN nor PLAIN mechanism (supported: %s).', $offered));
    }

    public function response(string $user, string $password): string
    {
        return match ($this) {
            self::AmqPlain => self::amqPlainResponse($user, $password),
            self::Plain => "\0" . $user . "\0" . $password,
        };
    }

    private static function amqPlainResponse(string $user, string $password): string
    {
        $buffer = new Buffer();
        (new ProtocolWriter())->appendTable([
            'LOGIN' => $user,
            'PASSWORD' => $password,
        ], $buffer);
        $buffer->discard(4);

        return $buffer->read($buffer->getLength());
    }
}
