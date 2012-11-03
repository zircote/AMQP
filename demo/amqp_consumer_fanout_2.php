<?php

// Run multiple instances of amqp_consumer_fanout_1.php and
// amqp_consumer_fanout_2.php to test

include(__DIR__ . '/config.php');
use AMQP\Connection;

$exchange = 'fanout_example_exchange';
$queue = 'fanout_group_2'; // Let RabbitMQ create a queue name
$consumerTag = 'consumer' . getmypid();

$connection = new Connection(AMQP_RESOURCE);
$channel = $connection->channel();

/*
    name: $queue    // should be unique in fanout exchange.
    passive: false  // don't check if a queue with the same name exists
    durable: false // the queue will not survive server restarts
    exclusive: false // the queue might be accessed by other channels
    auto_delete: true //the queue will be deleted once the channel is closed.
*/
$channel->queueDeclare(array('queue' => $queue));

/*
    name: $exchange
    type: direct
    passive: false // don't check if a exchange with the same name exists
    durable: false // the exchange will not survive server restarts
    auto_delete: true //the exchange will be deleted once the channel is closed.
*/

$channel->exchangeDeclare($exchange, 'fanout');

$channel->queueBind($queue, $exchange);

function process_message($msg)
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

/*
    queue: Queue from where to get the messages
    consumer_tag: Consumer identifier
    no_local: Don't receive messages published by this consumer.
    no_ack: Tells the server if the consumer will acknowledge the messages.
    exclusive: Request exclusive consumer access, meaning only this consumer can access the queue
    nowait: don't wait for a server response. In case of error the server will raise a channel
            exception
    callback: A PHP Callback
*/

$channel->basicConsume(
    array('queue' => $queue, 'consumer_tag' => $consumerTag, 'callback' => 'process_message')
);

register_shutdown_function(
    function() use ($channel, $connection)
    {
        $channel->close();
        $connection->close();
    }
);

// Loop as long as the channel has callbacks registered
while (count($channel->callbacks)) {
    $channel->wait();
}

