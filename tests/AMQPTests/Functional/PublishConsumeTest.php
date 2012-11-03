<?php
namespace AMQPTests\Functional;

use AMQP\Connection;
use AMQP\Message;

class PublishConsumeTest extends AbstractTestCase
{

    /**
     * @group publish
     */
    public function testPublishConsume()
    {
        $this->msgBody = 'foo bar baz äëïöü';

        $msg = new Message($this->msgBody, array(
            'content_type' => 'text/plain',
            'delivery_mode' => 1,
            'correlation_id' => 'my_correlation_id',
            'reply_to' => 'my_reply_to'
        ));

        $default = array('exchange' => $this->exchangeName, 'routingKey' => $this->queueName);
        $this->channel->basicPublish($msg, $default);

        $default = array('queue' => $this->queueName, 'consumer_tag' => getmypid(), 'no_local' => false, 'no_ack' => false,
                         'exclusive' => false,'no_wait' => false, 'callback' => array($this, 'process_msg'));
        $this->channel->basicConsume($default);

        while (count($this->channel->callbacks)) {
            $this->channel->wait();
        }
    }

    public function process_msg($msg)
    {
        $delivery_info = $msg->delivery_info;

        $delivery_info['channel']->basicAck($delivery_info['delivery_tag']);
        $delivery_info['channel']->basicCancel($delivery_info['consumer_tag']);

        $this->assertEquals($this->msgBody, $msg->body);

        //delivery tests
        $this->assertEquals(getmypid(), $delivery_info['consumer_tag']);
        $this->assertEquals($this->queueName, $delivery_info['routing_key']);
        $this->assertEquals($this->exchangeName, $delivery_info['exchange']);
        $this->assertEquals(false, $delivery_info['redelivered']);

        //msg property tests
        $this->assertEquals('text/plain', $msg->get('content_type'));
        $this->assertEquals('my_correlation_id', $msg->get('correlation_id'));
        $this->assertEquals('my_reply_to', $msg->get('reply_to'));

        $this->setExpectedException('OutOfBoundsException');
        $msg->get('no_property');
    }
}
