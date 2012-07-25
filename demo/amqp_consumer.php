<?php

include(__DIR__ . '/config.php');
use AMQP\Connection;

$exchange = 'router';
$queue = 'msgs';
$consumerTag = 'consumer';

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
$ch->queueDeclare($queue, false, true, false, false);

/*
    name: $exchange
    type: direct
    passive: false
    durable: true // the exchange will survive server restarts
    auto_delete: false //the exchange won't be deleted once the channel is closed.
*/

$ch->exchangeDeclare($exchange, 'direct', false, true, false);

$ch->queueBind($queue, $exchange);

/*
    queue: Queue from where to get the messages
    consumer_tag: Consumer identifier
    no_local: Don't receive messages published by this consumer.
    no_ack: Tells the server if the consumer will acknowledge the messages.
    exclusive: Request exclusive consumer access, meaning only this consumer can access the queue
    nowait:
    callback: A PHP Callback
*/

$ch->basicConsume(
    $queue, $consumerTag, false, false, false, false, function ($msg)
    {

        echo "\n--------\n";
        echo $msg->body;
        echo "\n--------\n";

        $msg->delivery_info[ 'channel' ]->
            basic_ack($msg->delivery_info[ 'delivery_tag' ]);

        // Send a message with the string "quit" to cancel the consumer.
        if ($msg->body === 'quit') {
            $msg->delivery_info[ 'channel' ]->
                basic_cancel($msg->delivery_info[ 'consumer_tag' ]);
        }
    }
);

register_shutdown_function(
    function() use ($ch, $conn)
    {
        $ch->close();
        $conn->close();
    }
);

// Loop as long as the channel has callbacks registered
while (count($ch->callbacks)) {
    $ch->wait();
}
