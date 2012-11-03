<?php
namespace AMQPTests\Functional;

use AMQP\Connection;
use AMQP\Message;

/**
 * @group File
 */
class FileTransferTest  extends AbstractTestCase
{

    public function testSendFile()
    {
        $this->msgBody = file_get_contents(__DIR__.'/fixtures/data_1mb.bin');

        $msg = new Message($this->msgBody, array('delivery_mode' => 1));
        $default = array('exchange' => $this->exchangeName, 'routingKey' => $this->queueName);
        $this->channel->basicPublish($msg, $default);

        $default = array('queue' => $this->queueName, 'consumer_tag' => '', 'no_local' => false, 'no_ack' => false,
                         'exclusive' => false, 'no_wait' => false, 'callback' => array($this, 'process_msg'));
        $this->channel->basicConsume($default);

        while (count($this->channel->callbacks)) {
            $this->channel->wait();
        }
    }

    public function process_msg($msg)
    {
        $msg->delivery_info['channel']
            ->basicAck($msg->delivery_info['delivery_tag']);
        $msg->delivery_info['channel']
            ->basicCancel($msg->delivery_info['consumer_tag']);

        $this->assertEquals($this->msgBody, $msg->body);
    }

}
