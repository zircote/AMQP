<?php
namespace AMQP\Exception;

/**
 *
 */
use AMQP\Exception\Exception;

/**
 *
 */
class ChannelException extends Exception
{

    /**
     * @param string       $replyCode
     * @param int          $replyText
     * @param array|string $methodSig
     */
    public function __construct($replyCode, $replyText, $methodSig)
    {
        parent::__construct($replyCode, $replyText, $methodSig);
    }
}
