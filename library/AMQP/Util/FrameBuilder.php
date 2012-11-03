<?php
namespace AMQP\Util;

/**
 *
 */
use AMQP\Wire\Writer;

/**
 *
 */
class FrameBuilder
{

    /**
     * @param int    $replyCode
     * @param string $replyText
     * @param string $classId
     * @param string $methodId
     *
     * @return \AMQP\Wire\Writer
     */
    public function channelClose($options)
    {
        $args = new Writer();
        $args->writeShort($options['reply_code'])
            ->writeShortStr($options['reply_text'])
            ->writeShort($options['method_signature'][0])
            ->writeShort($options['method_signature'][1]);
        return $args;
    }

    /**
     * @param $active
     *
     * @return \AMQP\Wire\Writer
     */
    public function flow($active)
    {
        $args = new Writer();
        $args->writeBit($active);
        return $args;
    }

    /**
     * @param $active
     *
     * @return \AMQP\Wire\Writer
     */
    public function xFlowOk($active)
    {
        $args = new Writer();
        $args->writeBit($active);
        return $args;
    }

    /**
     * @param $outOfBand
     *
     * @return \AMQP\Wire\Writer
     */
    public function xOpen($outOfBand)
    {
        $args = new Writer();
        $args->writeShortStr($outOfBand);
        return $args;
    }

    /**
     * @param string $realm
     * @param string $exclusive
     * @param string $passive
     * @param bool   $active
     * @param bool   $write
     * @param bool   $read
     *
     * @return \AMQP\Wire\Writer
     */
    public function accessRequest($realm, $options)
    {
        $args = new Writer();
        $args->writeShortStr($realm)
            ->writeBit($options['exclusive'])
            ->writeBit($options['passive'])
            ->writeBit($options['active'])
            ->writeBit($options['write'])
            ->writeBit($options['read']);
        return $args;
    }

    /**
     * @param string $exchange
     * @param int    $type
     * @param bool   $passive
     * @param bool   $durable
     * @param bool   $autoDelete
     * @param bool   $internal
     * @param bool   $nowait
     * @param array  $arguments
     * @param string $ticket
     *
     * @return \AMQP\Wire\Writer
     */
    public function exchangeDeclare($exchange, $type, $options = array())
    {
        $args = new Writer();
        $args->writeShort($options['ticket'])
            ->writeShortStr($exchange)
            ->writeShortStr($type)
            ->writeBit($options['passive'])
            ->writeBit($options['durable'])
            ->writeBit($options['auto_delete'])
            ->writeBit($options['internal'])
            ->writeBit($options['no_wait'])
            ->writeTable($options['arguments']);
        return $args;
    }

    /**
     * @param string $exchange
     * @param bool   $ifUnused
     * @param bool   $nowait
     * @param string $ticket
     *
     * @return \AMQP\Wire\Writer
     */
    public function exchangeDelete($exchange, $options)
    {
        $args = new Writer();
        $args->writeShort($options['ticket'])
            ->writeShortStr($exchange)
            ->writeBit($options['if_unused'])
            ->writeBit($options['no_wait']);
        return $args;
    }

    /**
     * @param string $queue
     * @param string $exchange
     * @param string $routing_key
     * @param bool   $nowait
     * @param array  $arguments
     * @param string $ticket
     *
     * @return \AMQP\Wire\Writer
     */
    public function queueBind($queue, $exchange, $options)
    {
        $args = new Writer();
        $args->writeShort($options['ticket'])
            ->writeShortStr($queue)
            ->writeShortStr($exchange)
            ->writeShortStr($options['routing_key'])
            ->writeBit($options['no_wait'])
            ->writeTable($options['arguments']);
        return $args;
    }

    public function queueUnbind($queue, $exchange, $options)
    {
        $args = new Writer();
        $args->writeShort($options['ticket'])
            ->writeShortStr($queue)
            ->writeShortStr($exchange)
            ->writeShortStr($options['routing_key'])
            ->writeTable($options['arguments']);
        return $args;
    }

    /**
     * @param string $queue
     * @param bool   $passive
     * @param bool   $durable
     * @param bool   $exclusive
     * @param bool   $autoDelete
     * @param bool   $nowait
     * @param array  $arguments
     * @param string $ticket
     *
     * @return \AMQP\Wire\Writer
     */
    public function queueDeclare($options)
    {
        $args = new Writer();
        $args->writeShort($options['ticket'])
            ->writeShortStr($options['queue'])
            ->writeBit($options['passive'])
            ->writeBit($options['durable'])
            ->writeBit($options['exclusive'])
            ->writeBit($options['auto_delete'])
            ->writeBit($options['no_wait'])
            ->writeTable($options['arguments']);
        return $args;
    }

