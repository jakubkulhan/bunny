<?php

declare(strict_types=1);

namespace Bunny\Test\Protocol;

use Bunny\Constants;
use Bunny\Protocol\Buffer;
use Bunny\Protocol\ProtocolWriter;
use DateTime;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProtocolWriterTest extends TestCase
{
    public function testAppendFieldValueCanHandleDateTime(): void
    {
        $protocolWriter = new ProtocolWriter();

        $buffer = $this->createMock(Buffer::class);

        $date = new DateTime();
        $buffer->expects($this->once())->method('appendUint8')->with(Constants::FIELD_TIMESTAMP);
        $buffer->expects($this->once())->method('appendUint64')->with($date->getTimestamp());

        $protocolWriter->appendFieldValue($date, $buffer);
    }

    public function testAppendFieldValueCanHandleDateTimeImmutable(): void
    {
        $protocolWriter = new ProtocolWriter();

        $buffer = $this->createMock(Buffer::class);

        $date = new DateTimeImmutable();
        $buffer->expects($this->once())->method('appendUint8')->with(Constants::FIELD_TIMESTAMP);
        $buffer->expects($this->once())->method('appendUint64')->with($date->getTimestamp());

        $protocolWriter->appendFieldValue($date, $buffer);
    }

    #[DataProvider('providerAppendFieldValueCanHandleInt64')]
    public function testAppendFieldValueCanHandleInt64(int $value, bool $expectedInt64): void
    {
        $protocolWriter = new ProtocolWriter();

        $buffer = $this->createMock(Buffer::class);

        if ($expectedInt64) {
            $buffer->expects($this->once())->method('appendUint8')->with(Constants::FIELD_LONG_LONG_INT);
            $buffer->expects($this->once())->method('appendInt64')->with($value);
        } else {
            $buffer->expects($this->once())->method('appendUint8')->with(Constants::FIELD_LONG_INT);
            $buffer->expects($this->once())->method('appendInt32')->with($value);
        }

        $protocolWriter->appendFieldValue($value, $buffer);
    }

    /**
     * @return iterable<array<string,mixed>>
     */
    public static function providerAppendFieldValueCanHandleInt64(): iterable
    {
        yield [
            'value' => 42,
            'expectedInt64' => true,
        ];

        yield [
            'value' => 2_157_483_647,
            'expectedInt64' => true,
        ];
    }
}
