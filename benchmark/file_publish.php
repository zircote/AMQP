<?php

/**
 * Usage:
 *  - Publish 100 5mb messages:
 *      php file_publish.php 100
 *  - Publish 1 5mb message:
 *      php file_publish.php
 *
 * NOTE: The script will take some time while it reads data from /dev/urandom
 */

include(__DIR__ . '/config.php');
use AMQP\Connection;
use AMQP\Message;

//suboptimal function to generate random content
function generate_random_content($bytes)
{
    $handle = @fopen('/dev/urandom', 'rb');
    $buffer = null;
    if ($handle) {
        $len = 0;
        $max = $bytes;
        while ($len < $max - 1) {
            $buffer .= fgets($handle, $max - $len);
            $len = strlen($buffer);
        }
        fclose($handle);
    }

    return $buffer;
}

$exchange = 'file_exchange';
$queue = 'file_queue';

$conn = new Connection(AMQP_RESOURCE);
$ch = $conn->channel();

$ch->queueDeclare($queue, false, false, false, false);
$ch->exchangeDeclare($exchange, 'direct', false, false, false);
$ch->queueBind($queue, $exchange);

$max = isset($argv[ 1 ]) ? (int)$argv[ 1 ] : 1;
$msg_size = 1024 * 1024 * 5 + 1;
$msg_body = generate_random_content($msg_size);

$msg = new Message($msg_body);

$time = microtime(true);

// Publishes $max messages using $msgBody as the content.
for ($i = 0; $i < $max; $i++) {
    $ch->basicPublish($msg, $exchange);
}

echo microtime(true) - $time, "\n";

$ch->basicPublish(new Message('quit'), $exchange);

$ch->close();
$conn->close();


