<?php
namespace AMQP;

/**
 *
 */
use AMQP\AbstractChannel;
use AMQP\Exception\ChannelException;
use AMQP\Util\Helper;
use AMQP\Util\FrameBuilder;

/**
 *
 */
class Channel extends AbstractChannel
{

    protected $methodMap
        = array(
            "20,11" => "openOk",
            "20,20" => "flow",
            "20,21" => "flowOk",
            "20,30" => "alert",
            "20,40" => "_close",
            "20,41" => "close_ok",
            "30,11" => "accessRequestOk",
            "40,11" => "exchangeDeclareOk",
            "40,21" => "exchangeDeleteOk",
            "50,11" => "queueDeclareOk",
            "50,21" => "queueBindOk",
            "50,31" => "queuePurgeOk",
            "50,41" => "queueDeleteOk",
            "50,51" => "queueUnbindOk",
            "60,11" => "basicQosOk",
            "60,21" => "basicConsumeOk",
            "60,31" => "basicCancelOk",
            "60,50" => "basicReturn",
            "60,60" => "basicDeliver",
            "60,71" => "basicGetOk",
            "60,72" => "basicGetEmpty",
            "90,11" => "txSelectOk",
            "90,21" => "txCommitOk",
            "90,31" => "txRollbackOk"
        );

    /**
     * @var int
     */
    protected $defaultTicket = 0;

    /**
     * @var bool
     */
    protected $isOpen = false;

    /**
     * @var bool
     */
    protected $active = true;

    /**
     * @var array
     */
    protected $alerts = array();

    /**
     * @var array
     */
    public $callbacks = array();

    /**
     * @var bool
     */
    protected $autoDecode;

    /**
     * @var \AMQP\FrameBuilder
     */
    protected $frameBuilder;

    /**
     * @param Connection $connection
     * @param null       $channelId
     * @param bool       $autoDecode
     */
    public function __construct($connection, $channelId = null, $autoDecode = true)
    {

        $this->frameBuilder = new FrameBuilder();

        if ($channelId == null) {
            $channelId = $connection->getFreeChannelId();
        }

        parent::__construct($connection, $channelId);

        if ($this->debug) {
            Helper::debugMsg(sprintf('using channel_id: %s', $channelId));
        }
        $this->autoDecode = $autoDecode;

        $this->xOpen();
    }

    /**
     * Tear down this object, after we've agreed to close with the server.
     */
    protected function doClose()
    {
        $this->isOpen = false;
        unset($this->connection->channels[$this->channelId]);
        $this->channelId = $this->connection = null;
    }

    /**
     * This method allows the server to send a non-fatal warning to
     * the client.  This is used for methods that are normally
     * asynchronous and thus do not have confirmations, and for which
     * the server may detect errors that need to be reported.  Fatal
     * errors are handled as channel or connection exceptions; non-
     * fatal errors are sent through this method.
     *
     * @param \AMQP\Wire\Reader $args
     */
    protected function alert(\AMQP\Wire\Reader $args)
    {
        $replyCode = $args->readShort();
        $replyText = $args->readShortstr();
        $details = $args->readTable();

        array_push($this->alerts, array($replyCode, $replyText, $details));
    }

    /**
     * @param array $options
     *
     * @return mixed|null
     *
     * close(array(
     *      'reply_code' => 0,
     *      'reply_text' => '',
     *      'method_signature' => array(0,0))
     * )
     */
    public function close($options = array())
    {
        $default = array('reply_code' => 0, 'reply_text' => '', 'method_signature' => array(0,0));
        $options = array_merge($default, $options);
        $args = $this->frameBuilder->channelClose($options);

        $this->sendMethodFrame(array(20, 40), $args);
        return $this->wait(
            array(
                 "20,41" // Channel.close_ok
            )
        );
    }

    /**
     * @param \AMQP\Wire\Reader $args
     *
     * @throws \AMQP\Exception\ChannelException
     */
    protected function _close(\AMQP\Wire\Reader $args)
    {
        $replyCode = $args->readShort();
        $replyText = $args->readShortstr();
        $classId = $args->readShort();
        $methodId = $args->readShort();

        $this->sendMethodFrame(array(20, 41));
        $this->doClose();

        throw new ChannelException($replyCode, $replyText, array($classId, $methodId));
    }

