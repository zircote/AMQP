<?php
namespace AMQP\Wire;
/**
 *
 */
/**
 * AMQP protocol decimal value.
 *
 * Values are represented as (n,e) pairs. The actual value
 * is n * 10^(-e).
 *
 * From 0.8 spec: Decimal values are
 * not intended to support floating point values, but rather
 * business values such as currency rates and amounts. The
 * 'decimals' octet is not signed.
 */
class Decimal
{

    /**
     * @var int
     */
    protected $_number;

    /**
     * @var int
     */
    protected $_power;

    /**
     * @param int $number
     * @param int $power
     */
    public function __construct($number, $power)
    {
        if ($this->_power < 0) {
            throw new \Exception('Decimal exponent value must be unsigned!');
        }
        $this->_number = $number;
        $this->_power = $power;
    }

    /**
     * @return string
     */
    public function asBCvalue()
    {
        return bcdiv($this->_number, bcpow(10, $this->_power));
    }
}
