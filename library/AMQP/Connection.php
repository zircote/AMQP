<?php
namespace AMQP;

/**
 *
 */
use AMQP\AbstractChannel;
use AMQP\Channel;
use AMQP\Exception\ConnectionException;
use AMQP\Util\Helper;
use AMQP\Wire\Writer;
use AMQP\Wire\Reader;

/**
 *
 */
class Connection extends AbstractChannel
{

    const AMQP_AUTH_PLAIN = 'AMQPLAIN';
    const AMQP_CHANNEL_MAX = 65535;
    const AMQP_FRAME_MAX = 131072;

    /**
     * @var string
     */
    public static $amqpProtocolHeader = "AMQP\x01\x01\x09\x01";

    /**
     * @var array
     */
    public static $libraryProperties
        = array(
            "library"         => array('S', "PHP-AMQP"),
            "library_version" => array('S', "@package_version@")
        );

    /**
     * @var array
     */
    protected $options
        = array(
            'insist'             => false,
            'login_method'       => self::AMQP_AUTH_PLAIN,
            'login_response'     => null,
            'locale'             => 'en_US',
            'connection_timeout' => 3,
            'read_write_timeout' => 3,
            'context'            => null,
            'ssl_options'        => array()
        );

    /**
     * @var array
     */
    protected $methodMap
        = array(
            "10,10" => "start",
            "10,20" => "secure",
            "10,30" => "tune",
            "10,41" => "openOk",
            "10,50" => "redirect",
            "10,60" => "_close",
            "10,61" => "close_ok"
        );

    /**
     * @var string
     */
    protected $connectionError;

    /**
     * @var string
     */
    protected $connectionErrorNumber;

    /**
     * @var resource|null
     */
    protected $sock;

    /**
     * @var int
     */
    protected $versionMajor;

    /**
     * @var int
     */
    protected $versionMinor;

    /**
     * @var array
     */
    protected $serverProperties = array();

    /**
     * @var array
     */
    protected $mechanisms = array();

    /**
     * @var array
     */
    protected $locales;

    /**
     * @var \AMQP\Wire\Reader
     */
    protected $input;

    /**
     * @var array
     */
    public $channels = array();

    /**
     * @var int
     */
    protected $channelMax = self::AMQP_CHANNEL_MAX;

    /**
     * @var int
     */
    protected $frameMax = self::AMQP_FRAME_MAX;

    /**
     * @var bool
     */
    protected $waitTuneOk = true;

    /**
     * @var array
     */
    protected $uri = array();

    /**
     * @var array
     */
    protected $knownHosts = array();

    /**
     *
     * @param string|null $uri amqp://guest:guest@localhost:5672/vhost
     *
     * @param array       $options
     */
    public function __construct($uri = null, $options = array())
    {
        $this->setUri($uri)
            ->setOptions($options)
            ->connect();
    }

    /**
     * @param $uri
     *
     * @return Connection
     * @throws \RuntimeException
     * @throws \UnexpectedValueException
     */
    public function setUri($uri)
    {
        if (null === $uri) {
            $uri = 'amqp://guest:guest@localhost:5672/';
        }
        $this->uri = parse_url($uri);
        if (($this->uri['scheme'] != 'amqp' && $this->uri['scheme'] != 'amqps')) {
            throw new \UnexpectedValueException(
                sprintf(
                    'a valid URI scheme is request expected format [%s] ' .
                        'received: [%s]', 'tcp://user:pass@host:port/vhost',
                    $uri
                )
            );
        }
        if (empty($this->uri['user']) || empty($this->uri['pass'])) {
            throw new \RuntimeException(
                'AMQPLAIN Auth requires both a user and pass be specified'
            );
        }
        if ($this->uri['scheme'] === 'amqps') {
            if (!extension_loaded('openssl')) {
                throw new \RuntimeException(
                    'SSL connection attempted however openssl extension is not loaded'
                );
            }
            $ssl_context = stream_context_create();
            foreach ($this->options['ssl_options'] as $k => $v) {
                stream_context_set_option($ssl_context, 'ssl', $k, $v);
            }
        }
        return $this;
    }