    /**
     * @param Wire\Reader $args
     */
    protected function close_ok(\AMQP\Wire\Reader $args)
    {
        $this->doClose();
    }

    /**
     * @param $active
     *
     * @return mixed|null
     */
    public function flow($active)
    {
        $args = $this->frameBuilder->flow($active);
        $this->sendMethodFrame(array(20, 20), $args);
        return $this->wait(
            array(
                 "20,21" //Channel.flowOk
            )
        );
    }

    /**
     * @param Wire\Reader $args
     */
    protected function _flow(\AMQP\Wire\Reader $args)
    {
        $this->active = $args->readBit();
        $this->xFlowOk($this->active);
    }

    /**
     * @param bool $active
     */
    protected function xFlowOk($active)
    {
        $args = $this->frameBuilder->flow($active);
        $this->sendMethodFrame(array(20, 21), $args);
    }

    /**
     * @param Wire\Reader $args
     *
     * @return bool
     */
    protected function flowOk(\AMQP\Wire\Reader $args)
    {
        return $args->readBit();
    }

    /**
     * @param string $outOfBand
     *
     * @return mixed|null
     */
    protected function xOpen($outOfBand = '')
    {
        if ($this->isOpen) {
            return null;
        }

        $args = $this->frameBuilder->xOpen($outOfBand);
        $this->sendMethodFrame(array(20, 10), $args);
        return $this->wait(
            array(
                 "20,11" //Channel.openOk
            )
        );
    }

    /**
     * @param Wire\Reader $args
     */
    protected function openOk(\AMQP\Wire\Reader $args)
    {
        $this->isOpen = true;
        if ($this->debug) {
            Helper::debugMsg('Channel open');
        }
    }

    /**
     * @param       $realm
     * @param array $options
     *
     * @return mixed|null
     *
     * accessRequest($realm, array(
     *      'exclusive' => false,
     *      'passive' => false,
     *      'active' => false,
     *      'write' => false,
     *      'read' => false)
     * )
     */
    public function accessRequest($realm, $options = array())
    {
        $default = array('exclusive' => false, 'passive' => false, 'active' => false, 'write' => false, 'read' => false);
        $options = array_merge($default, $options);
        $args = $this->frameBuilder->accessRequest($realm, $options);
        $this->sendMethodFrame(array(30, 10), $args);
        return $this->wait(
            array(
                 '30,11' //Channel.accessRequestOk
            )
        );
    }

    /**
     * grant access to server resources
     *
     * @param \AMQP\Wire\Reader $args
     *
     * @return int
     */
    protected function accessRequestOk(\AMQP\Wire\Reader $args)
    {
        $this->defaultTicket = $args->readShort();
        return $this->defaultTicket;
    }

    /**
     * @param       $exchange
     * @param       $type
     * @param array $options
     *
     * @return mixed|null
     *
     * exchangeDeclare($exchange, $type,
     *  array(
     *      'passive'  => false,
     *      'durable' => false,
     *      'auto_delete' => true,
     *      'internal' => false,
     *      'no_wait' => false,
     *      'arguments' => array(),
     *      'ticket' => null)
     * )
     */
    public function exchangeDeclare($exchange, $type, $options = array())
    {
        $defaultArgs = array('passive'  => false, 'durable' => false, 'auto_delete' => true,
                             'internal' => false, 'no_wait' => false, 'arguments' => array(), 'ticket' => null);
        $options = array_merge($defaultArgs, $options);
        $options['arguments'] = $this->getArguments($options['arguments']);
        $options['ticket'] = $this->getTicket($options['ticket']);
        $args = $this->frameBuilder->exchangeDeclare($exchange, $type, $options);
        $this->sendMethodFrame(array(40, 10), $args);

        if (!$options['no_wait']) {
            return $this->wait(
                array(
                     "40,11" //Channel.exchangeDeclareOk
                )
            );
        }
        return null;
    }

