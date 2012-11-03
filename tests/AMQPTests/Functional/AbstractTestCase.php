<?php
namespace AMQPTests\Functional;

/**
 * @package
 * @category
 * @subcategory
 * Date: 7/15/12T6:25 AM
 */
use AMQP\Connection;
use AMQP\Message;

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
    protected $exchangeName = 'test_exchange';

    /**
     * @var null
     */
    protected $queueName = null;

    /**
     * @var \AMQP\Connection
     */
    protected $connection;

    /**
     * @var \AMQP\Channel
     */
    protected $channel;

    /**
     * @var string
     */
    protected  $msgBody;

    public function setUp()
    {
        $this->connection = new Connection(AMQP_TEST_HOST);
        $this->channel = $this->connection->channel();
        $this->channel->exchangeDeclare($this->exchangeName, 'direct', array('auto_delete' => false));
        list($this->queueName,,) = $this->channel->queueDeclare();
        $this->channel->queueBind($this->queueName, $this->exchangeName, array('routing_key' => $this->queueName));
    }

    public function tearDown()
    {
        if(!$this->channel){
            return;
        }
        $this->channel->exchangeDelete($this->exchangeName);
        $this->channel->close();
        $this->connection->close();
    }

}
