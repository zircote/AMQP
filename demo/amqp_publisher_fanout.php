<?php

include(__DIR__ . '/config.php');
use AMQP\Connection;
use AMQP\Message;

$exchange = 'fanout_example_exchange';

$connection = new Connection(AMQP_RESOURCE);
$channel = $connection->channel();

/*
    name: $exchange
    type: fanout
    passive: false // don't check is an exchange with the same name exists
    durable: false // the exchange won't survive server restarts
    auto_delete: true //the exchange will be deleted once the channel is closed.
*/

$channel->exchangeDeclare($exchange, 'fanout');

$messageBody = implode(' ', array_slice($argv, 1));
$message = new Message($messageBody,array('content_type' => 'text/plain'));
$channel->basicPublish($message, array('exchange' => $exchange));

$channel->close();
$connection->close();
