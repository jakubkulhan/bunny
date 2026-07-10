<?php

declare(strict_types=1);

namespace Bunny\Test\Protocol;

use Bunny\Exception\BufferUnderflowException;
use Bunny\Protocol\Buffer;
use PHPUnit\Framework\TestCase;

final class BufferTest extends TestCase
{
    public function testGetLength(): void
    {
        $buffer = new Buffer();
        self::assertEquals(0, $buffer->getLength());

        $buffer->append('a');
        self::assertEquals(1, $buffer->getLength());

        $buffer->append('a');
        self::assertEquals(2, $buffer->getLength());

        $buffer->read(1);
        self::assertEquals(2, $buffer->getLength());

        $buffer->read(2);
        self::assertEquals(2, $buffer->getLength());

        $buffer->consume(1);
        self::assertEquals(1, $buffer->getLength());

        $buffer->consume(1);
        self::assertEquals(0, $buffer->getLength());
    }

    public function testIsEmpty(): void
    {
        $buffer = new Buffer();
        self::assertTrue($buffer->isEmpty());

        $buffer->append('a');
        self::assertFalse($buffer->isEmpty());

        $buffer2 = new Buffer('a');
        self::assertFalse($buffer2->isEmpty());
    }

    public function testRead(): void
    {
        $buffer = new Buffer('abcd');

        self::assertEquals('a', $buffer->read(1));
        self::assertEquals(4, $buffer->getLength());

        self::assertEquals('ab', $buffer->read(2));
        self::assertEquals(4, $buffer->getLength());

        self::assertEquals('abc', $buffer->read(3));
        self::assertEquals(4, $buffer->getLength());

        self::assertEquals('abcd', $buffer->read(4));
        self::assertEquals(4, $buffer->getLength());
    }

    public function testReadOffset(): void
    {
        $buffer = new Buffer('abcd');

        self::assertEquals('a', $buffer->read(1, 0));
        self::assertEquals(4, $buffer->getLength());

        self::assertEquals('b', $buffer->read(1, 1));
        self::assertEquals(4, $buffer->getLength());

        self::assertEquals('c', $buffer->read(1, 2));
        self::assertEquals(4, $buffer->getLength());

        self::assertEquals('d', $buffer->read(1, 3));
        self::assertEquals(4, $buffer->getLength());
    }

    public function testReadThrows(): void
    {
        $this->expectException(BufferUnderflowException::class);

        $buffer = new Buffer();
        $buffer->read(1);
    }

    public function testConsume(): void
    {
        $buffer = new Buffer('abcd');

        self::assertEquals('a', $buffer->consume(1));
        self::assertEquals(3, $buffer->getLength());

        self::assertEquals('bc', $buffer->consume(2));
        self::assertEquals(1, $buffer->getLength());

        self::assertEquals('d', $buffer->consume(1));
        self::assertEquals(0, $buffer->getLength());
    }

    public function testConsumeThrows(): void
    {
        $this->expectException(BufferUnderflowException::class);

        $buffer = new Buffer();
        $buffer->consume(1);
    }

    public function testDiscard(): void
    {
        $buffer = new Buffer('abcd');

        $buffer->discard(1);
        self::assertEquals('bcd', $buffer->read($buffer->getLength()));
        self::assertEquals(3, $buffer->getLength());

        $buffer->discard(2);
        self::assertEquals('d', $buffer->read($buffer->getLength()));
        self::assertEquals(1, $buffer->getLength());

        $buffer->discard(1);
        self::assertEquals(0, $buffer->getLength());
        self::assertTrue($buffer->isEmpty());
    }

    public function testDiscardThrows(): void
    {
        $this->expectException(BufferUnderflowException::class);

        $buffer = new Buffer();
        $buffer->discard(1);
    }

