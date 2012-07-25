<?php
/**
 *
 */
include(__DIR__ . '/config.php');
use AMQP\Channel;
use AMQP\Connection;
use AMQP\Message;

$exchange = 'bench_exchange';
$queue = 'bench_queue';
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

register_shutdown_function(
    function () use ($ch, $conn)
    {
        $ch->close();
        $conn->close();
    }
);

while (count($ch->callbacks)) {
    $ch->wait();
}