    /**
     * @param array $options
     *
     * @return Connection
     */
    public function setOptions($options)
    {
        $options = (array)$options;
        foreach ($options as $option => $value) {
            $this->setOption($option, $value);
        }
        return $this;
    }

    /**
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * @param $option
     * @param $value
     *
     * @return Connection
     */
    public function setOption($option, $value)
    {
        if (array_key_exists($option, $this->options)) {
            $this->options[$option] = $value;
        }
        return $this;
    }

    /**
     * @param $option
     *
     * @return bool
     */
    public function getOption($option)
    {
        return array_key_exists($option, $this->options) ?
            $this->options[$option] : false;
    }

    /**
     * @return \AMQP\Wire\Writer|null|string
     */
    protected function getLoginResponse()
    {
        if ($this->uri['user'] && $this->uri['pass']) {
            $loginResponse = new Writer();
            $loginResponse->writeTable(
                array(
                     "LOGIN"    => array('S', $this->uri['user']),
                     "PASSWORD" => array('S', $this->uri['pass'])
                )
            );
            $loginResponse = substr($loginResponse->getvalue(), 4);
        } else {
            $loginResponse = null;
        }
        return $loginResponse;
    }

    /**
     * @throws \Exception
     */
    protected function createSocket()
    {
        if ($this->uri['scheme'] === 'amqp') {
            $scheme = 'tcp';
        } elseif ($this->uri['scheme'] === 'amqps') {
            $scheme = 'ssl';
        } else {
            throw new \RuntimeException(
                'Unknown Connection Scheme please select [amqp] or [amqps]'
            );
        }
        $remote = sprintf(
            '%s://%s:%s', $scheme,
            $this->uri['host'], $this->uri['port']
        );
        $args = array(
            $remote,
            &$this->connectionErrorNumber,
            &$this->connectionError,
            $this->options['connection_timeout'],
            STREAM_CLIENT_CONNECT
        );
        if ($scheme === 'ssl') {
            $ssl_context = stream_context_create();
            foreach ($this->options['ssl_options'] as $k => $v) {
                stream_context_set_option($ssl_context, 'ssl', $k, $v);
            }
            array_push($args, $ssl_context);
        }
        $this->sock = call_user_func_array('stream_socket_client', $args);
        if (!$this->sock) {
            throw new \Exception (
                sprintf(
                    'Error Connecting to server(%s): %s',
                    $this->connectionErrorNumber, $this->connectionError
                )
            );
        }

        if (!stream_set_timeout($this->sock, $this->options['read_write_timeout'])) {
            throw new \Exception('Timeout could not be set');
        }
        stream_set_blocking($this->sock, true);
    }

    /**
     *
     */
    protected function connect()
    {
        $login_response = $this->getLoginResponse();

        while (true) {
            $this->channels = array();
            /*
             * The connection object itself is treated as channel 0
             */
            parent::__construct($this, 0);
            $this->createSocket();

            $this->input = new Reader(null, $this->sock);

            $this->write(self::$amqpProtocolHeader);
            $this->wait(array("10,10"));
            $this->xStartOk(
                self::$libraryProperties, $this->options['login_method'],
                $login_response, $this->options['locale']
            );

            while ($this->waitTuneOk) {
                $this->wait(
                    array(
                         "10,20", // secure
                         "10,30", // tune
                    )
                );
            }
            $vhost = preg_replace('/^\//', null, $this->uri['path']) ? : '/';
            $host = $this->xOpen(
                $vhost, '', $this->options['insist']
            );
            if (!$host) {
                return; // we weren't redirected
            }
            $this->closeSocket();
            return;
        }
    }

