<?php
namespace AMQP\Exception;
/**
 *
 */
use AMQP\AbstractChannel;
use AMQP\Helper;

/**
 *
 */
class Exception extends \Exception
{

    /**
     * @var string
     */
    public $amqpMethodSig;

    /**
     * @var array
     */
    public $args;
    /**
     * @param string $replyCode
     * @param int    $replyText
     * @param string $methodSig
     */
    public function __construct($replyCode, $replyText, $methodSig)
    {
        parent::__construct($replyText, $replyCode);

        $this->amqpMethodSig = $methodSig;

        $ms = Helper::methodSig($methodSig);

        $mn = isset(AbstractChannel::$globalMethodNames[ $ms ])
            ? AbstractChannel::$globalMethodNames[ $ms ]
            : $mn = "";

        $this->args = array($replyCode, $replyText, $methodSig, $mn);
    }
}