    /**
     * confirms an exchange declaration
     *
     * @param \AMQP\Wire\Reader $args
     */
    protected function exchangeDeclareOk(\AMQP\Wire\Reader $args)
    {
    }

    /**
     * @param       $exchange
     * @param array $options
     *
     * @return mixed|null
     *
     *
     * exchangeDelete($exchange, array(
     *      'if_unused' => false,
     *      'no_wait' => false,
     *      'ticket' => null)
     * );
     */
    public function exchangeDelete($exchange, $options=array())
    {
        $default = array('if_unused' => false, 'no_wait' => false, 'ticket' => null);
        $options = array_merge($default, $options);
        $options['ticket'] = $this->getTicket($options['ticket']);
        $args = $this->frameBuilder->exchangeDelete($exchange, $options);
        $this->sendMethodFrame(array(40, 20), $args);

        if (!$options['no_wait']) {
            return $this->wait(
                array(
                     "40,21" //Channel.exchangeDeleteOk
                )
            );
        }
        return null;
    }

    /**
     * confirm deletion of an exchange
     *
     * @param Wire\Reader $args
     */
    protected function exchangeDeleteOk(\AMQP\Wire\Reader $args)
    {
    }

    /**
     * @param       $queue
     * @param       $exchange
     * @param array $options
     *
     * @return mixed|null
     *
     *
     * queueBind($queue, $exchange,
     *      array(
     *          'routing_key' => '',
     *          'no_wait' => false,
     *          'arguments' => array(),
     *          'ticket' => null
     *      )
     * )
     */
    public function queueBind($queue, $exchange, $options=array())
    {
        $default = array('routing_key' => '', 'no_wait' => false, 'arguments' => array(), 'ticket' => null);
        $options = array_merge($default, $options);
        $options['arguments'] = $this->getArguments($options['arguments']);
        $options['ticket'] = $this->getTicket($options['ticket']);

        $args = $this->frameBuilder->queueBind($queue, $exchange, $options);

        $this->sendMethodFrame(array(50, 20), $args);

        if (!$options['no_wait']) {
            return $this->wait(
                array(
                     "50,21" // Channel.queueBindOk
                )
            );
        }
        return null;
    }

    /**
     * confirm bind successful
     *
     * @param \AMQP\Wire\Reader $args
     */
    protected function queueBindOk(\AMQP\Wire\Reader $args)
    {
    }

    /**
     * unbind queue from an exchange
     *
     * @param        $queue
     * @param        $exchange
     * @param array  $options
     * @return mixed|null
     *
     * queueUnbind($queue, $exchange, array('routing_key' => '', 'arguments' => null, 'ticket' => null));
     */
    public function queueUnbind($queue, $exchange, $options = array())
    {
        $default = array('routing_key' => '', 'arguments' => null, 'ticket' => null);
        $options = array_merge($default, $options);
        $options['arguments'] = $this->getArguments($options['arguments']);
        $options['ticket'] = $this->getTicket($options['ticket']);

        $args = $this->frameBuilder->queueUnbind($queue, $exchange, $options);

        $this->sendMethodFrame(array(50, 50), $args);

        return $this->wait(
            array(
                 "50,51" // Channel.queueUnbindOk
            )
        );
    }

    /**
     * confirm unbind successful
     *
     * @param \AMQP\Wire\Reader $args
     */
    protected function queueUnbindOk(\AMQP\Wire\Reader $args)
    {
    }

    /**
     * @param array $options
     *
     * @return mixed|null
     *
     * queueDeclare(array(
     *      'queue' => '',
     *      'passive' => false,
     *      'durable' => false,
     *      'exclusive' => false,
     *      'auto_delete' => true,
     *      'no_wait' => false,
     *      'arguments' => array(),
     *      'ticket' => null
     * ));
     */
    public function queueDeclare($options = array())
    {
        $default = array('queue' => '', 'passive' => false, 'durable' => false, 'exclusive' => false,
        'auto_delete' => true, 'no_wait' => false, 'arguments' => array(), 'ticket' => null);
        $options = array_merge($default, $options);
        $options['arguments'] = $this->getArguments($options['arguments']);
        $options['ticket'] = $this->getTicket($options['ticket']);
        $args = $this->frameBuilder->queueDeclare($options);
        $this->sendMethodFrame(array(50, 10), $args);

        if (!$options['no_wait']) {
            return $this->wait(
                array(
                     "50,11" // Channel.queueDeclareOk
                )
            );
        }
        return null;
    }

