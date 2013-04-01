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
    /**
     * @var resource
     */
    protected $sock;
    /**
     * @var string
     */
    protected $buffer;
    /**
     * @var int
     */
    protected $offset;
    /**
     * @var int
     */
    protected $blockSize = 8192;

    /**
     * @param $sock
     */
    public function __construct($sock)
    {

        $this->sock = $sock;
        $this->reset('');

    }

    /**
     * @return mixed
     */
    public function realSock()
    {
        return $this->sock;
    }

    /**
     * @param $n
     *
     * @return bool|string
     */
    public function read($n)
    {
        if ($this->offset >= strlen($this->buffer)) {
            if (!($rv = $this->populateBuffer())) {
                return $rv;
            }
        }
        return $this->readBuffer($n);
    }

    /**
     *
     */
    public function close()
    {
        fclose($this->sock);
        $this->reset('');
    }

    /**
     * @param $n
     *
     * @return string
     */
    protected function readBuffer($n)
    {
        $n = min($n, strlen($this->buffer) - $this->offset);
        if ($n === 0) {
            // substr('', 0, 0) => false, which screws up read loops that are
            // expecting non-blocking reads to return ''. This avoids that edge
            // case when the buffer is empty/used up.
            return '';
        }
        $block = substr($this->buffer, $this->offset, $n);
        $this->offset += $n;
        return $block;
    }

    /**
     * @param $block
     */
    protected function reset($block)
    {
        $this->buffer = $block;
        $this->offset = 0;
    }

    /**
     * @return bool|string
     */
    protected function populateBuffer()
    {
        if (feof($this->sock)) {
            $this->reset('');
            return false;
        }

        $block = fread($this->sock, $this->blockSize);
        if ($block !== false) {
            $this->reset($block);
            return true;
        } else {
            return $block;
        }
    }
}
