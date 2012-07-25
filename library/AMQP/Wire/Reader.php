<?php
namespace AMQP\Wire;
/**
 *
 */
use AMQP\Wire\Decimal;
use AMQP\Wire\BufferedInput;

/**
 *
 */
class Reader
{

    /**
     * @var int
     */
    protected $_bits = 0;

    /**
     * @var int
     */
    protected $_bitcount = 0;

    /**
     * @var int
     */
    protected $_offset = 0;

    /**
     * @var bool
     */
    protected $_is64bits = false;

    /**
     * @var int
     */
    protected $_bufferReadTimeout = 5;

    /**
     * @var string
     */
    protected $_str;

    /**
     * @var BufferedInput
     */
    protected $_bufferedInput;

    /**
     * @param string $str
     * @param null   $bufferedInput
     */
    public function __construct($str, $bufferedInput = false)
    {
        $this->_str = $str;
        if ($bufferedInput) {
            $this->_bufferedInput = new BufferedInput($bufferedInput);
        }
        if (((int)4294967296) != 0) {
            $this->_is64bits = true;
        }

        /*
         * bcmul required
         */
        if (!extension_loaded('bcmath')) {
            throw new \RuntimeException("'bc math' module required");
        }
    }

    /**
     *
     */
    public function close()
    {
        if ($this->_bufferedInput) {
            $this->_bufferedInput->close();
        }
    }

    /**
     * @param int $n
     *
     * @return string
     */
    public function read($n)
    {
        $this->_bitcount = $this->_bits = 0;
        return $this->_rawread($n);
    }

    /**
     * @return bool
     */
    public function readBit()
    {
        if (!$this->_bitcount) {
            $this->_bits = ord($this->_rawread(1));
            $this->_bitcount = 8;
        }
        $result = ($this->_bits & 1) == 1;
        $this->_bits >>= 1;
        $this->_bitcount -= 1;
        return $result;
    }

    /**
     * @return mixed
     */
    public function readOctet()
    {
        $this->_bitcount = $this->_bits = 0;
        list(, $res) = unpack('C', $this->_rawread(1));
        return $res;
    }

    /**
     * @return mixed
     */
    public function readShort()
    {
        $this->_bitcount = $this->_bits = 0;
        list(, $res) = unpack('n', $this->_rawread(2));
        return $res;
    }

    /**
     * Reads 32 bit integer in big-endian byte order.
     *
     * On 64 bit systems it will return always usngined int
     * value in 0..2^32 range.
     *
     * On 32 bit systems it will return signed int value in
     * -2^31...+2^31 range.
     *
     * Use with caution!
     *
     * @return int
     */
    public function readPhpInt()
    {
        list(, $res) = unpack('N', $this->_rawread(4));
        if ($this->_is64bits) {
            $sres = sprintf("%u", $res);
            return (int)$sres;
        } else {
            return $res;
        }
    }

    /**
     * PHP does not have unsigned 32 bit int, so we return it as a string
     *
     * @return string
     */
    public function readLong()
    {
        $this->_bitcount = $this->_bits = 0;
        list(, $res) = unpack('N', $this->_rawread(4));
        $sres = sprintf("%u", $res);
        return $sres;
    }

    /**
     * @return mixed
     */
    protected function _readSignedLong()
    {
        $this->_bitcount = $this->_bits = 0;
        list(, $res) = unpack('N', $this->_rawread(4));
        return $res;
    }

    /**
     * @return string
     */
    public function readLonglong()
    {
        $this->_bitcount = $this->_bits = 0;
        $hi = unpack('N', $this->_rawread(4));
        $lo = unpack('N', $this->_rawread(4));
        $hi = sprintf("%u", $hi[ 1 ]);
        $lo = sprintf("%u", $lo[ 1 ]);

        return bcadd(bcmul($hi, "4294967296"), $lo);
    }

    /**
     * Read a utf-8 encoded string that's stored in up to
     * 255 bytes.  Return it decoded as a PHP unicode object.
     *
     * @return string
     */
    public function readShortstr()
    {
        $this->_bitcount = $this->_bits = 0;
        list(, $slen) = unpack('C', $this->_rawread(1));
        return $this->_rawread($slen);
    }

    /**
     * Read a string that's up to 2**32 bytes, the encoding
     * isn't specified in the AMQP spec, so just return it as
     * a plain PHP string.
     *
     * @return string
     * @throws \Exception
     */
    public function readLongstr()
    {
        $this->_bitcount = $this->_bits = 0;
        $slen = $this->readPhpInt();
        if ($slen < 0) {
            throw new \Exception("Strings longer than supported on this platform");
        }
        return $this->_rawread($slen);
    }

    /**
     * Read and AMQP timestamp, which is a 64-bit integer representing
     * seconds since the Unix epoch in 1-second resolution.
     *
     * @return string
     */
    public function readTimestamp()
    {
        return $this->readLonglong();
    }

    /**
     * Read an AMQP table, and return as a PHP array. keys are strings,
     * values are (type,value) tuples.
     *
     * @return array
     * @throws \Exception
     */
    public function readTable()
    {
        $this->_bitcount = $this->_bits = 0;
        $tlen = $this->readPhpInt();
        if ($tlen < 0) {
            throw new \Exception('Table is longer than supported');
        }
        $tableData = new Reader($this->_rawread($tlen));
        $result = array();
        while ($tableData->tell() < $tlen) {
            $name = $tableData->readShortstr();
            $ftype = $tableData->_rawread(1);
            if ($ftype == 'S') {
                $val = $tableData->readLongstr();
            } else if ($ftype == 'I') {
                $val = $tableData->_readSignedLong();
            } else if ($ftype == 'D') {
                $e = $tableData->readOctet();
                $n = $tableData->_readSignedLong();
                $val = new Decimal($n, $e);
            } else if ($ftype == 'T') {
                $val = $tableData->readTimestamp();
            } else if ($ftype == 'F') {
                $val = $tableData->readTable(); // recursion
            } else {
                error_log(sprintf('Usupported table field type %s', $ftype));
                $val = null;
            }
            $result[ $name ] = array( $ftype, $val );
        }
        return $result;
    }

    /**
     * @param $n
     *
     * @return string
     * @throws \Exception
     */
    protected function _rawread($n)
    {
        if ($this->_bufferedInput) {
            $result = '';
            $read = 0;
            while ($read < $n && !feof($this->_bufferedInput->realSock()) &&
                   (false !== ($buffer = fread($this->_bufferedInput->realSock(), $n - $read)))
            ) {
                $read += strlen($buffer);
                $result .= $buffer;
            }

            if (strlen($result) != $n) {
                throw new \Exception(
                    sprintf(
                            'Error reading data. Recevived %d instead of '.
                            'expected %d bytes', strlen($result), $n
                         )
                    );
            }

            $this->_offset += $n;
        } else {
            if (strlen($this->_str) < $n) {
                throw new \Exception(
                    sprintf(
                        'Error reading data. Requested %d bytes while string '.
                        'buffer has only %s', $n, strlen($this->_str)
                    )
                );
            }
            $result = substr($this->_str, 0, $n);
            $this->_str = substr($this->_str, $n);
            $this->_offset += $n;
        }
        return $result;
    }

    /**
     * @return int
     */
    protected function tell()
    {
        return $this->_offset;
    }
}