    /**
     * confirms a queue definition
     *
     * @param \AMQP\Wire\Reader $args
     *
     * @return array
     */
    protected function queueDeclareOk(\AMQP\Wire\Reader $args)
    {
        $queue = $args->readShortstr();
        $messageCount = $args->readLong();
        $consumerCount = $args->readLong();

        return array($queue, $messageCount, $consumerCount);
    }

    /**
     * @param array $options
     *
     * @return mixed|null
     *
     * queueDelete(array(
     *      'queue' => '',
     *      'if_unused' => false,
     *      'if_empty' => false,
     *      'no_wait' => false,
     *      'ticket' => null)
     * )
     */
    public function queueDelete($options = array())
    {
        $default = array('queue' => '', 'if_unused' => false, 'if_empty' => false, 'no_wait' => false, 'ticket' => null);
        $options = array_merge($default, $options);
        $options['ticket'] = $this->getTicket($options['ticket']);

        $args = $this->frameBuilder->queueDelete($options);

        $this->sendMethodFrame(array(50, 40), $args);

        if (!$options['no_wait']) {
            return $this->wait(
                array(
                     "50,41" //Channel.queueDeleteOk
                )
            );
        }
        return null;
    }

    /**
     * confirm deletion of a queue
     *
     * @param \AMQP\Wire\Reader $args
     *
     * @return string
     */
    protected function queueDeleteOk(\AMQP\Wire\Reader $args)
    {
        return $args->readLong();
    }

    /**
     * @param array $options
     *
     * @return mixed|null
     *
     * queuePurge(array(
     *      'queue' => '',
     *      'no_wait' => false,
     *      'ticket' => null)
     * )
     */
    public function queuePurge($options = array())
    {
        $default = array('queue' => '', 'no_wait' => false, 'ticket' => null);
        $options = array_merge($default, $options);
        $options['ticket'] = $this->getTicket($options['ticket']);
        $args = $this->frameBuilder->queuePurge($options);

        $this->sendMethodFrame(array(50, 30), $args);

        if (!$options['no_wait']) {
            return $this->wait(
                array(
                     "50,31" //Channel.queuePurgeOk
                )
            );
        }
        return null;
    }

    /**
     * confirms a queue purge
     *
     * @param \AMQP\Wire\Reader $args
     *
     * @return string
     */
    protected function queuePurgeOk(\AMQP\Wire\Reader $args)
    {
        return $args->readLong();
    }

    /**
     * @param      $delivery_tag
     * @param bool $multiple
     */
    public function basicAck($delivery_tag, $multiple = false)
    {
        $args = $this->frameBuilder->basicAck($delivery_tag, $multiple);
        $this->sendMethodFrame(array(60, 80), $args);
    }

    /**
     * end a queue consumer
     *
     * @param      $consumerTag
     * @param bool $nowait
     *
     * @return mixed|null
     */
    public function basicCancel($consumerTag, $nowait = false)
    {
        $args = $this->frameBuilder->basicCancel($consumerTag, $nowait);
        $this->sendMethodFrame(array(60, 30), $args);
        return $this->wait(
            array(
                 "60,31" // Channel.basicCancelOk
            )
        );
    }

    /**
     * @param Wire\Reader $args
     */
    protected function basicCancelOk(\AMQP\Wire\Reader $args)
    {
        $consumer_tag = $args->readShortstr();
        unset($this->callbacks[$consumer_tag]);
    }

