<?php

namespace AMQPTests\Unit\Helper\Writer;

use AMQP\Util\FrameBuilder;

class FrameBuilderTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var FrameBuilder
     */
    protected $frameBuilder;
    public function setUp()
    {
        $this->frameBuilder = new FrameBuilder();
    }

    public function testChannelClose()
    {
        $expected = "\x00\x00\x00\x00\x00\x00\x00";
        $default = array('reply_code' => 0, 'reply_text' => '', 'method_signature' => array(0,0));
        $args = $this->frameBuilder->channelClose($default);
        $this->assertEquals($expected, $args->getvalue());

        $expected = "\x00\x00\x05error\x00\x00\x00\x00";
        $default = array('reply_code' => 0, 'reply_text' => 'error', 'method_signature' => array(0,0));
        $args = $this->frameBuilder->channelClose($default);
        $this->assertEquals($expected, $args->getvalue());

        $expected = "\x00\x00\x05error\x00\x14\x00\x28";
        $default = array('reply_code' => 0, 'reply_text' => 'error', 'method_signature' => array(20,40));
        $args = $this->frameBuilder->channelClose($default);
        $this->assertEquals($expected, $args->getvalue());
    }

    public function testFlow()
    {
        $expected = "\x01";
        $args = $this->frameBuilder->flow(true);
        $this->assertEquals($expected, $args->getvalue());

        $expected = "\x00";
        $args = $this->frameBuilder->flow(false);
        $this->assertEquals($expected, $args->getvalue());
    }

    public function testXFlowOk()
    {
        $expected = "\x01";
        $args = $this->frameBuilder->xFlowOk(true);
        $this->assertEquals($expected, $args->getvalue());

        $expected = "\x00";
        $args = $this->frameBuilder->xFlowOk(false);
        $this->assertEquals($expected, $args->getvalue());
    }

    public function testXOpen()
    {
        $expected = "\x03foo";
        $args = $this->frameBuilder->xOpen("foo");
        $this->assertEquals($expected, $args->getvalue());

        $expected = "\x00";
        $args = $this->frameBuilder->xOpen('');
        $this->assertEquals($expected, $args->getvalue());
    }

    public function testAccessRequest()
    {
        $expected = "\x01/\x00";
        $default = array('exclusive' => false, 'passive' => false, 'active' => false, 'write' => false, 'read' => false);
        $args = $this->frameBuilder->accessRequest("/", $default);
        $this->assertEquals($expected, $args->getvalue());

        $expected = "\x04/foo\x00";
        $default = array('exclusive' => false, 'passive' => false, 'active' => false, 'write' => false, 'read' => false);
        $args = $this->frameBuilder->accessRequest("/foo", $default);
        $this->assertEquals($expected, $args->getvalue());
    }

    public function testExchangeDeclare()
    {
        $expected = "\x00\x00\x03foo\x06direct\x00\x00\x00\x00\x00";
         $options = array('passive' => false, 'durable' => false, 'auto_delete' => false,
        'internal' => false, 'no_wait' => false, 'arguments' => array(), 'ticket' => 0);
        $args = $this->frameBuilder->exchangeDeclare('foo', 'direct', $options);
       $this->assertEquals($expected, $args->getvalue());
    }

    public function testExchangeDelete()
    {
        $expected = "\x00\x00\x03foo\x00";
        $default = array('if_unused' => false, 'no_wait' => false, 'ticket' => 0);
        $args = $this->frameBuilder->exchangeDelete('foo', $default);
        $this->assertEquals($expected, $args->getvalue());
    }

    public function testQueueBind()
    {
        $expected = "\x00\x00\x03foo\x03bar\x03baz\x00\x00\x00\x00\x00";
        $options = array('routing_key' => 'baz', 'no_wait' => false, 'arguments' => array(), 'ticket' => 0);
        $args = $this->frameBuilder->queueBind('foo', 'bar', $options);
        $this->assertEquals($expected, $args->getvalue());
    }

    public function testQueueUnbind()
    {
        $expected = "\x00\x00\x03foo\x03bar\x03baz\x00\x00\x00\x00";
        $default = array('routing_key' => 'baz', 'arguments' => array(), 'ticket' => 0);
        $args = $this->frameBuilder->queueUnbind('foo', 'bar', $default);
        $this->assertEquals($expected, $args->getvalue());
    }


    public function testQueueDeclare()
    {
        $expected = "\x00\x00\x03foo\x00\x00\x00\x00\x00";
        $default = array('queue' => 'foo', 'passive' => false, 'durable' => false, 'exclusive' => false,
        'auto_delete' => false, 'no_wait' => false, 'arguments' => array(), 'ticket' => 0);
        $args = $this->frameBuilder->queueDeclare($default);
       $this->assertEquals($expected, $args->getvalue());
    }

    public function testQueueDelete()
    {
        $expected = "\x00\x00\x03foo\x00";
        $options = array('queue' => 'foo', 'if_unused' => false, 'if_empty' => false, 'no_wait' => false, 'ticket' => 0);
        $args = $this->frameBuilder->queueDelete($options);
        $this->assertEquals($expected, $args->getvalue());
    }

    public function testQueuePurge()
    {
        $expected = "\x00\x00\x03foo\x00";
        $default = array('queue' => 'foo', 'no_wait' => false, 'ticket' => 0);
        $args = $this->frameBuilder->queuePurge($default);
        $this->assertEquals($expected, $args->getvalue());
    }

    public function testBasicAck()
    {
        $expected = "\x00\x00\x00\x00\x00\x00\x00\x01\x00";
        $args = $this->frameBuilder->basicAck(1, false);
        $this->assertEquals($expected, $args->getvalue());
    }

    public function testBasicCancel()
    {
        $expected = "\x03foo\x00";
        $args = $this->frameBuilder->basicCancel('foo', false);
        $this->assertEquals($expected, $args->getvalue());
    }

    public function testBasicConsume()
    {
        $expected = "\x00\x00\x03foo\x03bar\x00";
        $default = array('queue' => 'foo', 'consumer_tag' => 'bar', 'no_local' => false, 'no_ack' => false,
                         'exclusive' => false, 'no_wait' => false, 'ticket' => 0);
        $args = $this->frameBuilder->basicConsume($default);
        $this->assertEquals($expected, $args->getvalue());
    }

    public function testBasicGet()
    {
        $expected = "\x00\x00\x03foo\x00";
        $options = array('queue' => 'foo', 'no_ack' => false, 'ticket' => 0);
        $args = $this->frameBuilder->basicGet($options);
        $this->assertEquals($expected, $args->getvalue());
    }

    public function testBasicPublish()
    {
        $expected = "\x00\x00\x03foo\x03bar\x00";
        $default = array('exchange' => 'foo', 'routing_key' => 'bar', 'mandatory' => false, 'immediate' => false,
                         'ticket' => 0);
        $args = $this->frameBuilder->basicPublish($default);
        $this->assertEquals($expected, $args->getvalue());
    }

    public function testBasicQos()
    {
        $expected = "\x00\x00\x00\xA\x00\x01\x00";
        $args = $this->frameBuilder->basicQos(10, 1, false);
        $this->assertEquals($expected, $args->getvalue());
    }

    public function testBasicRecover()
    {
        $expected = "\x01";
        $args = $this->frameBuilder->basicRecover(true);
        $this->assertEquals($expected, $args->getvalue());

        $expected = "\x00";
        $args = $this->frameBuilder->basicRecover(false);
        $this->assertEquals($expected, $args->getvalue());
    }

    public function testBasicReject()
    {
        $expected = "\x00\x00\x00\x00\x00\x00\x00\x01\x01";
        $args = $this->frameBuilder->basicReject(1, true);
        $this->assertEquals($expected, $args->getvalue());

        $expected = "\x00\x00\x00\x00\x00\x00\x00\x01\x00";
        $args = $this->frameBuilder->basicReject(1, false);
        $this->assertEquals($expected, $args->getvalue());
    }
}
