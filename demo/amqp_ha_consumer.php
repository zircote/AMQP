<?php

include(__DIR__ . '/config.php');
use AMQP\Connection;

$exchange = 'router';
$queue = 'haqueue';
$specificQueue = 'specific-haqueue';

$consumerTag = 'consumer';

$connection = new Connection(AMQP_RESOURCE);
$channel = $connection->channel();

/*
    The following code is the same both in the consumer and the producer.
    In this way we are sure we always have a queue to consume from and an
        exchange where to publish messages.
*/

$uri = parse_url(AMQP_RESOURCE);

$ha_connection = array(
    'x-ha-policy' => array(
        'S', 'all'
    ),
);

$ha_specific_connection = array(
    'x-ha-policy' => array(
        'S', 'nodes'
    ),
    'x-ha-policy-params' => array(
        'A', array(
            'rabbit@' . $uri['host'],
            'hare@' . $uri['host'],
        ),
    ),
);

/*
    name: $queue
    passive: false
    durable: true // the queue will survive server restarts
    exclusive: false // the queue can be accessed in other channels
    auto_delete: false //the queue won't be deleted once the channel is closed.
    nowait: false // Doesn't wait on replies for certain things.
    parameters: array // How you send certain extra data to the queue declare
*/
$channel->queueDeclare(
    array('queue' => $queue, 'auto_delete' => false, 'arguments' => $ha_connection)
);
$channel->queueDeclare(
    array('queue' => $specificQueue, 'auto_delete' => false, 'arguments' => $ha_specific_connection)
);

/*
    name: $exchange
    type: direct
    passive: false
    durable: true // the exchange will survive server restarts
    auto_delete: false //the exchange won't be deleted once the channel is closed.
*/

$channel->exchangeDeclare($exchange, 'direct', array('durable' => true, 'auto_delete' => false));

$channel->queueBind($queue, $exchange);
$channel->queueBind($specificQueue, $exchange);

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
    nowait:
    callback: A PHP Callback
*/

$channel->basicConsume(
    array( 'queue' => $queue, 'consumer_tag' => $consumerTag, 'callback' => 'process_message')
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
