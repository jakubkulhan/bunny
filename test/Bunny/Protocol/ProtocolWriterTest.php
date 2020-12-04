<?php
namespace Bunny\Protocol;

use Bunny\Constants;
use DateTime;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class ProtocolWriterTest extends TestCase
{
    public function test_appendFieldValue_canHandleDateTime()
    {
        $buffer = $this->createMock(Buffer::class);
        $protocolWriter = new ProtocolWriter();

        $date = new DateTime();

        $buffer->expects($this->once())
            ->method('appendUint8')
            ->with(Constants::FIELD_TIMESTAMP);
        $buffer->expects($this->once())
            ->method('appendUint64')
            ->with($date->getTimestamp());

        $protocolWriter->appendFieldValue($date, $buffer);
    }

    public function test_appendFieldValue_canHandleDateTimeImmutable()
    {
        $buffer = $this->createMock(Buffer::class);
        $protocolWriter = new ProtocolWriter();

        $date = new DateTimeImmutable();

        $buffer->expects($this->once())
            ->method('appendUint8')
            ->with(Constants::FIELD_TIMESTAMP);
        $buffer->expects($this->once())
            ->method('appendUint64')
            ->with($date->getTimestamp());

        $protocolWriter->appendFieldValue($date, $buffer);
    }
}
