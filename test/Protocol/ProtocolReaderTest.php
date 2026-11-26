<?php

declare(strict_types=1);

namespace Bunny\Test\Protocol;

use Bunny\Constants;
use Bunny\Protocol\Buffer;
use Bunny\Protocol\ProtocolReader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use function chr;
use function pack;
use function strlen;

final class ProtocolReaderTest extends TestCase
{
    #[DataProvider('providerConsumeFieldValueCanHandleBytes')]
    public function testConsumeFieldValueCanHandleBytes(string $value): void
    {
        $buffer = new Buffer(chr(Constants::FIELD_BYTES) . pack('N', strlen($value)) . $value . 'tail');

        self::assertSame($value, (new ProtocolReader())->consumeFieldValue($buffer));
        self::assertSame('tail', $buffer->consume($buffer->getLength()));
    }

    /**
     * @return iterable<string, array<string, mixed>>
     */
    public static function providerConsumeFieldValueCanHandleBytes(): iterable
    {
        yield 'empty' => ['value' => ''];

        yield 'binary' => ['value' => "\x00\xff\x01x\x00"];
    }
}