    /**
     * @param string $data
     *
     * @throws \Exception
     */
    protected function write($data)
    {
        if ($this->debug) {
            Helper::debugMsg(
                '< [hex]:' . PHP_EOL . Helper::hexdump(
                    $data, false, true, true
                )
            );
        }

        $len = strlen($data);
        while (true) {
            if (false === ($written = fwrite($this->sock, $data))) {
                throw new \Exception ('Error sending data');
            }
            if ($written === 0) {
                throw new \Exception ('Broken pipe or closed connection');
            }
            $info = stream_get_meta_data($this->sock);
            if ($info['timed_out']) {
                throw new \Exception("Error sending data. Socket connection timed out");
            }
            $len = $len - $written;
            if ($len > 0) {
                $data = substr($data, 0 - $len);
            } else {
                break;
            }
        }
    }

    /**
     *
     */
    protected function doClose()
    {
        if (isset($this->input) && $this->input) {
            $this->input->close();
            $this->input = null;
        }

        $this->closeSocket();
    }

    /**
     * @return int
     * @throws \Exception
     */
    public function getFreeChannelId()
    {
        for ($i = 1; $i <= $this->channelMax; $i++) {
            if (!isset($this->channels[$i])) {
                return $i;
            }
        }
        throw new \Exception("No free channel ids");
    }

    /**
     * @param string $channel
     * @param string $classId
     * @param string $weight
     * @param string $bodySize
     * @param string $packedProperties
     * @param string $body
     *
     * @return Connection
     */
    public function sendContent(
        $channel, $classId, $weight, $bodySize,
        $packedProperties, $body
    )
    {
        $outboundPacket = new Writer();

        $outboundPacket->writeOctet(2);
        $outboundPacket->writeShort($channel);
        $outboundPacket->writeLong(strlen($packedProperties) + 12);

        $outboundPacket->writeShort($classId);
        $outboundPacket->writeShort($weight);
        $outboundPacket->writeLongLong($bodySize);
        $outboundPacket->write($packedProperties);

        $outboundPacket->writeOctet(0xCE);
        $outboundPacket = $outboundPacket->getvalue();
        $this->write($outboundPacket);

        while ($body) {
            $payload = substr($body, 0, $this->frameMax - 8);
            $body = substr($body, $this->frameMax - 8);
            $outboundPacket = new Writer();

            $outboundPacket->writeOctet(3);
            $outboundPacket->writeShort($channel);
            $outboundPacket->writeLong(strlen($payload));

            $outboundPacket->write($payload);

            $outboundPacket->writeOctet(0xCE);
            $outboundPacket = $outboundPacket->getvalue();
            $this->write($outboundPacket);
        }
        return $this;
    }

    /**
     * @param string            $channel
     * @param string            $methodSig
     * @param Writer|string     $args
     *
     * @return Connection
     */
    public function sendChannelMethodFrame($channel, $methodSig, $args = '')
    {
        if ($args instanceof Writer) {
            $args = $args->getvalue();
        }

        $outboundPacket = new Writer();

        $outboundPacket->writeOctet(1);
        $outboundPacket->writeShort($channel);
        $outboundPacket->writeLong(
            strlen($args) + 4
        ); // 4 = length of class_id and method_id
        // in payload

        $outboundPacket->writeShort($methodSig[0]); // class_id
        $outboundPacket->writeShort($methodSig[1]); // method_id
        $outboundPacket->write($args);

        $outboundPacket->writeOctet(0xCE);
        $outboundPacket = $outboundPacket->getvalue();
        $this->write($outboundPacket);

        if ($this->debug) {
            Helper::debugMsg(
                '< ' . Helper::methodSig($methodSig) . ': ' .
                    AbstractChannel::$globalMethodNames[Helper::methodSig(
                        $methodSig
                    )]
            );
        }
        return $this;
    }

    /**
     * Wait for a frame from the server
     *
     * @return array
     * @throws \Exception
     */
    protected function waitFrame()
    {
        $frameType = $this->input->readOctet();
        $channel = $this->input->readShort();
        $size = $this->input->readLong();
        $payload = $this->input->read($size);

        $ch = $this->input->readOctet();
        if ($ch != 0xCE) {
            throw new \Exception(
                sprintf(
                    'Framing error, unexpected byte: %x', $ch
                )
            );
        }

        return array($frameType, $channel, $payload);
    }

