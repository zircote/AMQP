<?php

include(__DIR__ . '/config.php');
use AMQP\Connection;
use AMQP\Message;

$exchange = 'router';
$queue = 'msgs';

$connection = new Connection(AMQP_RESOURCE);
$channel = $connection->channel();

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
$channel->queueDeclare(array('queue' => $queue, 'durable' => true, 'auto_delete' => false));

/*
    name: $exchange
    type: direct
    passive: false
    durable: true // the exchange will survive server restarts
    auto_delete: false //the exchange won't be deleted once the channel is closed.
*/

$channel->exchangeDeclare($exchange, 'direct', array('durable' => true, 'auto_delete' => false));

$channel->queueBind($queue, $exchange);

$messageBody = implode(' ', array_slice($argv, 1));
$message = new Message($messageBody, array('content_type' => 'text/plain', 'delivery_mode' => 2));
$channel->basicPublish($message, $exchange);

$channel->close();
$connection->close();
