<?php

include(__DIR__ . '/config.php');
use AMQP\Connection;
use AMQP\Message;

$exchange = 'basic_get_test';
$queue = 'basic_get_queue';

$conn = new Connection(AMQP_RESOURCE);
$ch = $conn->channel();

/*
    The following code is the same both in the consumer and the producer.
    In this way we are sure we always have a queue to consume from and an
        exchange where to publish messages.
*/

/*
    name: $queue
    passive: false
    durable: true // the queue will survive server restarts
    exclusive: false // the queue can be accessed in other channels
    auto_delete: false //the queue won't be deleted once the channel is closed.
*/
$ch->queueDeclare(array('queue' => $queue, 'durable' => true, 'auto_delete' => false));

/*
    name: $exchange
    type: direct
    passive: false
    durable: true // the exchange will survive server restarts
    auto_delete: false //the exchange won't be deleted once the channel is closed.
*/

$ch->exchangeDeclare($exchange, 'direct', array('durable' => true, 'auto_delete' => false));

$ch->queueBind($queue, $exchange);

$toSend = new Message('test message', array('content_type' => 'text/plain', 'delivery_mode' => 2));
$ch->basicPublish($toSend, array('exchange' => $exchange));

$msg = $ch->basicGet(array('queue' => $queue));

$ch->basicAck($msg->delivery_info['delivery_tag']);

var_dump($msg->body);

$ch->close();
$conn->close();
