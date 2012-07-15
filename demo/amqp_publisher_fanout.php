<?php

include(__DIR__ . '/config.php');
use AMQP\Connection\Connection;
use AMQP\Message\Message;

$exchange = 'fanout_example_exchange';

$conn = new Connection(HOST, PORT, USER, PASS, VHOST);
$ch = $conn->channel();

/*
    name: $exchange
    type: fanout
    passive: false // don't check is an exchange with the same name exists
    durable: false // the exchange won't survive server restarts
    auto_delete: true //the exchange will be deleted once the channel is closed.
*/

$ch->exchangeDeclare($exchange, 'fanout', false, false, true);

$msg_body = implode(' ', array_slice($argv, 1));
$msg = new Message($msg_body,array('content_type' => 'text/plain'));
$ch->basicPublish($msg, $exchange);

$ch->close();
$conn->close();
?>
