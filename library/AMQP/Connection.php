<?php
namespace AMQP;
/**
 *
 */
use AMQP\AbstractChannel;
use AMQP\Channel;
use AMQP\Exception\ConnectionException;
use AMQP\Helper;
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
    public static $libraryProperties = array(
        "library" => array( 'S', "PHP-AMQP" ),
        "library_version" => array( 'S', "@package_version@" )
    );

    /**
     * @var array
     */
    protected $_options = array(
        'insist' => false,
        'login_method' => self::AMQP_AUTH_PLAIN,
        'login_response' => null,
        'locale' => 'en_US',
        'connection_timeout' => 3,
        'read_write_timeout' => 3,
        'context' => null,
        'ssl_options' => array()
    );

    /**
     * @var array
     */
    protected $_methodMap = array(
        "10,10" => "_start",
        "10,20" => "_secure",
        "10,30" => "_tune",
        "10,41" => "_openOk",
        "10,50" => "_redirect",
        "10,60" => "_close",
        "10,61" => "_close_ok"
    );

    /**
     * @var string
     */
    protected $_connectionError;

    /**
     * @var string
     */
    protected $_connectionErrorNumber;

    /**
     * @var resource|null
     */
    protected $_sock;

    /**
     * @var int
     */
    protected $_versionMajor;

    /**
     * @var int
     */
    protected $_versionMinor;

    /**
     * @var array
     */
    protected $_serverProperties = array();

    /**
     * @var array
     */
    protected $_mechanisms = array();

    /**
     * @var array
     */
    protected $_locales;

    /**
     * @var \AMQP\Wire\Reader
     */
    protected $_input;

    /**
     * @var array
     */
    public $channels = array();

    /**
     * @var int
     */
    protected $_channelMax = self::AMQP_CHANNEL_MAX;

    /**
     * @var int
     */
    protected $_frameMax = self::AMQP_FRAME_MAX;

    /**
     * @var bool
     */
    protected $_waitTuneOk = true;

    /**
     * @var array
     */
    protected $_uri = array();

    /**
     * @var array
     */
    protected $knownHosts = array();

    /**
     *
     * @param string|null $uri amqp://guest:guest@localhost:5672/vhost
     *
     * @param array $options
     */
    public function __construct($uri = null, $options = array())
    {
        $this->setUri($uri)
            ->setOptions($options)
            ->connect();
    }

    /**
     * @param string $uri tcp://user:pass@host:port/vhost
     *
     * @return Connection
     * @throws \UnexpectedValueException
     */
    public function setUri($uri)
    {
        if(null === $uri){
            $uri = 'amqp://guest:guest@localhost:5672/';
        }
        $this->_uri = parse_url($uri);
        if(($this->_uri['scheme'] != 'amqp' && $this->_uri['scheme'] != 'amqps')){
            throw new \UnexpectedValueException(
                sprintf(
                    'a valid URI scheme is request expected format [%s] '.
                    'received: [%s]','tcp://user:pass@host:port/vhost',
                    $uri
                )
            );
        }
        if(empty($this->_uri['user']) || empty($this->_uri['pass'])){
            throw new \RuntimeException(
                'AMQPLAIN Auth requires both a user and pass be specified'
            );
        }
        if($this->_uri['scheme'] === 'amqps'){
            if(!extension_loaded('openssl')){
                throw new \RuntimeException(
                    'SSL connection attempted however openssl extension is not loaded'
                );
            }
            $ssl_context = stream_context_create();
            foreach ($this->_options['ssl_options'] as $k => $v) {
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
        $options = (array) $options;
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
        return $this->_options;
    }

    /**
     * @param $option
     * @param $value
     *
     * @return Connection
     */
    public function setOption($option, $value)
    {
        if(array_key_exists($option, $this->_options)){
            $this->_options[$option] = $value;
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
        return array_key_exists($option, $this->_options) ?
            $this->_options[ $option ] : false;
    }

    /**
     * @return \AMQP\Wire\Writer|null|string
     */
    protected function _getLoginResponse()
    {
        if ($this->_uri['user'] && $this->_uri['pass']) {
            $loginResponse = new Writer();
            $loginResponse->writeTable(
                array(
                     "LOGIN" => array( 'S', $this->_uri['user'] ),
                     "PASSWORD" => array( 'S', $this->_uri['pass'] )
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
    protected function _createSocket()
    {
        if($this->_uri['scheme'] === 'amqp'){
            $scheme = 'tcp';
        } elseif($this->_uri['scheme'] === 'amqps'){
            $scheme = 'ssl';
        } else {
            throw new \RuntimeException(
                'Unknown Connection Scheme please select [amqp] or [amqps]'
            );
        }
        $remote = sprintf(
            '%s://%s:%s', $scheme,
            $this->_uri['host'],$this->_uri['port']
        );
        $args = array(
            $remote,
            &$this->_connectionErrorNumber,
            &$this->_connectionError,
            $this->_options['connection_timeout'],
            STREAM_CLIENT_CONNECT
        );
        if($scheme === 'ssl'){
            $ssl_context = stream_context_create();
            foreach ($this->_options['ssl_options'] as $k => $v) {
                stream_context_set_option($ssl_context, 'ssl', $k, $v);
            }
            array_push($args, $ssl_context);
        }
        $this->_sock = call_user_func_array('stream_socket_client', $args);
        if (!$this->_sock) {
            throw new \Exception (
                sprintf(
                    'Error Connecting to server(%s): %s',
                    $this->_connectionErrorNumber, $this->_connectionError
                )
            );
        }

        stream_set_timeout($this->_sock, $this->_options['read_write_timeout']);
        stream_set_blocking($this->_sock, true);
    }

    /**
     *
     */
    protected function connect()
    {
        $login_response = $this->_getLoginResponse();

        while (true) {
            $this->channels = array();
            /*
             * The connection object itself is treated as channel 0
             */
            parent::__construct($this, 0);
            $this->_createSocket();

            $this->_input = new Reader(null, $this->_sock);

            $this->_write(self::$amqpProtocolHeader);
            $this->wait(array( "10,10" ));
            $this->_xStartOk(
                self::$libraryProperties, $this->_options['login_method'],
                $login_response, $this->_options['locale']
            );

            while ($this->_waitTuneOk) {
                $this->wait(
                    array(
                         "10,20", // _secure
                         "10,30", // _tune
                    )
                );
            }
            $vhost = preg_replace('/^\//',null, $this->_uri['path']) ?: '/';
            $host = $this->_xOpen(
                $vhost, '', $this->_options['insist']
            );
            if (!$host) {
                return; // we weren't redirected
            }
            $this->_closeSocket();
            return;
        }
    }

    /**
     * @param string $data
     *
     * @throws \Exception
     */
    protected function _write($data)
    {
        if ($this->_debug) {
            Helper::debugMsg(
                '< [hex]:' . PHP_EOL . Helper::hexdump(
                    $data, false, true, true
                )
            );
        }

        $len = strlen($data);
        while (true) {
            if (false === ($written = fwrite($this->_sock, $data))) {
                throw new \Exception ('Error sending data');
            }
            if ($written === 0) {
                throw new \Exception ('Broken pipe or closed connection');
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
    protected function _doClose()
    {
        if (isset($this->_input) && $this->_input) {
            $this->_input->close();
            $this->_input = null;
        }

        $this->_closeSocket();
    }

    /**
     * @return int
     * @throws \Exception
     */
    public function getFreeChannelId()
    {
        for ($i = 1; $i <= $this->_channelMax; $i++) {
            if (!isset($this->channels[ $i ])) {
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
    public function sendContent($channel, $classId, $weight, $bodySize,
                                 $packedProperties, $body)
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
        $this->_write($outboundPacket);

        while ($body) {
            $payload = substr($body, 0, $this->_frameMax - 8);
            $body = substr($body, $this->_frameMax - 8);
            $outboundPacket = new Writer();

            $outboundPacket->writeOctet(3);
            $outboundPacket->writeShort($channel);
            $outboundPacket->writeLong(strlen($payload));

            $outboundPacket->write($payload);

            $outboundPacket->writeOctet(0xCE);
            $outboundPacket = $outboundPacket->getvalue();
            $this->_write($outboundPacket);
        }
        return $this;
    }

    /**
     * @param string            $channel
     * @param string            $methodSig
     * @param Writer|string $args
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

        $outboundPacket->writeShort($methodSig[ 0 ]); // class_id
        $outboundPacket->writeShort($methodSig[ 1 ]); // method_id
        $outboundPacket->write($args);

        $outboundPacket->writeOctet(0xCE);
        $outboundPacket = $outboundPacket->getvalue();
        $this->_write($outboundPacket);

        if ($this->_debug) {
            Helper::debugMsg(
                '< ' . Helper::methodSig($methodSig) . ': ' .
                AbstractChannel::$globalMethodNames[ Helper::methodSig(
                    $methodSig
                ) ]
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
    protected function _waitFrame()
    {
        $frameType = $this->_input->readOctet();
        $channel = $this->_input->readShort();
        $size = $this->_input->readLong();
        $payload = $this->_input->read($size);

        $ch = $this->_input->readOctet();
        if ($ch != 0xCE) {
            throw new \Exception(
                sprintf(
                    'Framing error, unexpected byte: %x', $ch
                )
            );
        }

        return array( $frameType, $channel, $payload );
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
            list($frameType, $frameChannel, $payload) = $this->_waitFrame();
            if ($frameChannel == $channelId) {
                return array( $frameType, $payload );
            }
            /**
             * Not the channel we were looking for.  Queue this frame
             * for later, when the other channel is looking for frames.
             */
            array_push(
                $this->channels[ $frameChannel ]->frame_queue,
                array( $frameType, $payload )
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
        if (isset($this->channels[ $channelId ])) {
            return $this->channels[ $channelId ];
        } else {
            $channelId = $channelId ?: $this->getFreeChannelId();
            $ch = new Channel($this->_connection, $channelId);
            return $this->channels[ $channelId ] = $ch;
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
    public function close($replyCode = 0, $replyText = '', $methodSig = array( 0, 0 ))
    {
        $args = new Writer();
        $args->writeShort($replyCode);
        $args->writeShortStr($replyText);
        $args->writeShort($methodSig[ 0 ]); // class_id
        $args->writeShort($methodSig[ 1 ]); // method_id
        $this->_sendMethodFrame(array( 10, 60 ), $args);
        return $this->wait(
            array(
                 "10,61", // Connection._close_ok
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

        $this->_xCloseOk();

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
    protected function _xCloseOk()
    {
        $this->_sendMethodFrame(array( 10, 61 ));
        $this->_doClose();
        return $this;
    }

    /**
     * confirm a connection close
     */
    protected function _close_ok($args)
    {
        $this->_doClose();
    }

    /**
     * @param        $virtualHost
     * @param string $capabilities
     * @param bool   $insist
     *
     * @return mixed|null
     */
    protected function _xOpen($virtualHost, $capabilities = '', $insist = false)
    {
        $args = new Writer();
        $args->writeShortStr($virtualHost);
        $args->writeShortStr($capabilities);
        $args->writeBit($insist);
        $this->_sendMethodFrame(array( 10, 40 ), $args);
        return $this->wait(
            array(
                 "10,41", // Connection._openOk
                 "10,50" // Connection._redirect
            )
        );
    }

    /**
     * signal that the connection is ready
     */
    protected function _openOk(Reader $args)
    {
        $this->knownHosts = $args->readShortstr();
        if ($this->_debug) {
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
    protected function _redirect(Reader $args)
    {
        $host = $args->readShortstr();
        $this->knownHosts = $args->readShortstr();
        if ($this->_debug) {
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
    protected function _secure(Reader $args)
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
        $this->_sendMethodFrame(array( 10, 21 ), $args);
    }

    /**
     * _start connection negotiation
     */
    protected function _start(Reader $args)
    {
        $this->_versionMajor = $args->readOctet();
        $this->_versionMinor = $args->readOctet();
        $this->_serverProperties = $args->readTable();
        $this->_mechanisms = explode(" ", $args->readLongstr());
        $this->_locales = explode(" ", $args->readLongstr());

        if ($this->_debug) {
            Helper::debugMsg(
                sprintf(
                    'Start from server, version: %d.%d, properties: %s, '.
                    'mechanisms: %s, locales: %s',
                    $this->_versionMajor,
                    $this->_versionMinor,
                    self::dumpTable($this->_serverProperties),
                    implode(', ', $this->_mechanisms),
                    implode(', ', $this->_locales)
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
    protected function _xStartOk($clientProperties, $mechanism, $response, $locale)
    {
        $args = new Writer();
        $args->writeTable($clientProperties);
        $args->writeShortStr($mechanism);
        $args->writeLongStr($response);
        $args->writeShortStr($locale);
        $this->_sendMethodFrame(array( 10, 11 ), $args);
    }

    /**
     * propose connection tuning parameters
     *
     * @param \AMQP\Wire\Reader $args
     */
    protected function _tune(Reader $args)
    {
        $v = $args->readShort();
        if ($v) {
            $this->_channelMax = $v;
        }

        $v = $args->readLong();

        if ($v) {
            $this->_frameMax = $v;
        }

        $this->heartbeat = $args->readShort();

        $this->_xTuneOk($this->_channelMax, $this->_frameMax, 0);
    }

    /**
     * negotiate connection tuning parameters
     *
     * @param $channelMax
     * @param $frameMax
     * @param $heartbeat
     */
    protected function _xTuneOk($channelMax, $frameMax, $heartbeat)
    {
        $args = new Writer();
        $args->writeShort($channelMax);
        $args->writeLong($frameMax);
        $args->writeShort($heartbeat);
        $this->_sendMethodFrame(array( 10, 31 ), $args);
        $this->_waitTuneOk = False;
    }

    /**
     * get socket from current connection
     *
     * @return null|resource
     */
    public function getSocket()
    {
        return $this->_sock;
    }

    public function __destruct()
    {
        if (isset($this->_input) && $this->_input) {
            $this->close();
        }

        $this->_closeSocket();
    }

    protected function _closeSocket()
    {
        if ($this->_debug) {
            Helper::debugMsg('closing socket');
        }
        if (is_resource($this->_sock)) {
            @fclose($this->_sock);
        }
        $this->_sock = null;
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
            switch ($value[ 0 ]) {
                case 'D':
                    $val = $value[ 1 ]->n . 'E' . $value[ 1 ]->e;
                    break;
                case 'F':
                    $val = '(' . self::dumpTable($value[ 1 ]) . ')';
                    break;
                case 'T':
                    $val = date('Y-m-d H:i:s', $value[ 1 ]);
                    break;
                default:
                    $val = $value[ 1 ];
            }
            $tokens[ ] = $name . '=' . $val;
        }
        return implode(', ', $tokens);
    }
}
