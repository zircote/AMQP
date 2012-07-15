<?php

namespace AMQPTests\Unit;

use AMQP\Wire\Reader;
use AMQP\Wire\Writer;


class AMQPWriterTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var AMQP\Wire\Writer
     */
    protected $_writer;
    
    public function setUp()
    {
        $this->_writer = new Writer();
    }
    
    public function tearDown()
    {
        $this->_writer = null;
    }
    
    public function testWriteArray()
    {
        $this->_writer->writeArray(array(
            'rabbit@localhost',
            'hare@localhost'
        ));
        
        $out = $this->_writer->getvalue();
        
        $this->assertEquals(44, strlen($out));
        
        $expected = "\x00\x00\x00(S\x00\x00\x00\x10rabbit@localhostS\x00\x00\x00\x0Ehare@localhost";
        
        $this->assertEquals($expected, $out);
    }
}