    /**
     * @param string $queue
     * @param bool   $ifUnused
     * @param bool   $ifEmpty
     * @param bool   $nowait
     * @param string $ticket
     *
     * @return \AMQP\Wire\Writer
     */
    public function queueDelete($options)
    {
        $args = new Writer();
        $args->writeShort($options['ticket'])
            ->writeShortStr($options['queue'])
            ->writeBit($options['if_unused'])
            ->writeBit($options['if_empty'])
            ->writeBit($options['no_wait']);
        return $args;
    }

    /**
     * @param string $queue
     * @param bool   $nowait
     * @param string $ticket
     *
     * @return \AMQP\Wire\Writer
     */
    public function queuePurge($options)
    {
        $args = new Writer();
        $args->writeShort($options['ticket'])
            ->writeShortStr($options['queue'])
            ->writeBit($options['no_wait']);
        return $args;
    }

    /**
     * @param string $deliveryTag
     * @param bool   $multiple
     *
     * @return \AMQP\Wire\Writer
     */
    public function basicAck($deliveryTag, $multiple)
    {
        $args = new Writer();
        $args->writeLongLong($deliveryTag)
            ->writeBit($multiple);
        return $args;
    }

    /**
     * @param $consumerTag
     * @param $nowait
     *
     * @return \AMQP\Wire\Writer
     */
    public function basicCancel($consumerTag, $nowait)
    {
        $args = new Writer();
        $args->writeShortStr($consumerTag)
            ->writeBit($nowait);
        return $args;
    }

    /**
     * @param string $queue
     * @param string $consumerTag
     * @param bool   $noLocal
     * @param bool   $noAck
     * @param bool   $exclusive
     * @param bool   $nowait
     * @param string $ticket
     *
     * @return \AMQP\Wire\Writer
     */
    public function basicConsume($options)
    {
        $args = new Writer();
        $args->writeShort($options['ticket'])
            ->writeShortStr($options['queue'])
            ->writeShortStr($options['consumer_tag'])
            ->writeBit($options['no_local'])
            ->writeBit($options['no_ack'])
            ->writeBit($options['exclusive'])
            ->writeBit($options['no_wait']);
        return $args;
    }

    /**
     * @param string $queue
     * @param bool   $noAck
     * @param string $ticket
     *
     * @return \AMQP\Wire\Writer
     */
    public function basicGet($options)
    {
        $args = new Writer();
        $args->writeShort($options['ticket'])
            ->writeShortStr($options['queue'])
            ->writeBit($options['no_ack']);
        return $args;
    }

    /**
     * @param string $exchange
     * @param string $routingKey
     * @param bool   $mandatory
     * @param bool   $immediate
     * @param string $ticket
     *
     * @return \AMQP\Wire\Writer
     */
    public function basicPublish($options)
    {
        $args = new Writer();
        $args->writeShort($options['ticket'])
            ->writeShortStr($options['exchange'])
            ->writeShortStr($options['routingKey'])
            ->writeBit($options['mandatory'])
            ->writeBit($options['immediate']);
        return $args;
    }

    /**
     * @param int  $prefetchSize
     * @param int  $prefetchCount
     * @param bool $aGlobal
     *
     * @return \AMQP\Wire\Writer
     */
    public function basicQos($prefetchSize, $prefetchCount, $aGlobal)
    {
        $args = new Writer();
        $args->writeLong($prefetchSize)
            ->writeShort($prefetchCount)
            ->writeBit($aGlobal);
        return $args;
    }

    /**
     * @param bool $reQueue
     *
     * @return \AMQP\Wire\Writer
     */
    public function basicRecover($reQueue)
    {
        $args = new Writer();
        $args->writeBit($reQueue);
        return $args;
    }

    /**
     * @param string $deliveryTag
     * @param bool   $reQueue
     *
     * @return \AMQP\Wire\Writer
     */
    public function basicReject($deliveryTag, $reQueue)
    {
        $args = new Writer();
        $args->writeLongLong($deliveryTag)
            ->writeBit($reQueue);
        return $args;
    }
}
