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
use const PHP_INT_MIN;

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

    #[DataProvider('providerConsumeTableReadsShortIntAsSigned')]
    public function testConsumeTableReadsShortIntAsSigned(int $value): void
    {
        $fields = "\x05shorts" . pack('n', $value & 0xFFFF) . "\x04nextS" . pack('N', 2) . 'ok';
        $buffer = new Buffer(pack('N', strlen($fields)) . $fields);

        self::assertSame(['short' => $value, 'next' => 'ok'], (new ProtocolReader())->consumeTable($buffer));
    }

    /**
     * @return iterable<string, array<string, mixed>>
     */
    public static function providerConsumeTableReadsShortIntAsSigned(): iterable
    {
        yield 'positive' => ['value' => 42];

        yield 'negative' => ['value' => -2];

        yield 'minimum' => ['value' => -32768];
    }

    #[DataProvider('providerConsumeFieldValueReadsLongLongIntAsSigned')]
    public function testConsumeFieldValueReadsLongLongIntAsSigned(int $value): void
    {
        $buffer = new Buffer('l' . pack('J', $value));

        self::assertSame($value, (new ProtocolReader())->consumeFieldValue($buffer));
    }

    /**
     * @return iterable<string, array<string, mixed>>
     */
    public static function providerConsumeFieldValueReadsLongLongIntAsSigned(): iterable
    {
        yield 'positive' => ['value' => 42];

        yield 'negative' => ['value' => -5];

        yield 'minimum' => ['value' => PHP_INT_MIN];
    }
}
