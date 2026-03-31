<?php

declare(strict_types=1);

namespace Bunny\Protocol;

use Bunny\Exception\BufferUnderflowException;
use function pack;
use function strlen;
use function substr;
use function unpack;
use const PHP_INT_SIZE;

/**
 * Binary buffer implementation.
 *
 * Acts as queue:
 *
 * - read*() methods peeks from start.
 * - consume*() methods pops data from start.
 * - append*() methods add data to end.
 *
 * All integers are read from and written to buffer in big-endian order.
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class Buffer
{
    private static ?bool $isLittleEndian = null;

    private static ?bool $native64BitPack = null;

    private int $length;

    public function __construct(private string $buffer = '')
    {
        $this->length = strlen($this->buffer);
        self::isLittleEndian();

        if (self::$native64BitPack === null) {
            self::$native64BitPack = PHP_INT_SIZE === 8;
        }
    }

    /**
     * Checks if machine is little-endian.
     *
     * AMQP (as a network protocol) is big-endian.
     */
    public static function isLittleEndian(): bool
    {
        if (self::$isLittleEndian === null) {
            self::$isLittleEndian = unpack('S', "\x01\x00")[1] === 1;
        }

        return self::$isLittleEndian;
    }

    /**
     * Swaps 16-bit integer endianness.
     */
    public static function swapEndian16(string $s): string
    {
        return $s[1] . $s[0];
    }

    /**
     * Swaps 32-bit integer endianness.
     */
    public static function swapEndian32(string $s): string
    {
        return $s[3] . $s[2] . $s[1] . $s[0];
    }

    /**
     * Swaps 64-bit integer endianness.
     */
    public static function swapEndian64(string $s): string
    {
        return $s[7] . $s[6] . $s[5] . $s[4] . $s[3] . $s[2] . $s[1] . $s[0];
    }

    /**
     * Swaps 64-bit integer endianness so integer can be read/written as two 32-bit integers.
     */
    public static function swapHalvedEndian64(string $s): string
    {
        return $s[3] . $s[2] . $s[1] . $s[0] . $s[7] . $s[6] . $s[5] . $s[4];
    }

    /**
     * Returns number of bytes in buffer.
     */
    public function getLength(): int
    {
        return $this->length;
    }

    /**
     * Returns true if buffer is empty.
     */
    public function isEmpty(): bool
    {
        return $this->length === 0;
    }

    /**
     * Reads first $n bytes from $offset.
     */
    public function read(int $n, int $offset = 0): string
    {
        if ($this->length < $offset + $n) {
            throw new BufferUnderflowException();
        }

        if ($offset === 0 && $this->length === $offset + $n) {
            return $this->buffer;
        }

        return substr($this->buffer, $offset, $n);
    }

    /**
     * Reads first $n bytes from buffer and discards them.
     */
    public function consume(int $n): string
    {
        if ($this->length < $n) {
            throw new BufferUnderflowException();
        }

        if ($this->length === $n) {
            $buffer = $this->buffer;
            $this->buffer = '';
            $this->length = 0;

            return $buffer;
        }

        $buffer = substr($this->buffer, 0, $n);
        $this->buffer = substr($this->buffer, $n);
        $this->length -= $n;

        return $buffer;
    }

    /**
     * Discards first $n bytes from buffer.
     */
    public function discard(int $n): self
    {
        if ($this->length < $n) {
            throw new BufferUnderflowException();
        }

        if ($this->length === $n) {
            $this->buffer = '';
            $this->length = 0;

            return $this;
        }

        $this->buffer = substr($this->buffer, $n);
        $this->length -= $n;

        return $this;
    }

    /**
     * Returns new buffer with first $n bytes.
     */
    public function slice(int $n): Buffer
    {
        if ($this->length < $n) {
            throw new BufferUnderflowException();
        }

        if ($this->length === $n) {
            return new Buffer($this->buffer);
        }

        return new Buffer(substr($this->buffer, 0, $n));
    }

    /**
     * Returns new buffer with first $n bytes and discards them from current buffer.
     */
    public function consumeSlice(int $n): Buffer
    {
        if ($this->length < $n) {
            throw new BufferUnderflowException();
        }

        if ($this->length === $n) {
            $buffer = $this->buffer;
            $this->buffer = '';
            $this->length = 0;

            return new Buffer($buffer);
        }

        $buffer = substr($this->buffer, 0, $n);
        $this->buffer = substr($this->buffer, $n);
        $this->length -= $n;

        return new Buffer($buffer);
    }

    /**
     * Appends bytes at the end of the buffer.
     */
    public function append(Buffer|string $s): self
    {
        if ($s instanceof Buffer) {
            $s = $s->buffer;
        }

        $this->buffer .= $s;
        $this->length = strlen($this->buffer);

        return $this;
    }

    /**
     * Reads unsigned 8-bit integer from buffer.
     *
     * @return int<0, 255>
     */
    public function readUint8(int $offset = 0): int
    {
        [, $ret] = unpack('C', $this->read(1, $offset));

        return $ret;
    }

    /**
     * Reads signed 8-bit integer from buffer.
     *
     * @return int<-128, 127>
     */
    public function readInt8(int $offset = 0): int
    {
        [, $ret] = unpack('c', $this->read(1, $offset));

        return $ret;
    }

    /**
     * Reads and discards unsigned 8-bit integer from buffer.
     *
     * @return int<0, 255>
     */
    public function consumeUint8(): int
    {
        [, $ret] = unpack('C', $this->buffer);
        $this->discard(1);

        return $ret;
    }

    /**
     * Reads and discards signed 8-bit integer from buffer.
     *
     * @return int<-128, 127>
     */
    public function consumeInt8(): mixed
    {
        [, $ret] = unpack('c', $this->consume(1));

        return $ret;
    }

    /**
     * Appends unsigned 8-bit integer to buffer.
     *
     * @return \Bunny\Protocol\Buffer
     */
    public function appendUint8(int $value): self
    {
        return $this->append(pack('C', $value));
    }

    /**
     * Appends signed 8-bit integer to buffer.
     *
     * @return \Bunny\Protocol\Buffer
     */
    public function appendInt8(int $value): self
    {
        return $this->append(pack('c', $value));
    }

    /**
     * Reads unsigned 16-bit integer from buffer.
     *
     * @return int<0, 65535>
     */
    public function readUint16(int $offset = 0): int
    {
        $s = $this->read(2, $offset);
        [, $ret] = unpack('n', $s);

        return $ret;
    }

    /**
     * Reads signed 16-bit integer from buffer.
     *
     * @return int<-32768, 32767>
     */
    public function readInt16(int $offset = 0): int
    {
        $s = $this->read(2, $offset);
        [, $ret] = unpack('s', self::$isLittleEndian ? self::swapEndian16($s) : $s);

        return $ret;
    }

    /**
     * Reads and discards unsigned 16-bit integer from buffer.
     *
     * @return int<0, 65535>
     */
    public function consumeUint16(): int
    {
        [, $ret] = unpack('n', $this->buffer);
        $this->discard(2);

        return $ret;
    }

    /**
     * Reads and discards signed 16-bit integer from buffer.
     *
     * @return int<-32768, 32767>
     */
    public function consumeInt16(): int
    {
        $s = $this->consume(2);
        [, $ret] = unpack('s', self::$isLittleEndian ? self::swapEndian16($s) : $s);

        return $ret;
    }

    /**
     * Appends unsigned 16-bit integer to buffer.
     *
     * @return \Bunny\Protocol\Buffer
     */
    public function appendUint16(int $value): self
    {
        $s = pack('n', $value);

        return $this->append($s);
    }

    /**
     * Appends signed 16-bit integer to buffer.
     *
     * @return \Bunny\Protocol\Buffer
     */
    public function appendInt16(int $value): self
    {
        $s = pack('s', $value);

        return $this->append(self::$isLittleEndian ? self::swapEndian16($s) : $s);
    }

    /**
     * Reads unsigned 32-bit integer from buffer.
     *
     * @return int<0, 4294967295>
     */
    public function readUint32(int $offset = 0): int
    {
        $s = $this->read(4, $offset);
        [, $ret] = unpack('N', $s);

        return $ret;
    }

    /**
     * Reads signed 32-bit integer from buffer.
     *
     * @return int<-2147483648, 2147483647>
     */
    public function readInt32(int $offset = 0): int
    {
        $s = $this->read(4, $offset);
        [, $ret] = unpack('l', self::$isLittleEndian ? self::swapEndian32($s) : $s);

        return $ret;
    }

    /**
     * Reads and discards unsigned 32-bit integer from buffer.
     *
     * @return int<0, 4294967295>
     */
    public function consumeUint32(): int
    {
        [, $ret] = unpack('N', $this->buffer);
        $this->discard(4);

        return $ret;
    }

    /**
     * Reads and discards signed 32-bit integer from buffer.
     *
     * @return int<-2147483648, 2147483647>
     */
    public function consumeInt32(): int
    {
        $s = $this->consume(4);
        [, $ret] = unpack('l', self::$isLittleEndian ? self::swapEndian32($s) : $s);

        return $ret;
    }

    /**
     * Appends unsigned 32-bit integer to buffer.
     *
     * @return \Bunny\Protocol\Buffer
     */
    public function appendUint32(int $value): self
    {
        $s = pack('N', $value);

        return $this->append($s);
    }

    /**
     * Appends signed 32-bit integer to buffer.
     *
     * @return \Bunny\Protocol\Buffer
     */
    public function appendInt32(int $value): self
    {
        $s = pack('l', $value);

        return $this->append(self::$isLittleEndian ? self::swapEndian32($s) : $s);
    }

    /**
     * Reads unsigned 64-bit integer from buffer.
     */
    public function readUint64(int $offset = 0): int
    {
        $s = $this->read(8, $offset);
        if (self::$native64BitPack) {
            [, $ret] = unpack('Q', self::$isLittleEndian ? self::swapEndian64($s) : $s);
        } else {
            $d = unpack('Lh/Ll', self::$isLittleEndian ? self::swapHalvedEndian64($s) : $s);
            $ret = $d['h'] << 32 | $d['l'];
        }

        return $ret;
    }

    /**
     * Reads signed 64-bit integer from buffer.
     */
    public function readInt64(int $offset = 0): int
    {
        $s = $this->read(8, $offset);
        if (self::$native64BitPack) {
            [, $ret] = unpack('q', self::$isLittleEndian ? self::swapEndian64($s) : $s);
        } else {
            $d = unpack('Lh/Ll', self::$isLittleEndian ? self::swapHalvedEndian64($s) : $s);
            $ret = $d['h'] << 32 | $d['l'];
        }

        return $ret;
    }

    /**
     * Reads and discards unsigned 64-bit integer from buffer.
     */
    public function consumeUint64(): int
    {
        $s = $this->consume(8);
        if (self::$native64BitPack) {
            [, $ret] = unpack('Q', self::$isLittleEndian ? self::swapEndian64($s) : $s);
        } else {
            $d = unpack('Lh/Ll', self::$isLittleEndian ? self::swapHalvedEndian64($s) : $s);
            $ret = $d['h'] << 32 | $d['l'];
        }

        return $ret;
    }

    /**
     * Reads and discards signed 64-bit integer from buffer.
     */
    public function consumeInt64(): int
    {
        $s = $this->consume(8);
        if (self::$native64BitPack) {
            [, $ret] = unpack('q', self::$isLittleEndian ? self::swapEndian64($s) : $s);
        } else {
            $d = unpack('Lh/Ll', self::$isLittleEndian ? self::swapHalvedEndian64($s) : $s);
            $ret = $d['h'] << 32 | $d['l'];
        }

        return $ret;
    }

    /**
     * Appends unsigned 64-bit integer to buffer.
     *
     * @return \Bunny\Protocol\Buffer
     */
    public function appendUint64(int $value): self
    {
        if (self::$native64BitPack) {
            $s = pack('Q', $value);
            if (self::$isLittleEndian) {
                $s = self::swapEndian64($s);
            }
        } else {
            $s = pack('LL', ($value & 0xffffffff00000000) >> 32, $value & 0x00000000ffffffff);
            if (self::$isLittleEndian) {
                $s = self::swapHalvedEndian64($s);
            }
        }

        return $this->append($s);
    }

    /**
     * Appends signed 64-bit integer to buffer.
     *
     * @return \Bunny\Protocol\Buffer
     */
    public function appendInt64(int $value): self
    {
        if (self::$native64BitPack) {
            $s = pack('q', $value);
            if (self::$isLittleEndian) {
                $s = self::swapEndian64($s);
            }
        } else {
            $s = pack('LL', ($value & 0xffffffff00000000) >> 32, $value & 0x00000000ffffffff);
            if (self::$isLittleEndian) {
                $s = self::swapHalvedEndian64($s);
            }
        }

        return $this->append($s);
    }

    /**
     * Reads float from buffer.
     */
    public function readFloat(int $offset = 0): float
    {
        $s = $this->read(4, $offset);
        [, $ret] = unpack('f', self::$isLittleEndian ? self::swapEndian32($s) : $s);

        return $ret;
    }

    /**
     * Reads and discards float from buffer.
     */
    public function consumeFloat(): float
    {
        $s = $this->consume(4);
        [, $ret] = unpack('f', self::$isLittleEndian ? self::swapEndian32($s) : $s);

        return $ret;
    }

    /**
     * Appends float to buffer.
     *
     * @return \Bunny\Protocol\Buffer
     */
    public function appendFloat(float $value): self
    {
        $s = pack('f', $value);

        return $this->append(self::$isLittleEndian ? self::swapEndian32($s) : $s);
    }

    /**
     * Reads double from buffer.
     */
    public function readDouble(int $offset = 0): float
    {
        $s = $this->read(8, $offset);
        [, $ret] = unpack('d', self::$isLittleEndian ? self::swapEndian64($s) : $s);

        return $ret;
    }

    /**
     * Reads and discards double from buffer.
     */
    public function consumeDouble(): float
    {
        $s = $this->consume(8);
        [, $ret] = unpack('d', self::$isLittleEndian ? self::swapEndian64($s) : $s);

        return $ret;
    }

    /**
     * Appends double to buffer.
     *
     * @return \Bunny\Protocol\Buffer
     */
    public function appendDouble(float $value): self
    {
        $s = pack('d', $value);

        return $this->append(self::$isLittleEndian ? self::swapEndian64($s) : $s);
    }
}
