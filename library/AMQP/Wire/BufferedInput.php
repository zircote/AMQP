<?php
namespace AMQP\Wire;
/**
 *
 */
/**
 *
 */
class BufferedInput
{
    protected $_sock;

    /**
     * @param $sock
     */
    public function __construct($sock)
    {
        $this->block_size = 8192;

        $this->_sock = $sock;
        $this->_reset('');

    }

    /**
     * @return mixed
     */
    public function realSock()
    {
        return $this->_sock;
    }

    /**
     * @param $n
     *
     * @return bool|string
     */
    public function read($n)
    {
        if ($this->offset >= strlen($this->buffer))
        {
            if (!($rv = $this->_populateBuffer()))
            {
                return $rv;
            }
        }
        return $this->_readBuffer($n);
    }

    /**
     *
     */
    public function close()
    {
        fclose($this->_sock);
        $this->_reset("");
    }

    /**
     * @param $n
     *
     * @return string
     */
    protected function _readBuffer($n)
    {
        $n = min($n, strlen($this->buffer) - $this->offset);
        if ($n === 0)
        {
            // substr("", 0, 0) => false, which screws up read loops that are
            // expecting non-blocking reads to return "". This avoids that edge
            // case when the buffer is empty/used up.
            return "";
        }
        $block = substr($this->buffer, $this->offset, $n);
        $this->offset += $n;
        return $block;
    }

    /**
     * @param $block
     */
    protected function _reset($block)
    {
        $this->buffer = $block;
        $this->offset = 0;
    }

    /**
     * @return bool|string
     */
    protected function _populateBuffer()
    {
        if(feof($this->_sock))
        {
            $this->_reset("");
            return false;
        }

        $block = fread($this->_sock, $this->block_size);
        if ($block !== false)
        {
            $this->_reset($block);
            return true;
        } else
        {
            return $block;
        }
    }
}