    /**
     * Wait for a frame from the server destined for a particular channel.
     *
     * @param $channelId
     *
     * @return array
     */
    public function waitChannel($channelId)
    {
        while (true) {
            list($frameType, $frameChannel, $payload) = $this->waitFrame();
            if ($frameChannel == $channelId) {
                return array($frameType, $payload);
            }
            /**
             * Not the channel we were looking for.  Queue this frame
             * for later, when the other channel is looking for frames.
             */
            array_push(
                $this->channels[$frameChannel]->frame_queue,
                array($frameType, $payload)
            );

            /**
             * If we just queued up a method for channel 0 (the Connection
             * itself) it's probably a close method in reaction to some
             * error, so deal with it right away.
             */
            if (($frameType == 1) && ($frameChannel == 0)) {
                $this->wait();
            }
        }
        return array();
    }

    /**
     * Fetch a Channel object identified by the numeric channel_id, or
     * create that object if it doesn't already exist.
     *
     * @param string|null $channelId
     *
     * @return \AMQP\Channel
     */
    public function channel($channelId = null)
    {
        if (isset($this->channels[$channelId])) {
            return $this->channels[$channelId];
        } else {
            $channelId = $channelId ? : $this->getFreeChannelId();
            $ch = new Channel($this->connection, $channelId);
            return $this->channels[$channelId] = $ch;
        }
    }

    /**
     * request a connection close
     *
     * @param int    $replyCode
     * @param string $replyText
     * @param array  $methodSig
     *
     * @return mixed|null
     */
    public function close($replyCode = 0, $replyText = '', $methodSig = array(0, 0))
    {
        $args = new Writer();
        $args->writeShort($replyCode);
        $args->writeShortStr($replyText);
        $args->writeShort($methodSig[0]); // class_id
        $args->writeShort($methodSig[1]); // method_id
        $this->sendMethodFrame(array(10, 60), $args);
        return $this->wait(
            array(
                 "10,61", // Connection.close_ok
            )
        );
    }

    /**
     * @param \AMQP\Wire\Reader $args
     *
     * @throws \AMQP\Exception\ConnectionException
     */
    protected function _close(Reader $args)
    {
        $replyCode = $args->readShort();
        $replyText = $args->readShortstr();
        $classId = $args->readShort();
        $methodId = $args->readShort();

        $this->xCloseOk();

        throw new ConnectionException(
            $replyCode, $replyText, array(
                                         $classId,
                                         $methodId
                                    )
        );
    }

    /**
     * confirm a connection close
     */
    protected function xCloseOk()
    {
        $this->sendMethodFrame(array(10, 61));
        $this->doClose();
        return $this;
    }

    /**
     * confirm a connection close
     */
    protected function close_ok($args)
    {
        $this->doClose();
    }

    /**
     * @param        $virtualHost
     * @param string $capabilities
     * @param bool   $insist
     *
     * @return mixed|null
     */
    protected function xOpen($virtualHost, $capabilities = '', $insist = false)
    {
        $args = new Writer();
        $args->writeShortStr($virtualHost);
        $args->writeShortStr($capabilities);
        $args->writeBit($insist);
        $this->sendMethodFrame(array(10, 40), $args);
        return $this->wait(
            array(
                 "10,41", // Connection.openOk
                 "10,50" // Connection.redirect
            )
        );
    }

    /**
     * signal that the connection is ready
     */
    protected function openOk(Reader $args)
    {
        $this->knownHosts = $args->readShortstr();
        if ($this->debug) {
            Helper::debugMsg("Open OK! known_hosts: " . $this->knownHosts);
        }

        return null;
    }