    /**
     * @param array $options
     *
     * @return mixed|null
     *
     * basicConsume(
     *  array(
     *      'queue' => '',
     *      'consumer_tag' => '',
     *      'no_local' => false,
     *      'no_ack' => false,
     *      'exclusive' => false,
     *      'no_wait' => false,
     *      'callback' => null,
     *      'ticket' => null
     *  )
     * )
     */
    public function basicConsume($options = array())
    {
        $consumerTag = null;
        $default = array('queue' => '', 'consumer_tag' => '', 'no_local' => false, 'no_ack' => false, 'exclusive' => false,
                         'no_wait' => false, 'callback' => null, 'ticket' => null);
        $options = array_merge($default, $options);
        $options['ticket'] = $this->getTicket($options['ticket']);
        $args = $this->frameBuilder->basicConsume($options);

        $this->sendMethodFrame(array(60, 20), $args);

        if (!$options['no_wait']) {
            $consumerTag = $this->wait(
                array(
                     "60,21" //Channel.basicConsumeOk
                )
            );
        }

        $this->callbacks[$consumerTag] = $options['callback'];
        return $consumerTag;
    }

    /**
     * confirm a new consumer
     *
     * @param \AMQP\Wire\Reader $args
     *
     * @return string
     */
    protected function basicConsumeOk(\AMQP\Wire\Reader $args)
    {
        return $args->readShortstr();
    }

    /**
     * @param Wire\Reader $args
     * @param Message     $msg
     */
    protected function basicDeliver(\AMQP\Wire\Reader $args, \AMQP\Message $msg)
    {
        $consumerTag = $args->readShortstr();
        $deliveryTag = $args->readLonglong();
        $redelivered = $args->readBit();
        $exchange = $args->readShortstr();
        $routingKey = $args->readShortstr();

        $msg->delivery_info = array(
            "channel"      => $this,
            "consumer_tag" => $consumerTag,
            "delivery_tag" => $deliveryTag,
            "redelivered"  => $redelivered,
            "exchange"     => $exchange,
            "routing_key"  => $routingKey
        );
        $callback = null;
        if (isset($this->callbacks[$consumerTag])) {
            $callback = $this->callbacks[$consumerTag];
        }

        if ($callback != null && is_callable($callback)) {
            call_user_func($callback, $msg);
        }
    }

    /**
     * @param array $options
     *
     * @return mixed|null
     *
     * basicGet(
     *  array(
     *      'queue' => '',
     *      'no_ack' => false,
     *      'ticket' => null
     *  )
     * )
     */
    public function basicGet($options = array())
    {
        $default = array('queue' => '', 'no_ack' => false, 'ticket' => null);
        $options = array_merge($default, $options);
        $options['ticket'] = $this->getTicket($options['ticket']);
        $args = $this->frameBuilder->basicGet($options);

        $this->sendMethodFrame(array(60, 70), $args);
        return $this->wait(
            array(
                 "60,71", //Channel.basicGetOk
                 "60,72" // Channel.basicGetEmpty
            )
        );
    }

    /**
     * indicate no messages available
     *
     * @param \AMQP\Wire\Reader $args
     */
    protected function basicGetEmpty(\AMQP\Wire\Reader $args)
    {
        $cluster_id = $args->readShortstr();
    }

    /**
     * @param Wire\Reader $args
     * @param Message     $msg
     *
     * @return Message
     */
    protected function basicGetOk(\AMQP\Wire\Reader $args, \AMQP\Message $msg)
    {
        $deliveryTag = $args->readLonglong();
        $redelivered = $args->readBit();
        $exchange = $args->readShortstr();
        $routingKey = $args->readShortstr();
        $messageCount = $args->readLong();

        $msg->delivery_info = array(
            "delivery_tag"  => $deliveryTag,
            "redelivered"   => $redelivered,
            "exchange"      => $exchange,
            "routing_key"   => $routingKey,
            "message_count" => $messageCount
        );
        return $msg;
    }

