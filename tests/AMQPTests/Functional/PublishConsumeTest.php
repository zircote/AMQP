<?php

namespace AMQPTests\Functional;

use AMQP\Connection;
use AMQP\Message;

class PublishConsumeTest extends AbstractTestCase
{

    public function setUp()
    {
        $this->conn = new Connection(AMQP_TEST_HOST);
        $this->ch = $this->conn->channel();

        $this->ch->exchangeDeclare($this->exchange_name, 'direct', false, false, false);
        list($this->queue_name,,) = $this->ch->queueDeclare();
        $this->ch->queueBind($this->queue_name, $this->exchange_name, $this->queue_name);
    }

    public function testPublishConsume()
    {
        $this->msg_body = 'foo bar baz äëïöü';

        $msg = new Message($this->msg_body, array(
            'content_type' => 'text/plain',
            'delivery_mode' => 1,
            'correlation_id' => 'my_correlation_id',
            'reply_to' => 'my_reply_to'
        ));

        $this->ch->basicPublish($msg, $this->exchange_name, $this->queue_name);

        $this->ch->basicConsume(
            $this->queue_name,
            getmypid(),
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
        $delivery_info = $msg->delivery_info;

        $delivery_info['channel']->basicAck($delivery_info['delivery_tag']);
        $delivery_info['channel']->basicCancel($delivery_info['consumer_tag']);

        $this->assertEquals($this->msg_body, $msg->body);

        //delivery tests
        $this->assertEquals(getmypid(), $delivery_info['consumer_tag']);
        $this->assertEquals($this->queue_name, $delivery_info['routing_key']);
        $this->assertEquals($this->exchange_name, $delivery_info['exchange']);
        $this->assertEquals(false, $delivery_info['redelivered']);

        //msg property tests
        $this->assertEquals('text/plain', $msg->get('content_type'));
        $this->assertEquals('my_correlation_id', $msg->get('correlation_id'));
        $this->assertEquals('my_reply_to', $msg->get('reply_to'));

        $this->setExpectedException('OutOfBoundsException');
        $msg->get('no_property');
    }

    public function tearDown()
    {
        if(!$this->ch){
            return;
        }
        $this->ch->exchangeDelete($this->exchange_name);
        $this->ch->close();
        $this->conn->close();
    }
}
