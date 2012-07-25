<?php
namespace AMQP;
/**
 *
 */
use AMQP\AbstractChannel;
use AMQP\Exception\ChannelException;
use AMQP\Helper;
use AMQP\FrameBuilder;

/**
 *
 */
class Channel extends AbstractChannel
{

    protected $_methodMap = array(
        "20,11" => "_openOk",
        "20,20" => "flow",
        "20,21" => "_flowOk",
        "20,30" => "_alert",
        "20,40" => "_close",
        "20,41" => "_close_ok",
        "30,11" => "_accessRequestOk",
        "40,11" => "_exchangeDeclareOk",
        "40,21" => "_exchangeDeleteOk",
        "50,11" => "_queueDeclareOk",
        "50,21" => "_queueBindOk",
        "50,31" => "_queuePurgeOk",
        "50,41" => "_queueDeleteOk",
        "50,51" => "_queueUnbindOk",
        "60,11" => "_basicQosOk",
        "60,21" => "_basicConsumeOk",
        "60,31" => "_basicCancelOk",
        "60,50" => "_basicReturn",
        "60,60" => "_basicDeliver",
        "60,71" => "_basicGetOk",
        "60,72" => "_basicGetEmpty",
        "90,11" => "_txSelectOk",
        "90,21" => "_txCommitOk",
        "90,31" => "_txRollbackOk"
    );

    /**
     * @var int
     */
    protected $_defaultTicket = 0;

    /**
     * @var bool
     */
    protected $_isOpen = false;

    /**
     * @var bool
     */
    protected $_active = true;

    /**
     * @var array
     */
    protected $_alerts = array();

    /**
     * @var array
     */
    public $callbacks = array();

    /**
     * @var bool
     */
    protected $_autoDecode;

    /**
     * @var \AMQP\FrameBuilder
     */
    protected $frameBuilder;

    public function __construct($connection, $channelId = null,
                                $autoDecode = true)
    {

        $this->frameBuilder = new FrameBuilder();

        if ($channelId == null) {
            $channelId = $connection->getFreeChannelId();
        }

        parent::__construct($connection, $channelId);

        if ($this->_debug) {
            Helper::debugMsg(sprintf('using channel_id: %s', $channelId));
        }
        $this->_autoDecode = $autoDecode;

        $this->_xOpen();
    }

