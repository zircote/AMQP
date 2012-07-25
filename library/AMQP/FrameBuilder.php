<?php
namespace AMQP;
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
    public function channelClose($replyCode, $replyText, $classId, $methodId)
    {
        $args = new Writer();
        $args->writeShort($replyCode)
            ->writeShortStr($replyText)
            ->writeShort($classId)
            ->writeShort($methodId);
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
    public function accessRequest($realm, $exclusive, $passive, $active, $write,
                                  $read)
    {
        $args = new Writer();
        $args->writeShortStr($realm)
            ->writeBit($exclusive)
            ->writeBit($passive)
            ->writeBit($active)
            ->writeBit($write)
            ->writeBit($read);
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
    public function exchangeDeclare($exchange, $type, $passive, $durable,
                                    $autoDelete, $internal, $nowait, $arguments,
                                    $ticket)
    {
        $args = new Writer();
        $args->writeShort($ticket)
            ->writeShortStr($exchange)
            ->writeShortStr($type)
            ->writeBit($passive)
            ->writeBit($durable)
            ->writeBit($autoDelete)
            ->writeBit($internal)
            ->writeBit($nowait)
            ->writeTable($arguments);
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
    public function exchangeDelete($exchange, $ifUnused, $nowait, $ticket)
    {
        $args = new Writer();
        $args->writeShort($ticket)
            ->writeShortStr($exchange)
            ->writeBit($ifUnused)
            ->writeBit($nowait);
        return $args;
    }

    /**
     * @param string $queue
     * @param string $exchange
     * @param string $routingKey
     * @param bool   $nowait
     * @param array  $arguments
     * @param string $ticket
     *
     * @return \AMQP\Wire\Writer
     */
    public function queueBind($queue, $exchange, $routingKey, $nowait,
                              $arguments, $ticket)
    {
        $args = new Writer();
        $args->writeShort($ticket)
            ->writeShortStr($queue)
            ->writeShortStr($exchange)
            ->writeShortStr($routingKey)
            ->writeBit($nowait)
            ->writeTable($arguments);
        return $args;
    }

    public function queueUnbind($queue, $exchange, $routingKey, $arguments,
                                $ticket)
    {
        $args = new Writer();
        $args->writeShort($ticket)
            ->writeShortStr($queue)
            ->writeShortStr($exchange)
            ->writeShortStr($routingKey)
            ->writeTable($arguments);
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
    public function queueDeclare($queue, $passive, $durable, $exclusive,
                                 $autoDelete, $nowait, $arguments, $ticket)
    {
        $args = new Writer();
        $args->writeShort($ticket)
            ->writeShortStr($queue)
            ->writeBit($passive)
            ->writeBit($durable)
            ->writeBit($exclusive)
            ->writeBit($autoDelete)
            ->writeBit($nowait)
            ->writeTable($arguments);
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
    public function queueDelete($queue, $ifUnused, $ifEmpty, $nowait, $ticket)
    {
        $args = new Writer();
        $args->writeShort($ticket)
            ->writeShortStr($queue)
            ->writeBit($ifUnused)
            ->writeBit($ifEmpty)
            ->writeBit($nowait);
        return $args;
    }

    /**
     * @param string $queue
     * @param bool   $nowait
     * @param string $ticket
     *
     * @return \AMQP\Wire\Writer
     */
    public function queuePurge($queue, $nowait, $ticket)
    {
        $args = new Writer();
        $args->writeShort($ticket)
            ->writeShortStr($queue)
            ->writeBit($nowait);
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
    public function basicConsume($queue, $consumerTag, $noLocal,
                                 $noAck, $exclusive, $nowait, $ticket)
    {
        $args = new Writer();
        $args->writeShort($ticket)
            ->writeShortStr($queue)
            ->writeShortStr($consumerTag)
            ->writeBit($noLocal)
            ->writeBit($noAck)
            ->writeBit($exclusive)
            ->writeBit($nowait);
        return $args;
    }

    /**
     * @param string $queue
     * @param bool $noAck
     * @param string $ticket
     *
     * @return \AMQP\Wire\Writer
     */
    public function basicGet($queue, $noAck, $ticket)
    {
        $args = new Writer();
        $args->writeShort($ticket)
            ->writeShortStr($queue)
            ->writeBit($noAck);
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
    public function basicPublish($exchange, $routingKey, $mandatory,
                                 $immediate, $ticket)
    {
        $args = new Writer();
        $args->writeShort($ticket)
            ->writeShortStr($exchange)
            ->writeShortStr($routingKey)
            ->writeBit($mandatory)
            ->writeBit($immediate);
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
     * @param bool $requeue
     *
     * @return \AMQP\Wire\Writer
     */
    public function basicRecover($requeue)
    {
        $args = new Writer();
        $args->writeBit($requeue);
        return $args;
    }

    /**
     * @param string $deliveryTag
     * @param bool   $requeue
     *
     * @return \AMQP\Wire\Writer
     */
    public function basicReject($deliveryTag, $requeue)
    {
        $args = new Writer();
        $args->writeLongLong($deliveryTag)
            ->writeBit($requeue);
        return $args;
    }
}
