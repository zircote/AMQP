<?php
namespace AMQP;

/**
 *
 */
use AMQP\Util\Helper;
use AMQP\Wire\Reader;
use AMQP\Message;

/**
 *
 */
class AbstractChannel
{

    private static $contentMethods
        = array(
            "60,60", // Basic.deliver
            "60,71", // Basic.get_ok
        );

    private static $closeMethods
        = array(
            "10,60", // Connection.close
            "20,40", // Channel.close
        );

    // All the method names
    public static $globalMethodNames
        = array(
            "10,10"  => "Connection.start",
            "10,11"  => "Connection.start_ok",
            "10,20"  => "Connection.secure",
            "10,21"  => "Connection.secure_ok",
            "10,30"  => "Connection.tune",
            "10,31"  => "Connection.tune_ok",
            "10,40"  => "Connection.open",
            "10,41"  => "Connection.openOk",
            "10,50"  => "Connection.redirect",
            "10,60"  => "Connection.close",
            "10,61"  => "Connection.close_ok",
            "20,10"  => "Channel.open",
            "20,11"  => "Channel.openOk",
            "20,20"  => "Channel.flow",
            "20,21"  => "Channel.flowOk",
            "20,30"  => "Channel.alert",
            "20,40"  => "Channel.close",
            "20,41"  => "Channel.close_ok",
            "30,10"  => "Channel.accessRequest",
            "30,11"  => "Channel.accessRequestOk",
            "40,10"  => "Channel.exchangeDeclare",
            "40,11"  => "Channel.exchangeDeclareOk",
            "40,20"  => "Channel.exchangeDelete",
            "40,21"  => "Channel.exchangeDeleteOk",
            "50,10"  => "Channel.queueDeclare",
            "50,11"  => "Channel.queueDeclareOk",
            "50,20"  => "Channel.queueBind",
            "50,21"  => "Channel.queueBindOk",
            "50,30"  => "Channel.queuePurge",
            "50,31"  => "Channel.queuePurgeOk",
            "50,40"  => "Channel.queueDelete",
            "50,41"  => "Channel.queueDeleteOk",
            "50,50"  => "Channel.queueUnbind",
            "50,51"  => "Channel.queueUnbindOk",
            "60,10"  => "Channel.basicQos",
            "60,11"  => "Channel.basicQosOk",
            "60,20"  => "Channel.basicConsume",
            "60,21"  => "Channel.basicConsumeOk",
            "60,30"  => "Channel.basicCancel",
            "60,31"  => "Channel.basicCancelOk",
            "60,40"  => "Channel.basicPublish",
            "60,50"  => "Channel.basicReturn",
            "60,60"  => "Channel.basicDeliver",
            "60,70"  => "Channel.basicGet",
            "60,71"  => "Channel.basicGetOk",
            "60,72"  => "Channel.basicGetEmpty",
            "60,80"  => "Channel.basicAck",
            "60,90"  => "Channel.basicReject",
            "60,100" => "Channel.basicRecover",
            "90,10"  => "Channel.txSelect",
            "90,11"  => "Channel.txSelectOk",
            "90,20"  => "Channel.txCommit",
            "90,21"  => "Channel.txCommitOk",
            "90,30"  => "Channel.txRollback",
            "90,31"  => "Channel.txRollbackOk"
        );

    protected $methodMap;

    /**
     * @var bool
     */
    protected $debug;

    /**
     * @var \AMQP\Connection
     */
    protected $connection;

    /**
     * @var string
     */
    protected $channelId;

    /**
     * @var array
     */
    protected $frameQueue = array();

    /**
     * @var array
     */
    protected $methodQueue = array();

    /**
     * @var bool
     */
    protected $autoDecode = false;

    /**
     * @param \AMQP\Connection                $connection
     * @param string                          $channelId
     */
    public function __construct(\AMQP\Connection $connection, $channelId)
    {
        $this->connection = $connection;
        $this->channelId = $channelId;
        $connection->channels[$channelId] = $this;
        $this->debug = defined('AMQP_DEBUG') ? AMQP_DEBUG : false;
    }

    /**
     * @return string
     */
    public function getChannelId()
    {
        return $this->channelId;
    }

    /**
     * @todo tie in an event handler interface and decouple.
     *
     * @param $methodSig
     * @param $args
     * @param $content
     *
     * @return mixed
     * @throws \Exception
     */
    public function dispatch($methodSig, $args, $content)
    {
        if (!array_key_exists($methodSig, $this->methodMap)) {
            throw new \Exception("Unknown AMQP method $methodSig");
        }

        $amqpMethod = $this->methodMap[$methodSig];

        if ($content == null) {
            return call_user_func(array($this, $amqpMethod), $args);
        } else {
            return call_user_func(
                array($this, $amqpMethod), $args, $content
            );
        }
    }

    /**
     * @return array|mixed
     */
    public function nextFrame()
    {
        if ($this->debug) {
            Helper::debugMsg("waiting for a new frame");
        }

        if (!empty($this->frameQueue)) {
            return array_pop($this->frameQueue);
        }

        return $this->connection->waitChannel($this->channelId);
    }

