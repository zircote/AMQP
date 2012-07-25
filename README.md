# php-amqplib #

This library is a _pure PHP_ implementation of the AMQP protocol.

## Setup ##


## Usage ##

host is defined by passing a URN string as first connection parameter:

    `amqp://user:pass@hostname:port/vhost`
    `amqps://user:pass@hostname:port/vhost`

connection options are passed as the second parameter of the connection:

```php
<?php

$amqp = 'amqp://user:pass@hostname:port/vhost';

// Options Options Array
$options = array(
    'insist' => false,
    'login_method' => \AMQP\Connection::AMQP_AUTH_PLAIN,
    'login_response' => null,
    'locale' => 'en_US',
    'connection_timeout' => 3,
    'read_write_timeout' => 3,
    'context' => null,
    'ssl_options' => array()
);
$connection = new \AMQP\Connection($amqp, $options);


```

With RabbitMQ running open two Terminals and on the first one execute the following commands to _start the consumer:

    $ cd php-amqplib/demo
    $ php amqp_consumer.php

Then on the other Terminal do:

    $ cd php-amqplib/demo
    $ php amqp_publisher.php some text to publish

You should see the message arriving to the process on the other Terminal

Then to stop the consumer, send to it the `quit` message:

    $ php amqp_publisher.php quit

If you need to listen to the sockets used to connect to RabbitMQ then see the example in the non blocking consumer.

    $ php amqp_consumer_non_blocking.php

## More Examples ##

- `amqp_ha_consumer.php`: demoes the use of mirrored queues
- `amqp_consumer_exclusive.php` and `amqp_publisher_exclusive.php`: demoes fanout exchanges using exclusive queues.
- `amqp_consumer_fanout_{1,2}.php` and `amqp_publisher_fanout.php`: demoes fanout exchanges with named queues.
- `basic_get.php`: demoes obtaining messages from the queues by using the _basic get_ AMQP call.

## Loading Classes ##

    $ ant setup

Place the following in your bootstrap once composer has been installed in the project:

```php
<?php

require_once(dirname(__DIR__) . '/vendor/autoload.php');

```

## Debugging ##

If you want to know what's going on at a protocol level then add the following constant to your code:

```php
<?php
define('AMQP_DEBUG', true);

... more code

?>
```

## Benchmarks ##

To run the publishing/consume benchmark type:

    $ ant benchmark

## Tests ##

Once your environment is set up you can run your tests like this:

    $ ant build