    public function testSlice(): void
    {
        $buffer = new Buffer('abcd');

        $slice1 = $buffer->slice(1);
        self::assertEquals('a', $slice1->read($slice1->getLength()));
        self::assertEquals(4, $buffer->getLength());

        $slice2 = $buffer->slice(2);
        self::assertEquals('ab', $slice2->read($slice2->getLength()));
        self::assertEquals(4, $buffer->getLength());

        $slice3 = $buffer->slice(3);
        self::assertEquals('abc', $slice3->read($slice3->getLength()));
        self::assertEquals(4, $buffer->getLength());

        $slice4 = $buffer->slice(4);
        self::assertEquals('abcd', $slice4->read($slice4->getLength()));
        self::assertEquals(4, $buffer->getLength());
    }

    public function testSliceThrows(): void
    {
        $this->expectException(BufferUnderflowException::class);

        $buffer = new Buffer();
        $buffer->slice(1);
    }

    public function testConsumeSlice(): void
    {
        $buffer = new Buffer('abcdef');

        $slice1 = $buffer->consumeSlice(1);
        self::assertEquals('a', $slice1->read($slice1->getLength()));
        self::assertEquals(5, $buffer->getLength());

        $slice2 = $buffer->consumeSlice(2);
        self::assertEquals('bc', $slice2->read($slice2->getLength()));
        self::assertEquals(3, $buffer->getLength());

        $slice3 = $buffer->consumeSlice(3);
        self::assertEquals('def', $slice3->read($slice3->getLength()));
        self::assertEquals(0, $buffer->getLength());
    }

    public function testConsumeSliceThrows(): void
    {
        $this->expectException(BufferUnderflowException::class);

        $buffer = new Buffer();
        $buffer->consumeSlice(1);
    }

    public function testAppend(): void
    {
        $buffer = new Buffer();
        self::assertEquals(0, $buffer->getLength());

        $buffer->append('abcd');
        self::assertEquals(4, $buffer->getLength());
        self::assertEquals('abcd', $buffer->read(4));

        $buffer->append('efgh');
        self::assertEquals(8, $buffer->getLength());
        self::assertEquals('abcdefgh', $buffer->read(8));
    }

    public function testAppendBuffer(): void
    {
        $buffer = new Buffer();
        self::assertEquals(0, $buffer->getLength());

        $buffer->append(new Buffer('ab'));
        self::assertEquals(2, $buffer->getLength());
        self::assertEquals('ab', $buffer->read(2));

        $buffer->append('cd');
        self::assertEquals(4, $buffer->getLength());
        self::assertEquals('abcd', $buffer->read(4));

        $buffer->append(new Buffer('ef'));
        self::assertEquals(6, $buffer->getLength());
        self::assertEquals('abcdef', $buffer->read(6));
    }

    public function testReadUint8(): void
    {
        $buffer = new Buffer("\xA9");
        self::assertEquals(0xA9, $buffer->readUint8());
    }

    public function testReadInt8(): void
    {
        $buffer = new Buffer("\xA9");
        self::assertEquals(0xA9 - 0x100, $buffer->readInt8());
    }

    public function testConsumeUint8(): void
    {
        $buffer = new Buffer("\xA9");
        self::assertEquals(0xA9, $buffer->consumeUint8());
    }

    public function testConsumeInt8(): void
    {
        $buffer = new Buffer("\xA9");
        self::assertEquals(0xA9 - 0x100, $buffer->consumeInt8());
    }

    public function testAppendUint8(): void
    {
        $buffer = new Buffer();
        self::assertEquals("\xA9", $buffer->appendUint8(0xA9)->read(1));
    }

    public function testAppendInt8(): void
    {
        $buffer = new Buffer();
        self::assertEquals("\xA9", $buffer->appendInt8(0xA9 - 0x100)->read(1));
    }

    public function testReadUint16(): void
    {
        $buffer = new Buffer("\xA9\x78");
        self::assertEquals(0xA978, $buffer->readUint16());
    }

    public function testReadInt16(): void
    {
        $buffer = new Buffer("\xA9\x78");
        self::assertEquals(0xA978 - 0x10000, $buffer->readInt16());
    }

    public function testConsumeUint16(): void
    {
        $buffer = new Buffer("\xA9\x78");
        self::assertEquals(0xA978, $buffer->consumeUint16());
    }

