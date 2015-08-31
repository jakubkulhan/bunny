<?php
namespace Bunny\Protocol;

use Bunny\Exception\BufferUnderflowException;

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

    /** @var boolean */
    private static $isLittleEndian;

    /** @var boolean */
    private static $native64BitPack;

    /** @var string */
    private $buffer;

    /** @var int */
    private $length;

    /**
     * Constructor.
     *
     * @param string $buffer
     */
    public function __construct($buffer = "")
    {
        $this->buffer = $buffer;
        $this->length = strlen($this->buffer);
        self::isLittleEndian();

        if (self::$native64BitPack === null) {
            if (!defined("PHP_VERSION_ID")) {
                $version = explode(".", PHP_VERSION);
                define("PHP_VERSION_ID", ($version[0] * 10000 + $version[1] * 100 + $version[2]));
            }

            self::$native64BitPack = PHP_VERSION_ID >= 50603 && PHP_INT_SIZE === 8;
        }
    }

    /**
     * Checks if machine is little-endian.
     *
     * AMQP (as a network protocol) is big-endian.
     *
     * @return boolean
     */
    public static function isLittleEndian()
    {
        if (self::$isLittleEndian === null) {
            self::$isLittleEndian = unpack("S", "\x01\x00")[1] === 1;
        }

        return self::$isLittleEndian;
    }

    /**
     * Swaps 16-bit integer endianness.
     *
     * @param string $s
     * @return string
     */
    public static function swapEndian16($s)
    {
        return $s[1] . $s[0];
    }

    /**
     * Swaps 32-bit integer endianness.
     *
     * @param string $s
     * @return string
     */
    public static function swapEndian32($s)
    {
        return $s[3] . $s[2] . $s[1] . $s[0];
    }

    /**
     * Swaps 64-bit integer endianness.
     *
     * @param string $s
     * @return string
     */
    public static function swapEndian64($s)
    {
        return $s[7] . $s[6] . $s[5] . $s[4] . $s[3] . $s[2] . $s[1] . $s[0];
    }

    /**
     * Swaps 64-bit integer endianness so integer can be read/written as two 32-bit integers.
     *
     * @param string $s
     * @return string
     */
    public static function swapHalvedEndian64($s)
    {
        return $s[3] . $s[2] . $s[1] . $s[0] . $s[7] . $s[6] . $s[5] . $s[4];
    }

    /**
     * Returns number of bytes in buffer.
     *
     * @return int
     */
    public function getLength()
    {
        return $this->length;
    }

    /**
     * Returns true if buffer is empty.
     *
     * @return boolean
     */
    public function isEmpty()
    {
        return $this->length === 0;
    }

    /**
     * Reads first $n bytes from $offset.
     *
     * @param int $n
     * @param int $offset
     * @return string
     */
    public function read($n, $offset = 0)
    {
        if ($this->length < $offset + $n) {
            throw new BufferUnderflowException();

        } elseif ($offset === 0 && $this->length === $offset + $n) {
            return $this->buffer;

        } else {
            return substr($this->buffer, $offset, $n);
        }
    }

    /**
     * Reads first $n bytes from buffer and discards them.
     *
     * @param int $n
     * @return string
     */
    public function consume($n)
    {
        if ($this->length < $n) {
            throw new BufferUnderflowException();

        } elseif ($this->length === $n) {
            $buffer = $this->buffer;
            $this->buffer = "";
            $this->length = 0;
            return $buffer;

        } else {
            $buffer = substr($this->buffer, 0, $n);
            $this->buffer = substr($this->buffer, $n);
            $this->length -= $n;
            return $buffer;
        }
    }

    /**
     * Discards first $n bytes from buffer.
     *
     * @param int $n
     * @return self
     */
    public function discard($n)
    {
        if ($this->length < $n) {
            throw new BufferUnderflowException();

        } elseif ($this->length === $n) {
            $this->buffer = "";
            $this->length = 0;
            return $this;

        } else {
            $this->buffer = substr($this->buffer, $n);
            $this->length -= $n;
            return $this;
        }
    }

    /**
     * Returns new buffer with first $n bytes.
     *
     * @param int $n
     * @return Buffer
     */
    public function slice($n)
    {
        if ($this->length < $n) {
            throw new BufferUnderflowException();

        } elseif ($this->length === $n) {
            return new Buffer($this->buffer);

        } else {
            return new Buffer(substr($this->buffer, 0, $n));
        }
    }

    /**
     * Returns new buffer with first $n bytes and discards them from current buffer.
     *
     * @param int $n
     * @return Buffer
     */
    public function consumeSlice($n)
    {
        if ($this->length < $n) {
            throw new BufferUnderflowException();

        } elseif ($this->length === $n) {
            $buffer = $this->buffer;
            $this->buffer = "";
            $this->length = 0;
            return new Buffer($buffer);

        } else {
            $buffer = substr($this->buffer, 0, $n);
            $this->buffer = substr($this->buffer, $n);
            $this->length -= $n;
            return new Buffer($buffer);
        }
    }

    /**
     * Appends bytes at the end of the buffer.
     *
     * @param string $s
     * @return self
     */
    public function append($s)
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
     * @param int $offset
     * @return int
     */
    public function readUint8($offset = 0)
    {
        list(, $ret) = unpack("C", $this->read(1, $offset));
        return $ret;
    }

    /**
     * Reads signed 8-bit integer from buffer.
     *
     * @param int $offset
     * @return int
     */
    public function readInt8($offset = 0)
    {
        list(, $ret) = unpack("c", $this->read(1, $offset));
        return $ret;
    }

    /**
     * Reads and discards unsigned 8-bit integer from buffer.
     *
     * @return int
     */
    public function consumeUint8()
    {
        list(, $ret) = unpack("C", $this->buffer);
        $this->discard(1);
        return $ret;
    }

    /**
     * Reads and discards signed 8-bit integer from buffer.
     *
     * @return mixed
     */
    public function consumeInt8()
    {
        list(, $ret) = unpack("c", $this->consume(1));
        return $ret;
    }

    /**
     * Appends unsigned 8-bit integer to buffer.
     *
     * @param int $value
     * @return Buffer
     */
    public function appendUint8($value)
    {
        return $this->append(pack("C", $value));
    }

    /**
     * Appends signed 8-bit integer to buffer.
     *
     * @param int $value
     * @return Buffer
     */
    public function appendInt8($value)
    {
        return $this->append(pack("c", $value));
    }

    /**
     * Reads unsigned 16-bit integer from buffer.
     *
     * @param int $offset
     * @return int
     */
    public function readUint16($offset = 0)
    {
        $s = $this->read(2, $offset);
        list(, $ret) = unpack("n", $s);
        return $ret;
    }

    /**
     * Reads signed 16-bit integer from buffer.
     *
     * @param int $offset
     * @return int
     */
    public function readInt16($offset = 0)
    {
        $s = $this->read(2, $offset);
        list(, $ret) = unpack("s", self::$isLittleEndian ? self::swapEndian16($s) : $s);
        return $ret;
    }

    /**
     * Reads and discards unsigned 16-bit integer from buffer.
     *
     * @return int
     */
    public function consumeUint16()
    {
        list(, $ret) = unpack("n", $this->buffer);
        $this->discard(2);
        return $ret;
    }

    /**
     * Reads and discards signed 16-bit integer from buffer.
     *
     * @return int
     */
    public function consumeInt16()
    {
        $s = $this->consume(2);
        list(, $ret) = unpack("s", self::$isLittleEndian ? self::swapEndian16($s) : $s);
        return $ret;
    }

    /**
     * Appends unsigned 16-bit integer to buffer.
     *
     * @param int $value
     * @return Buffer
     */
    public function appendUint16($value)
    {
        $s = pack("n", $value);
        return $this->append($s);
    }

    /**
     * Appends signed 16-bit integer to buffer.
     *
     * @param int $value
     * @return Buffer
     */
    public function appendInt16($value)
    {
        $s = pack("s", $value);
        return $this->append(self::$isLittleEndian ? self::swapEndian16($s) : $s);
    }

    /**
     * Reads unsigned 32-bit integer from buffer.
     *
     * @param int $offset
     * @return int
     */
    public function readUint32($offset = 0)
    {
        $s = $this->read(4, $offset);
        list(, $ret) = unpack("N", $s);
        return $ret;
    }

    /**
     * Reads signed 32-bit integer from buffer.
     *
     * @param int $offset
     * @return int
     */
    public function readInt32($offset = 0)
    {
        $s = $this->read(4, $offset);
        list(, $ret) = unpack("l", self::$isLittleEndian ? self::swapEndian32($s) : $s);
        return $ret;
    }

    /**
     * Reads and discards unsigned 32-bit integer from buffer.
     *
     * @return int
     */
    public function consumeUint32()
    {
        list(, $ret) = unpack("N", $this->buffer);
        $this->discard(4);
        return $ret;
    }

    /**
     * Reads and discards signed 32-bit integer from buffer.
     *
     * @return int
     */
    public function consumeInt32()
    {
        $s = $this->consume(4);
        list(, $ret) = unpack("l", self::$isLittleEndian ? self::swapEndian32($s) : $s);
        return $ret;
    }

    /**
     * Appends unsigned 32-bit integer to buffer.
     *
     * @param int $value
     * @return Buffer
     */
    public function appendUint32($value)
    {
        $s = pack("N", $value);
        return $this->append($s);
    }

    /**
     * Appends signed 32-bit integer to buffer.
     *
     * @param int $value
     * @return Buffer
     */
    public function appendInt32($value)
    {
        $s = pack("l", $value);
        return $this->append(self::$isLittleEndian ? self::swapEndian32($s) : $s);
    }

    /**
     * Reads unsigned 64-bit integer from buffer.
     *
     * @param int $offset
     * @return int
     */
    public function readUint64($offset = 0)
    {
        $s = $this->read(8, $offset);
        if (self::$native64BitPack) {
            list(, $ret) = unpack("Q", self::$isLittleEndian ? self::swapEndian64($s) : $s);
        } else {
            $d = unpack("Lh/Ll", self::$isLittleEndian ? self::swapHalvedEndian64($s) : $s);
            $ret = $d["h"] << 32 | $d["l"];
        }
        return $ret;
    }

    /**
     * Reads signed 64-bit integer from buffer.
     *
     * @param int $offset
     * @return int
     */
    public function readInt64($offset = 0)
    {
        $s = $this->read(8, $offset);
        if (self::$native64BitPack) {
            list(, $ret) = unpack("q", self::$isLittleEndian ? self::swapEndian64($s) : $s);
        } else {
            $d = unpack("Lh/Ll", self::$isLittleEndian ? self::swapHalvedEndian64($s) : $s);
            $ret = $d["h"] << 32 | $d["l"];
        }
        return $ret;
    }

    /**
     * Reads and discards unsigned 64-bit integer from buffer.
     *
     * @return int
     */
    public function consumeUint64()
    {
        $s = $this->consume(8);
        if (self::$native64BitPack) {
            list(, $ret) = unpack("Q", self::$isLittleEndian ? self::swapEndian64($s) : $s);
        } else {
            $d = unpack("Lh/Ll", self::$isLittleEndian ? self::swapHalvedEndian64($s) : $s);
            $ret = $d["h"] << 32 | $d["l"];
        }
        return $ret;
    }

    /**
     * Reads and discards signed 64-bit integer from buffer.
     *
     * @return int
     */
    public function consumeInt64()
    {
        $s = $this->consume(8);
        if (self::$native64BitPack) {
            list(, $ret) = unpack("q", self::$isLittleEndian ? self::swapEndian64($s) : $s);
        } else {
            $d = unpack("Lh/Ll", self::$isLittleEndian ? self::swapHalvedEndian64($s) : $s);
            $ret = $d["h"] << 32 | $d["l"];
        }
        return $ret;
    }

    /**
     * Appends unsigned 64-bit integer to buffer.
     *
     * @param int $value
     * @return Buffer
     */
    public function appendUint64($value)
    {
        if (self::$native64BitPack) {
            $s = pack("Q", $value);
            if (self::$isLittleEndian) {
                $s = self::swapEndian64($s);
            }
        } else {
            $s = pack("LL", ($value & 0xffffffff00000000) >> 32, $value & 0x00000000ffffffff);
            if (self::$isLittleEndian) {
                $s = self::swapHalvedEndian64($s);
            }
        }

        return $this->append($s);
    }

    /**
     * Appends signed 64-bit integer to buffer.
     *
     * @param int $value
     * @return Buffer
     */
    public function appendInt64($value)
    {
        if (self::$native64BitPack) {
            $s = pack("q", $value);
            if (self::$isLittleEndian) {
                $s = self::swapEndian64($s);
            }
        } else {
            $s = pack("LL", ($value & 0xffffffff00000000) >> 32, $value & 0x00000000ffffffff);
            if (self::$isLittleEndian) {
                $s = self::swapHalvedEndian64($s);
            }
        }

        return $this->append($s);
    }

    /**
     * Reads float from buffer.
     *
     * @param int $offset
     * @return float
     */
    public function readFloat($offset = 0)
    {
        $s = $this->read(4, $offset);
        list(, $ret) = unpack("f", self::$isLittleEndian ? self::swapEndian32($s) : $s);
        return $ret;
    }

    /**
     * Reads and discards float from buffer.
     *
     * @return float
     */
    public function consumeFloat()
    {
        $s = $this->consume(4);
        list(, $ret) = unpack("f", self::$isLittleEndian ? self::swapEndian32($s) : $s);
        return $ret;
    }

    /**
     * Appends float to buffer.
     *
     * @param float $value
     * @return Buffer
     */
    public function appendFloat($value)
    {
        $s = pack("f", $value);
        return $this->append(self::$isLittleEndian ? self::swapEndian32($s) : $s);
    }

    /**
     * Reads double from buffer.
     *
     * @param int $offset
     * @return float
     */
    public function readDouble($offset = 0)
    {
        $s = $this->read(8, $offset);
        list(, $ret) = unpack("d", self::$isLittleEndian ? self::swapEndian64($s) : $s);
        return $ret;
    }

    /**
     * Reads and discards double from buffer.
     *
     * @return float
     */
    public function consumeDouble()
    {
        $s = $this->consume(8);
        list(, $ret) = unpack("d", self::$isLittleEndian ? self::swapEndian64($s) : $s);
        return $ret;
    }

    /**
     * Appends double to buffer.
     *
     * @param float $value
     * @return Buffer
     */
    public function appendDouble($value)
    {
        $s = pack("d", $value);
        return $this->append(self::$isLittleEndian ? self::swapEndian64($s) : $s);
    }

}