    /**
     * Tear down this object, after we've agreed to close with the server.
     */
    protected function _doClose()
    {
        $this->_isOpen = false;
        unset($this->_connection->channels[ $this->_channelId ]);
        $this->_channelId = $this->_connection = null;
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
    protected function _alert(\AMQP\Wire\Reader $args)
    {
        $replyCode = $args->readShort();
        $replyText = $args->readShortstr();
        $details = $args->readTable();

        array_push($this->_alerts, array( $replyCode, $replyText, $details ));
    }

    /**
     * request a channel close
     *
     * @param int    $replyCode
     * @param string $replyText
     * @param array  $methodSig
     *
     * @return mixed|null
     */
    public function close($replyCode = 0, $replyText = '',
                          $methodSig = array( 0, 0 ))
    {
        $args = $this->frameBuilder->channelClose(
            $replyCode,
            $replyText,
            $methodSig[ 0 ],
            $methodSig[ 1 ]
        );

        $this->_sendMethodFrame(array( 20, 40 ), $args);
        return $this->wait(
            array(
                 "20,41" // Channel._close_ok
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

        $this->_sendMethodFrame(array( 20, 41 ));
        $this->_doClose();

        throw new ChannelException($replyCode, $replyText,
                                       array( $classId, $methodId ));
    }

    /**
     * @param \AMQP\Wire\Reader $args
     */
    protected function _close_ok(\AMQP\Wire\Reader $args)
    {
        $this->_doClose();
    }

    /**
     * @param bool $active
     *
     * @return mixed|null
     */
    public function flow($active)
    {
        $args = $this->frameBuilder->flow($active);
        $this->_sendMethodFrame(array( 20, 20 ), $args);
        return $this->wait(
            array(
                 "20,21" //Channel._flowOk
            )
        );
    }

    /**
     * @param \AMQP\Wire\Reader $args
     */
    protected function _flow(\AMQP\Wire\Reader $args)
    {
        $this->_active = $args->readBit();
        $this->_xFlowOk($this->_active);
    }

    /**
     * @param bool $active
     */
    protected function _xFlowOk($active)
    {
        $args = $this->frameBuilder->flow($active);
        $this->_sendMethodFrame(array( 20, 21 ), $args);
    }

    /**
     * @param \AMQP\Wire\Reader $args
     *
     * @return bool
     */
    protected function _flowOk(\AMQP\Wire\Reader $args)
    {
        return $args->readBit();
    }

    /**
     * @param string $outOfBand
     *
     * @return mixed|null
     */
    protected function _xOpen($outOfBand = '')
    {
        if ($this->_isOpen) {
            return null;
        }

        $args = $this->frameBuilder->xOpen($outOfBand);
        $this->_sendMethodFrame(array( 20, 10 ), $args);
        return $this->wait(
            array(
                 "20,11" //Channel._openOk
            )
        );
    }

    /**
     * @param \AMQP\Wire\Reader $args
     */
    protected function _openOk(\AMQP\Wire\Reader $args)
    {
        $this->_isOpen = true;
        if ($this->_debug) {
            Helper::debugMsg('Channel open');
        }
    }

    /**
     * request an access ticket
     *
     * @param string $realm
     * @param bool   $exclusive
     * @param bool   $passive
     * @param bool   $active
     * @param bool   $write
     * @param bool   $read
     *
     * @return mixed|null
     */
    public function accessRequest($realm, $exclusive = false,
                                   $passive = false, $active = false,
                                   $write = false, $read = false)
    {
        $args = $this->frameBuilder->accessRequest(
            $realm, $exclusive,$passive, $active,$write, $read
        );
        $this->_sendMethodFrame(array( 30, 10 ), $args);
        return $this->wait(
            array(
                 '30,11' //Channel._accessRequestOk
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
    protected function _accessRequestOk(\AMQP\Wire\Reader $args)
    {
        $this->_defaultTicket = $args->readShort();
        return $this->_defaultTicket;
    }

    /**
     * declare exchange, create if needed
     *
     * @param      $exchange
     * @param      $type
     * @param bool $passive
     * @param bool $durable
     * @param bool $auto_delete
     * @param bool $internal
     * @param bool $nowait
     * @param null $arguments
     * @param null $ticket
     *
     * @return mixed|null
     */
    public function exchangeDeclare($exchange,
                                     $type,
                                     $passive = false,
                                     $durable = false,
                                     $auto_delete = true,
                                     $internal = false,
                                     $nowait = false,
                                     $arguments = null,
                                     $ticket = null)
    {

        $arguments = $this->_getArguments($arguments);
        $ticket = $this->_getTicket($ticket);

        $args = $this->frameBuilder->exchangeDeclare(
            $exchange, $type, $passive, $durable, $auto_delete,
            $internal, $nowait,$arguments, $ticket
        );

        $this->_sendMethodFrame(array( 40, 10 ), $args);

        if (!$nowait) {
            return $this->wait(
                array(
                     "40,11" //Channel._exchangeDeclareOk
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
    protected function _exchangeDeclareOk(\AMQP\Wire\Reader $args)
    {
    }

    /**
     * delete an exchange
     *
     * @param      $exchange
     * @param bool $if_unused
     * @param bool $nowait
     * @param null $ticket
     *
     * @return mixed|null
     */
    public function exchangeDelete($exchange, $if_unused = false,
                                    $nowait = false, $ticket = null)
    {
        $ticket = $this->_getTicket($ticket);
        $args = $this->frameBuilder->exchangeDelete(
            $exchange, $if_unused, $nowait, $ticket
        );
        $this->_sendMethodFrame(array( 40, 20 ), $args);

        if (!$nowait) {
            return $this->wait(
                array(
                     "40,21" //Channel._exchangeDeleteOk
                )
            );
        }
        return null;
    }

    /**
     * confirm deletion of an exchange
     *
     * @param \AMQP\Wire\Reader $args
     */
    protected function _exchangeDeleteOk(\AMQP\Wire\Reader $args)
    {
    }

    /**
     * bind queue to an exchange
     *
     * @param        $queue
     * @param        $exchange
     * @param string $routing_key
     * @param bool   $nowait
     * @param null   $arguments
     * @param null   $ticket
     *
     * @return mixed|null
     */
    public function queueBind($queue, $exchange, $routing_key = '',
                               $nowait = false, $arguments = null,
                               $ticket = null)
    {
        $arguments = $this->_getArguments($arguments);
        $ticket = $this->_getTicket($ticket);

        $args = $this->frameBuilder->queueBind(
            $queue, $exchange, $routing_key, $nowait, $arguments, $ticket
        );

        $this->_sendMethodFrame(array( 50, 20 ), $args);

        if (!$nowait) {
            return $this->wait(
                array(
                     "50,21" // Channel._queueBindOk
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
    protected function _queueBindOk(\AMQP\Wire\Reader $args)
    {
    }

    /**
     * unbind queue from an exchange
     *
     * @param        $queue
     * @param        $exchange
     * @param string $routing_key
     * @param null   $arguments
     * @param null   $ticket
     *
     * @return mixed|null
     */
    public function queueUnbind($queue, $exchange, $routing_key = '',
                                 $arguments = null, $ticket = null)
    {
        $arguments = $this->_getArguments($arguments);
        $ticket = $this->_getTicket($ticket);

        $args = $this->frameBuilder->queueUnbind(
            $queue, $exchange, $routing_key, $arguments, $ticket
        );

        $this->_sendMethodFrame(array( 50, 50 ), $args);

        return $this->wait(
            array(
                 "50,51" // Channel._queueUnbindOk
            )
        );
    }

    /**
     * confirm unbind successful
     *
     * @param \AMQP\Wire\Reader $args
     */
    protected function _queueUnbindOk(\AMQP\Wire\Reader $args)
    {
    }

    /**
     * declare queue, create if needed
     *
     * @param string $queue
     * @param bool   $passive
     * @param bool   $durable
     * @param bool   $exclusive
     * @param bool   $auto_delete
     * @param bool   $nowait
     * @param null   $arguments
     * @param null   $ticket
     *
     * @return mixed|null
     */
    public function  queueDeclare($queue = '',
                                   $passive = false,
                                   $durable = false,
                                   $exclusive = false,
                                   $auto_delete = true,
                                   $nowait = false,
                                   $arguments = null,
                                   $ticket = null)
    {
        $arguments = $this->_getArguments($arguments);
        $ticket = $this->_getTicket($ticket);

        $args = $this->frameBuilder->queueDeclare(
            $queue, $passive, $durable,
            $exclusive, $auto_delete, $nowait,
            $arguments, $ticket
        );
        $this->_sendMethodFrame(array( 50, 10 ), $args);

        if (!$nowait) {
            return $this->wait(
                array(
                     "50,11" // Channel._queueDeclareOk
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
    protected function _queueDeclareOk(\AMQP\Wire\Reader $args)
    {
        $queue = $args->readShortstr();
        $messageCount = $args->readLong();
        $consumerCount = $args->readLong();

        return array( $queue, $messageCount, $consumerCount );
    }

    /**
     * delete a queue
     *
     * @param string $queue
     * @param bool   $if_unused
     * @param bool   $if_empty
     * @param bool   $nowait
     * @param null   $ticket
     *
     * @return mixed|null
     */
    public function queueDelete($queue = '', $if_unused = false,
                                 $if_empty = false,
                                 $nowait = false, $ticket = null)
    {
        $ticket = $this->_getTicket($ticket);

        $args = $this->frameBuilder->queueDelete(
            $queue, $if_unused, $if_empty, $nowait, $ticket
        );

        $this->_sendMethodFrame(array( 50, 40 ), $args);

        if (!$nowait) {
            return $this->wait(
                array(
                     "50,41" //Channel._queueDeleteOk
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
    protected function _queueDeleteOk(\AMQP\Wire\Reader $args)
    {
        return $args->readLong();
    }

    /**
     * purge a queue
     *
     * @param string $queue
     * @param bool   $nowait
     * @param null   $ticket
     *
     * @return mixed|null
     */
    public function queuePurge($queue = '', $nowait = false, $ticket = null)
    {
        $ticket = $this->_getTicket($ticket);
        $args = $this->frameBuilder->queuePurge($queue, $nowait, $ticket);

        $this->_sendMethodFrame(array( 50, 30 ), $args);

        if (!$nowait) {
            return $this->wait(
                array(
                     "50,31" //Channel._queuePurgeOk
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
    protected function _queuePurgeOk(\AMQP\Wire\Reader $args)
    {
        return $args->readLong();
    }

    /**
     * acknowledge one or more messages
     *
     * @param      $delivery_tag
     * @param bool $multiple
     */
    public function basicAck($delivery_tag, $multiple = false)
    {
        $args = $this->frameBuilder->basicAck($delivery_tag, $multiple);
        $this->_sendMethodFrame(array( 60, 80 ), $args);
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
        $this->_sendMethodFrame(array( 60, 30 ), $args);
        return $this->wait(
            array(
                 "60,31" // Channel._basicCancelOk
            )
        );
    }

    /**
     * confirm a cancelled consumer
     *
     * @param \AMQP\Wire\Reader $args
     */
    protected function _basicCancelOk(\AMQP\Wire\Reader $args)
    {
        $consumer_tag = $args->readShortstr();
        unset($this->callbacks[ $consumer_tag ]);
    }

    /**
     * _start a queue consumer
     *
     * @param string $queue
     * @param string $consumerTag
     * @param bool   $noLocal
     * @param bool   $noAck
     * @param bool   $exclusive
     * @param bool   $nowait
     * @param null   $callback
     * @param null   $ticket
     *
     * @return mixed|null|string
     */
    public function basicConsume($queue = '', $consumerTag = '',
                                  $noLocal = false,
                                  $noAck = false, $exclusive = false,
                                  $nowait = false,
                                  $callback = null, $ticket = null)
    {
        $ticket = $this->_getTicket($ticket);
        $args = $this->frameBuilder->basicConsume(
            $queue, $consumerTag, $noLocal,
            $noAck, $exclusive, $nowait, $ticket
        );

        $this->_sendMethodFrame(array( 60, 20 ), $args);

        if (!$nowait) {
            $consumerTag = $this->wait(
                array(
                     "60,21" //Channel._basicConsumeOk
                )
            );
        }

        $this->callbacks[ $consumerTag ] = $callback;
        return $consumerTag;
    }

    /**
     * confirm a new consumer
     *
     * @param \AMQP\Wire\Reader $args
     *
     * @return string
     */
    protected function _basicConsumeOk(\AMQP\Wire\Reader $args)
    {
        return $args->readShortstr();
    }

    /**
     * notify the client of a consumer message
     *
     * @param \AMQP\Wire\Reader     $args
     * @param \AMQP\Message $msg
     */
    protected function _basicDeliver(
        \AMQP\Wire\Reader $args,
        \AMQP\Message $msg
    )
    {
        $consumerTag = $args->readShortstr();
        $deliveryTag = $args->readLonglong();
        $redelivered = $args->readBit();
        $exchange = $args->readShortstr();
        $routingKey = $args->readShortstr();

        $msg->delivery_info = array(
            "channel" => $this,
            "consumer_tag" => $consumerTag,
            "delivery_tag" => $deliveryTag,
            "redelivered" => $redelivered,
            "exchange" => $exchange,
            "routing_key" => $routingKey
        );

        if (isset($this->callbacks[ $consumerTag ])) {
            $func = $this->callbacks[ $consumerTag ];
        } else {
            $func = null;
        }

        if ($func != null) {
            call_user_func($func, $msg);
        }
    }

    /**
     * direct access to a queue
     *
     * @param string $queue
     * @param bool   $noAck
     * @param null   $ticket
     *
     * @return mixed|null
     */
    public function basicGet($queue = '', $noAck = false, $ticket = null)
    {
        $ticket = $this->_getTicket($ticket);
        $args = $this->frameBuilder->basicGet($queue, $noAck, $ticket);

        $this->_sendMethodFrame(array( 60, 70 ), $args);
        return $this->wait(
            array(
                 "60,71", //Channel._basicGetOk
                 "60,72" // Channel._basicGetEmpty
            )
        );
    }

    /**
     * indicate no messages available
     *
     * @param \AMQP\Wire\Reader $args
     */
    protected function _basicGetEmpty(\AMQP\Wire\Reader $args)
    {
        $cluster_id = $args->readShortstr();
    }

    /**
     * provide client with a message
     *
     * @param \AMQP\Wire\Reader     $args
     * @param \AMQP\Message $msg
     *
     * @return \AMQP\Message
     */
    protected function _basicGetOk(\AMQP\Wire\Reader $args, \AMQP\Message $msg)
    {
        $deliveryTag = $args->readLonglong();
        $redelivered = $args->readBit();
        $exchange = $args->readShortstr();
        $routingKey = $args->readShortstr();
        $messageCount = $args->readLong();

        $msg->delivery_info = array(
            "delivery_tag" => $deliveryTag,
            "redelivered" => $redelivered,
            "exchange" => $exchange,
            "routing_key" => $routingKey,
            "message_count" => $messageCount
        );
        return $msg;
    }

    /**
     * publish a message
     *
     * @param \AMQP\Message $msg
     * @param string                    $exchange
     * @param string                    $routingKey
     * @param bool                      $mandatory
     * @param bool                      $immediate
     * @param null                      $ticket
     */
    public function basicPublish(\AMQP\Message $msg, $exchange = '', $routingKey = '',
                                  $mandatory = false, $immediate = false,
                                  $ticket = null)
    {
        $ticket = $this->_getTicket($ticket);
        $args = $this->frameBuilder->basicPublish(
            $exchange, $routingKey, $mandatory,
            $immediate, $ticket
        );

        $this->_sendMethodFrame(array( 60, 40 ), $args);

        $this->_connection->sendContent(
            $this->_channelId, 60, 0,
            strlen($msg->body),
            $msg->serialize_properties(),
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
        $this->_sendMethodFrame(array( 60, 10 ), $args);
        return $this->wait(
            array(
                 "60,11" //Channel._basicQosOk
            )
        );
    }

    /**
     * confirm the requested qos
     *
     * @param \AMQP\Wire\Reader $args
     */
    protected function _basicQosOk(\AMQP\Wire\Reader $args)
    {
    }

    /**
     * redeliver unacknowledged messages
     *
     * @param bool $requeue
     */
    public function basicRecover($requeue = false)
    {
        $args = $this->frameBuilder->basicRecover($requeue);
        $this->_sendMethodFrame(array( 60, 100 ), $args);
    }

    /**
     * reject an incoming message
     *
     * @param $deliveryTag
     * @param $requeue
     */
    public function basicReject($deliveryTag, $requeue)
    {
        $args = $this->frameBuilder->basicReject($deliveryTag, $requeue);
        $this->_sendMethodFrame(array( 60, 90 ), $args);
    }

    /**
     * return a failed message
     *
     * @param \AMQP\Wire\Reader $args
     */
    protected function _basicReturn(\AMQP\Wire\Reader $args)
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
        $this->_sendMethodFrame(array( 90, 20 ));
        return $this->wait(
            array(
                 "90,21" //Channel._txCommitOk
            )
        );
    }

    /**
     * confirm a successful commit
     *
     * @param \AMQP\Wire\Reader $args
     */
    protected function _txCommitOk(\AMQP\Wire\Reader $args)
    {
    }

    /**
     * abandon the current transaction
     *
     * @return mixed|null
     */
    public function txRollback()
    {
        $this->_sendMethodFrame(array( 90, 30 ));
        return $this->wait(
            array(
                 "90,31" //Channel._txRollbackOk
            )
        );
    }

    /**
     * confirm a successful rollback
     *
     * @param \AMQP\Wire\Reader $args
     */
    protected function _txRollbackOk(\AMQP\Wire\Reader $args)
    {
    }

    /**
     * select standard transaction mode
     *
     * @return mixed|null
     */
    public function txSelect()
    {
        $this->_sendMethodFrame(array( 90, 10 ));
        return $this->wait(
            array(
                 "90,11" //Channel._txSelectOk
            )
        );
    }

    /**
     * confirm transaction mode
     *
     * @param \AMQP\Wire\Reader $args
     */
    protected function _txSelectOk(\AMQP\Wire\Reader $args)
    {
    }

    /**
     * @param array $arguments
     *
     * @return array
     */
    protected function _getArguments($arguments)
    {
        return (null === $arguments) ? array() : $arguments;
    }

    /**
     * @param $ticket
     *
     * @return int
     */
    protected function _getTicket($ticket)
    {
        return (null === $ticket) ? $this->_defaultTicket : $ticket;
    }
}