    public function testConsumeInt16(): void
    {
        $buffer = new Buffer("\xA9\x78");
        self::assertEquals(0xA978 - 0x10000, $buffer->consumeInt16());
    }

    public function testAppendUint16(): void
    {
        $buffer = new Buffer();
        self::assertEquals("\xA9\x78", $buffer->appendUint16(0xA978)->read(2));
    }

    public function testAppendInt16(): void
    {
        $buffer = new Buffer();
        self::assertEquals("\xA9\x78", $buffer->appendInt16(0xA978)->read(2));
    }

    public function testReadUint32(): void
    {
        $buffer = new Buffer("\xA9\x78\x23\x61");
        self::assertEquals(0xA9782361, $buffer->readUint32());
    }

    public function testReadInt32(): void
    {
        $buffer = new Buffer("\xA9\x78\x23\x61");
        self::assertEquals(0xA9782361 - 0x100000000, $buffer->readInt32());
    }

    public function testConsumeUint32(): void
    {
        $buffer = new Buffer("\xA9\x78\x23\x61");
        self::assertEquals(0xA9782361, $buffer->consumeUint32());
    }

    public function testConsumeInt32(): void
    {
        $buffer = new Buffer("\xA9\x78\x23\x61");
        self::assertEquals(0xA9782361 - 0x100000000, $buffer->consumeInt32());
    }

    public function testAppendUint32(): void
    {
        $buffer = new Buffer();
        self::assertEquals("\xA9\x78\x23\x61", $buffer->appendUint32(0xA9782361)->read(4));
    }

    public function testAppendInt32(): void
    {
        $buffer = new Buffer();
        self::assertEquals("\xA9\x78\x23\x61", $buffer->appendInt32(0xA9782361)->read(4));
    }

    public function testReadUint64(): void
    {
        $buffer = new Buffer("\x19\x78\x23\x61\x34\x73\x85\x25");
        self::assertEquals(0x1978236134738525, $buffer->readUint64());
    }

    public function testReadInt64(): void
    {
        $buffer = new Buffer("\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFE");
        self::assertEquals(-2, $buffer->readInt64());
    }

    public function testConsumeUint64(): void
    {
        $buffer = new Buffer("\x19\x78\x23\x61\x34\x73\x85\x25");
        self::assertEquals(0x1978236134738525, $buffer->consumeUint64());
    }

    public function testConsumeInt64(): void
    {
        $buffer = new Buffer("\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFE");
        self::assertEquals(-2, $buffer->consumeInt64());
    }

    public function testAppendUint64(): void
    {
        $buffer = new Buffer();
        self::assertEquals("\x19\x78\x23\x61\x34\x73\x85\x25", $buffer->appendUint64(0x1978236134738525)->read(8));
    }

    public function testAppendInt64(): void
    {
        $buffer = new Buffer();
        self::assertEquals("\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFE", $buffer->appendInt64(-2)->read(8));
    }

    public function testReadFloat(): void
    {
        $buffer = new Buffer("\x3F\xC0\x00\x00");
        self::assertEquals(1.5, $buffer->readFloat());
    }

    public function testConsumeFloat(): void
    {
        $buffer = new Buffer("\x3F\xC0\x00\x00");
        self::assertEquals(1.5, $buffer->consumeFloat());
    }

    public function testAppendFloat(): void
    {
        $buffer = new Buffer();
        self::assertEquals("\x3F\xC0\x00\x00", $buffer->appendFloat(1.5)->read(4));
    }

    public function testReadDouble(): void
    {
        $buffer = new Buffer("\x3F\xF8\x00\x00\x00\x00\x00\x00");
        self::assertEquals(1.5, $buffer->readDouble());
    }

    public function testConsumeDouble(): void
    {
        $buffer = new Buffer("\x3F\xF8\x00\x00\x00\x00\x00\x00");
        self::assertEquals(1.5, $buffer->consumeDouble());
    }

    public function testAppendDouble(): void
    {
        $buffer = new Buffer();
        self::assertEquals("\x3F\xF8\x00\x00\x00\x00\x00\x00", $buffer->appendDouble(1.5)->read(8));
    }
}
