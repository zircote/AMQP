<?php
namespace AMQP\Wire;
/**
 *
 */
/**
 *
 */
class Writer
{

    protected $bits;
    protected $out;
    protected $bitcount;

    public function __construct()
    {
        $this->out = '';
        $this->bits = array();
        $this->bitcount = 0;
    }

    /**
     * @static
     *
     * @param $x
     * @param $bytes
     *
     * @return array
     */
    protected static function _chrByteSplit($x, $bytes)
    {
        return array_map('chr', Writer::_byteSplit($x, $bytes));
    }

    /**
     * Splits number (could be either int or string) into array of byte
     * values (represented as integers) in big-endian byte order.
     *
     * @static
     *
     * @param $x
     * @param $bytes
     *
     * @return array
     * @throws \Exception
     */
    protected static function _byteSplit($x, $bytes)
    {
        if (is_int($x)) {
            if ($x < 0) {
                $x = sprintf("%u", $x);
            }
        }

        $res = array();

        while ($bytes > 0) {
            $b = bcmod($x, '256');
            $res[ ] = (int)$b;
            $x = bcdiv($x, '256', 0);
            $bytes--;
        }

        $res = array_reverse($res);

        if ($x != 0) {
            throw new \Exception('Value too big!');
        }

        return $res;
    }

    protected function _flushBits()
    {
        if (!empty($this->bits)) {
            $this->out .= implode("", array_map('chr', $this->bits));
            $this->bits = array();
            $this->bitcount = 0;
        }
    }

    /**
     * Get what's been encoded so far.
     */
    public function getvalue()
    {
        $this->_flushBits();
        return $this->out;
    }

    /**
     * Write a plain PHP string, with no special encoding.
     */
    public function write($s)
    {
        $this->_flushBits();
        $this->out .= $s;
        return $this;
    }

    /**
     * Write a boolean value.
     */
    public function writeBit($b)
    {
        if ($b) {
            $b = 1;
        } else {
            $b = 0;
        }

        $shift = $this->bitcount % 8;

        if ($shift == 0) {
            $last = 0;
        } else {
            $last = array_pop($this->bits);
        }

        $last |= ($b << $shift);
        array_push($this->bits, $last);

        $this->bitcount += 1;
        return $this;
    }

    /**
     * Write an integer as an unsigned 8-bit value.
     *
     * @param $n
     *
     * @return Writer
     * @throws \InvalidArgumentException
     */
    public function writeOctet($n)
    {
        if ($n < 0 || $n > 255) {
            throw new \InvalidArgumentException('Octet out of range 0..255');
        }

        $this->_flushBits();
        $this->out .= chr($n);
        return $this;
    }

    /**
     * Write an integer as an unsigned 16-bit value.
     *
     * @param $n
     *
     * @return Writer
     * @throws \InvalidArgumentException
     */
    public function writeShort($n)
    {
        if ($n < 0 || $n > 65535) {
            throw new \InvalidArgumentException('Octet out of range 0..65535');
        }

        $this->_flushBits();
        $this->out .= pack('n', $n);
        return $this;
    }

    /**
     * Write an integer as an unsigned 32-bit value.
     *
     * @param $n
     *
     * @return Writer
     */
    public function writeLong($n)
    {
        $this->_flushBits();
        $this->out .= implode("", Writer::_chrByteSplit($n, 4));
        return $this;
    }

    /**
     * @param $n
     *
     * @return Writer
     */
    protected function _writeSignedLong($n)
    {
        $this->_flushBits();
        $this->out .= pack('N', $n);
        return $this;
    }

    /**
     * Write an integer as an unsigned 64-bit value.
     *
     * @param $n
     *
     * @return Writer
     */
    public function writeLongLong($n)
    {
        $this->_flushBits();
        $this->out .= implode("", Writer::_chrByteSplit($n, 8));
        return $this;
    }

    /**
     * Write a string up to 255 bytes long after encoding.
     * Assume UTF-8 encoding.
     *
     * @param $s
     *
     * @return Writer
     * @throws \InvalidArgumentException
     */
    public function writeShortStr($s)
    {
        $this->_flushBits();
        if (strlen($s) > 255) {
            throw new \InvalidArgumentException('String too long');
        }

        $this->writeOctet(strlen($s));
        $this->out .= $s;
        return $this;
    }

    /**
    * Write a string up to 2**32 bytes long.  Assume UTF-8 encoding.
     *
     * @param $s
     *
     * @return Writer
     */
    public function writeLongStr($s)
    {
        $this->_flushBits();
        $this->writeLong(strlen($s));
        $this->out .= $s;
        return $this;
    }

    /**
     * Supports the writing of Array types, so that you can implement
     * array methods, like Rabbitmq's HA parameters
     *
     * @param array $a
     *
     * @return self
     */
    public function writeArray($a)
    {
        $this->_flushBits();
        $data = new Writer();

        foreach ($a as $v) {
            if (is_string($v)) {
                $data->write('S');
                $data->writeLongStr($v);
            } else if (is_int($v)) {
                $data->write('I');
                $data->_writeSignedLong($v);
            } else if ($v instanceof Decimal) {
                $data->write('D');
                $data->writeOctet($v->e);
                $data->_writeSignedLong($v->n);
            } else if (is_array($v)) {
                $data->write('A');
                $data->writeArray($v);
            }
        }

        $data = $data->getvalue();
        $this->writeLong(strlen($data));
        $this->write($data);
        return $this;
    }

    /**
     * Write unix time_t value as 64 bit timestamp.
     *
     * @param $v
     *
     * @return Writer
     */
    public function writeTimestamp($v)
    {
        $this->writeLongLong($v);
        return $this;
    }

    /**
     * Write PHP array, as table. Input array format: keys are strings,
     * values are (type,value) tuples.
     *
     * @param $d
     *
     * @return Writer
     */
    public function writeTable($d)
    {
        $this->_flushBits();
        $table_data = new Writer();
        foreach ($d as $k => $va) {
            list($ftype, $v) = $va;
            $table_data->writeShortStr($k);
            if ($ftype == 'S') {
                $table_data->write('S');
                $table_data->writeLongStr($v);
            } else if ($ftype == 'I') {
                $table_data->write('I');
                $table_data->_writeSignedLong($v);
            } else if ($ftype == 'D') {
                // 'D' type values are passed Decimal instances.
                $table_data->write('D');
                $table_data->writeOctet($v->e);
                $table_data->_writeSignedLong($v->n);
            } else if ($ftype == 'T') {
                $table_data->write('T');
                $table_data->writeTimestamp($v);
            } else if ($ftype == 'F') {
                $table_data->write('F');
                $table_data->writeTable($v);
            } else if ($ftype = 'A') {
                $table_data->write('A');
                $table_data->writeArray($v);
            }
        }

        $table_data = $table_data->getvalue();
        $this->writeLong(strlen($table_data));
        $this->write($table_data);
        return $this;
    }
}
