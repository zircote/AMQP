<?php
namespace AMQPTests\Functional;
/**
 * @package
 * @category
 * @subcategory
 * Date: 7/15/12T6:25 AM
 */
/**
 * @package
 * @category
 * @subcategory
 */
class AbstractTestCase extends \PHPUnit_Framework_TestCase
{

    /**
     * @var string
     */
    protected $exchange_name = 'test_exchange';

    /**
     * @var null
     */
    protected $queue_name = null;

    /**
     * @var \AMQP\Connection
     */
    protected $conn;

    /**
     * @var \AMQP\Channel
     */
    protected $ch;

    /**
     * @var string
     */
    protected  $msg_body;

}
