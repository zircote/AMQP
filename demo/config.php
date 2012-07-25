<?php
include_once(dirname(__DIR__) . '/vendor/autoload.php');
define('AMQP_RESOURCE', 'amqp://php_amqp_user:php_amqp_pass@localhost:5672/php_amqp_vhost');

define('AMQP_SSL_RESOURCE', 'amqps://php_amqp_user:php_amqp_pass@localhost:5671/php_amqp_vhost');

define('CA_PATH', '/somepath/to/cacert.pem');
define('CERT_PATH', '/some/path/to/cert.pem');

//If this is enabled you can see AMQP output on the CLI
define('AMQP_DEBUG', true);
