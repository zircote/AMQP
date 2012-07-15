<?php

namespace AMQPTests\Functional;

use AMQP\Connection\Connection;
use AMQP\Message\Message;

/**
 * @group File
 */
class FileTransferTest extends  \PHPUnit_Framework_TestCase
{


    /**
     * @var string
     */
    protected $exchange_name = 'test_exchange';

    /**
     * @var null
     */
    protected $queue_name = null;

    /**
     * @var \AMQP\Connection\Connection
     */
    protected $conn;

    /**
     * @var \AMQP\Channel\Channel
     */
    protected $ch;

    /**
     * @var string
     */
    protected  $msg_body;

    public function setUp()
    {
        $this->conn = new Connection(AMQP_TEST_HOST);
        $this->ch = $this->conn->channel();
        $this->ch->exchangeDeclare($this->exchange_name, 'direct', false, false, false);
        list($this->queue_name,,) = $this->ch->queueDeclare();
        $this->ch->queueBind($this->queue_name, $this->exchange_name, $this->queue_name);
    }

    public function testSendFile()
    {
        $this->msg_body = file_get_contents(__DIR__.'/fixtures/data_1mb.bin');

        $msg = new Message($this->msg_body, array('delivery_mode' => 1));
        $this->ch->basicPublish($msg, $this->exchange_name, $this->queue_name);

        $this->ch->basicConsume(
            $this->queue_name,
            '',
            false,
            false,
            false,
            false,
            array($this, 'process_msg')
        );

        while (count($this->ch->callbacks)) {
            $this->ch->wait();
        }
    }

    public function process_msg($msg)
    {
        $msg->delivery_info['channel']
            ->basicAck($msg->delivery_info['delivery_tag']);
        $msg->delivery_info['channel']
            ->basicCancel($msg->delivery_info['consumer_tag']);

        $this->assertEquals($this->msg_body, $msg->body);
    }

    public function tearDown()
    {
        if($this->ch){
            $this->ch->exchangeDelete($this->exchange_name);
            $this->ch->close();
            $this->conn->close();
        }
    }
}