    /**
     * asks the client to use a different server
     *
     * @param \AMQP\Wire\Reader $args
     *
     * @return string
     */
    protected function redirect(Reader $args)
    {
        $host = $args->readShortstr();
        $this->knownHosts = $args->readShortstr();
        if ($this->debug) {
            Helper::debugMsg(
                sprintf(
                    'Redirected to [%s], known_hosts [%s]',
                    $host, $this->knownHosts
                )
            );
        }
        return $host;
    }

    /**
     * security mechanism challenge
     *
     * @param \AMQP\Wire\Reader $args
     */
    protected function secure(Reader $args)
    {
        $challenge = $args->readLongstr();
    }

    /**
     * security mechanism response
     *
     * @param string $response
     */
    protected function _xSecureOk($response)
    {
        $args = new Writer();
        $args->writeLongStr($response);
        $this->sendMethodFrame(array(10, 21), $args);
    }

    /**
     * start connection negotiation
     */
    protected function start(Reader $args)
    {
        $this->versionMajor = $args->readOctet();
        $this->versionMinor = $args->readOctet();
        $this->serverProperties = $args->readTable();
        $this->mechanisms = explode(" ", $args->readLongstr());
        $this->locales = explode(" ", $args->readLongstr());

        if ($this->debug) {
            Helper::debugMsg(
                sprintf(
                    'Start from server, version: %d.%d, properties: %s, ' .
                        'mechanisms: %s, locales: %s',
                    $this->versionMajor,
                    $this->versionMinor,
                    self::dumpTable($this->serverProperties),
                    implode(', ', $this->mechanisms),
                    implode(', ', $this->locales)
                )
            );
        }
    }

    /**
     * @param string $clientProperties
     * @param string $mechanism
     * @param string $response
     * @param string $locale
     */
    protected function xStartOk($clientProperties, $mechanism, $response, $locale)
    {
        $args = new Writer();
        $args->writeTable($clientProperties);
        $args->writeShortStr($mechanism);
        $args->writeLongStr($response);
        $args->writeShortStr($locale);
        $this->sendMethodFrame(array(10, 11), $args);
    }

    /**
     * propose connection tuning parameters
     *
     * @param \AMQP\Wire\Reader $args
     */
    protected function tune(Reader $args)
    {
        $v = $args->readShort();
        if ($v) {
            $this->channelMax = $v;
        }

        $v = $args->readLong();

        if ($v) {
            $this->frameMax = $v;
        }

        $this->heartbeat = $args->readShort();

        $this->xTuneOk($this->channelMax, $this->frameMax, 0);
    }

    /**
     * negotiate connection tuning parameters
     *
     * @param $channelMax
     * @param $frameMax
     * @param $heartbeat
     */
    protected function xTuneOk($channelMax, $frameMax, $heartbeat)
    {
        $args = new Writer();
        $args->writeShort($channelMax);
        $args->writeLong($frameMax);
        $args->writeShort($heartbeat);
        $this->sendMethodFrame(array(10, 31), $args);
        $this->waitTuneOk = False;
    }

    /**
     * get socket from current connection
     *
     * @return null|resource
     */
    public function getSocket()
    {
        return $this->sock;
    }

    public function __destruct()
    {
        if (isset($this->input) && $this->input) {
            $this->close();
        }

        $this->closeSocket();
    }

    protected function closeSocket()
    {
        if ($this->debug) {
            Helper::debugMsg('closing socket');
        }
        if (is_resource($this->sock)) {
            @fclose($this->sock);
        }
        $this->sock = null;
    }

    /**
     * @static
     *
     * @param $table
     *
     * @return string
     */
    public static function dumpTable($table)
    {
        $tokens = array();
        foreach ($table as $name => $value) {
            switch ($value[0]) {
                case 'D':
                    $val = $value[1]->n . 'E' . $value[1]->e;
                    break;
                case 'F':
                    $val = '(' . self::dumpTable($value[1]) . ')';
                    break;
                case 'T':
                    $val = date('Y-m-d H:i:s', $value[1]);
                    break;
                default:
                    $val = $value[1];
            }
            $tokens[] = $name . '=' . $val;
        }
        return implode(', ', $tokens);
    }
}