    /**
     * @param string $methodSig
     * @param string $args
     */
    protected function sendMethodFrame($methodSig, $args = '')
    {
        $this->connection->sendChannelMethodFrame(
            $this->channelId, $methodSig, $args
        );
    }

    /**
     * @return \AMQP\Message
     * @throws \Exception
     */
    public function waitContent()
    {
        $frm = $this->nextFrame();
        $frameType = $frm[0];
        $payload = $frm[1];

        if ($frameType != 2) {
            throw new \Exception("Expecting Content header");
        }

        $payloadReader = new Reader(substr($payload, 0, 12));
        $classId = $payloadReader->readShort();
        $weight = $payloadReader->readShort();

        $bodySize = $payloadReader->readLonglong();
        $msg = new Message();
        $msg->load_properties(substr($payload, 12));

        $bodyParts = array();
        $bodyReceived = 0;
        while (bccomp($bodySize, $bodyReceived) == 1) {
            $frm = $this->nextFrame();
            $frameType = $frm[0];
            $payload = $frm[1];
            /*
             * @todo add constants for the frameType identification
             */
            if ($frameType != 3) {
                throw new \Exception(
                    sprintf(
                        'Expecting Content body, received frame type %s',
                        $frameType
                    )
                );
            }

            $bodyParts[] = $payload;
            $bodyReceived = bcadd($bodyReceived, strlen($payload));
        }

        $msg->body = implode('', $bodyParts);

        if ($this->autoDecode && isset($msg->content_encoding)) {
            try {
                $msg->body = $msg->decode();
            } catch (\Exception $e) {
                if ($this->debug) {
                    Helper::debugMsg(
                        sprintf(
                            'Ignoring body decoding exception: %s',
                            $e->getMessage()
                        )
                    );
                }
            }
        }

        return $msg;
    }

    /**
     * @todo refactor
     *
     * Wait for some expected AMQP methods and dispatch to them.
     * Unexpected methods are queued up for later calls to this PHP
     * method.
     *
     * @param array $allowedMethods
     * @param bool  $nonBlocking
     *
     * @return mixed|null
     * @throws \Exception
     */
    public function wait($allowedMethods = array(), $nonBlocking = false)
    {
        if ($allowedMethods) {
            if ($this->debug) {
                Helper::debugMsg(
                    sprintf('waiting for %s', implode(', ', $allowedMethods))
                );
            }
        } else {
            if ($this->debug) {
                Helper::debugMsg('waiting for any method');
            }
        }

        //Process deferred methods
        foreach ($this->methodQueue as $queueKey => $queuedMethod) {
            if ($this->debug) {
                Helper::debugMsg(
                    sprintf('checking queue method %s', $queueKey)
                );
            }

            $methodSig = $queuedMethod[0];
            if ($allowedMethods == null
                || in_array($methodSig, $allowedMethods)
            ) {
                unset($this->methodQueue[$queueKey]);

                if ($this->debug) {
                    Helper::debugMsg(
                        sprintf(
                            'Executing queued method: $methodSig: %s',
                            self::$globalMethodNames[Helper::methodSig(
                                $methodSig
                            )]
                        )
                    );
                }

                return $this->dispatch(
                    $queuedMethod[0],
                    $queuedMethod[1],
                    $queuedMethod[2]
                );
            }
        }

        // No deferred methods?  wait for new ones
        while (true) {
            $frame = $this->nextFrame();
            $frameType = $frame[0];
            $payload = $frame[1];

            if ($frameType != 1) {
                throw new \Exception(
                    sprintf(
                        'Expecting AMQP method, received frame type: %s',
                        $frameType
                    )
                );
            }

            if (strlen($payload) < 4) {
                throw new \Exception('Method frame too short');
            }

            $methodSigArray = unpack('n2', substr($payload, 0, 4));
            $methodSig
                = '' . $methodSigArray[1] . ',' . $methodSigArray[2];
            $args = new Reader(substr($payload, 4));

            if ($this->debug) {
                Helper::debugMsg(
                    '> ' . $methodSig . ': ' .
                        self::$globalMethodNames[Helper::methodSig($methodSig)]
                );
            }

            if (in_array($methodSig, self::$contentMethods)) {
                $content = $this->waitContent();
            } else {
                $content = null;
            }

            if ($allowedMethods == null
                || in_array($methodSig, $allowedMethods)
                || in_array($methodSig, self::$closeMethods)
            ) {
                return $this->dispatch($methodSig, $args, $content);
            }

            // Wasn't what we were looking for? save it for later
            if ($this->debug) {
                Helper::debugMsg(
                    sprintf(
                        'Queueing for later: %s: %s', $methodSig,
                        self::$globalMethodNames[Helper::methodSig(
                            $methodSig
                        )]
                    )
                );
            }
            $this->methodQueue[] = array($methodSig, $args, $content);

            if ($nonBlocking) {
                break;
            }
        }
        return null;
    }
}