    /**
     * @param Message $msg
     * @param array   $options
     *
     * basicPublish($msg,
     *  array(
     *      'exchange' => '',
     *      'routing_key' => '',
     *      'mandatory' => false,
     *      'immediate' => false,
     *      'ticket' => null)
     * )
     */
    public function basicPublish( \AMQP\Message $msg, $options = array())
    {
        $default = array('exchange' => '', 'routing_key' => '', 'mandatory' => false, 'immediate' => false,
                         'ticket' => null);
        $options = array_merge($default, $options);
        $options['ticket'] = $this->getTicket($options['ticket']);
        $args = $this->frameBuilder->basicPublish($options);

        $this->sendMethodFrame(array(60, 40), $args);

        $this->connection->sendContent(
            $this->channelId, 60, 0,
            strlen($msg->body),
            $msg->serializeProperties(),
            $msg->body
        );
    }

    /**
     * specify quality of service
     *
     * @param $prefetchSize
     * @param $prefetchCount
     * @param $aGlobal
     *
     * @return mixed|null
     */
    public function basicQos($prefetchSize, $prefetchCount, $aGlobal)
    {
        $args = $this->frameBuilder->basicQos(
            $prefetchSize, $prefetchCount, $aGlobal
        );
        $this->sendMethodFrame(array(60, 10), $args);
        return $this->wait(
            array(
                 "60,11" //Channel.basicQosOk
            )
        );
    }

    /**
     * confirm the requested qos
     *
     * @param \AMQP\Wire\Reader $args
     */
    protected function basicQosOk(\AMQP\Wire\Reader $args)
    {
    }

    /**
     * redeliver unacknowledged messages
     *
     * @param bool $reQueue
     */
    public function basicRecover($reQueue = false)
    {
        $args = $this->frameBuilder->basicRecover($reQueue);
        $this->sendMethodFrame(array(60, 100), $args);
    }

    /**
     * reject an incoming message
     *
     * @param $deliveryTag
     * @param $reQueue
     */
    public function basicReject($deliveryTag, $reQueue)
    {
        $args = $this->frameBuilder->basicReject($deliveryTag, $reQueue);
        $this->sendMethodFrame(array(60, 90), $args);
    }

    /**
     * return a failed message
     *
     * @param \AMQP\Wire\Reader $args
     */
    protected function basicReturn(\AMQP\Wire\Reader $args)
    {
        $reply_code = $args->readShort();
        $reply_text = $args->readShortstr();
        $exchange = $args->readShortstr();
        $routing_key = $args->readShortstr();
        $msg = $this->wait();
    }

    /**
     * @return mixed|null
     */
    public function txCommit()
    {
        $this->sendMethodFrame(array(90, 20));
        return $this->wait(
            array(
                 "90,21" //Channel.txCommitOk
            )
        );
    }

    /**
     * confirm a successful commit
     *
     * @param \AMQP\Wire\Reader $args
     */
    protected function txCommitOk(\AMQP\Wire\Reader $args)
    {
    }

    /**
     * abandon the current transaction
     *
     * @return mixed|null
     */
    public function txRollback()
    {
        $this->sendMethodFrame(array(90, 30));
        return $this->wait(
            array(
                 "90,31" //Channel.txRollbackOk
            )
        );
    }

    /**
     * confirm a successful rollback
     *
     * @param \AMQP\Wire\Reader $args
     */
    protected function txRollbackOk(\AMQP\Wire\Reader $args)
    {
    }

    /**
     * select standard transaction mode
     *
     * @return mixed|null
     */
    public function txSelect()
    {
        $this->sendMethodFrame(array(90, 10));
        return $this->wait(
            array(
                 "90,11" //Channel.txSelectOk
            )
        );
    }

    /**
     * confirm transaction mode
     *
     * @param \AMQP\Wire\Reader $args
     */
    protected function txSelectOk(\AMQP\Wire\Reader $args)
    {
    }

    /**
     * @param array $arguments
     *
     * @return array
     */
    protected function getArguments($arguments)
    {
        return (null === $arguments) ? array() : $arguments;
    }

    /**
     * @param $ticket
     *
     * @return int
     */
    protected function getTicket($ticket)
    {
        return (null === $ticket) ? $this->defaultTicket : $ticket;
    }
}
