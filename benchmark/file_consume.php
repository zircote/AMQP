<?php

/**
 * Usage: php file_consume.php 100
 */

include(__DIR__ . '/config.php');
use AMQP\Connection;
use AMQP\Message;

$exchange = 'file_exchange';
$queue = 'file_queue';
$consumer_tag = '';

$conn = new Connection(AMQP_RESOURCE);
$ch = $conn->channel();

$ch->queueDeclare($queue, false, false, false, false);
$ch->exchangeDeclare($exchange, 'direct', false, false, false);
$ch->queueBind($queue, $exchange);

class Consumer
{

    protected $msgCount = 0;

    protected $startTime = null;

    public function process_message($msg)
    {
        if ($this->startTime === null) {
            $this->startTime = microtime(true);
        }

        if ($msg->body == 'quit') {
            echo sprintf(
                "Pid: %s, Count: %s, Time: %.4f\n", getmypid(), $this->msgCount,
                microtime(true) - $this->startTime
            );
            die;
        }
        $this->msgCount++;
    }
}

$ch->basicConsume(
    $queue, '', false, true, false, false,
    array( new Consumer(), 'process_message' )
);

function shutdown($ch, $conn)
{
    $ch->close();
    $conn->close();
}

register_shutdown_function('shutdown', $ch, $conn);

while (count($ch->_callbacks)) {
    $ch->wait();
}

