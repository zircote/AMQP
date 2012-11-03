<?php
namespace AMQP\Wire;

/**
 *
 */
use AMQP\Wire\Reader;
use AMQP\Wire\Writer;

/**
 * Abstract base class for AMQP content.  Subclasses should override
 * the PROPERTIES attribute.
 *
 */
class GenericContent
{

    /**
     * @var string
     */
    public $delivery_info;

    /**
     * @var string
     */
    public $body;
    /**
     * @var array|null
     */
    protected $propertyTypes = array();
    /**
     * @var string
     */
    protected $content_encoding;

    /**
     * @var array
     */
    protected static $propertyTable
        = array(
            "dummy" => "shortstr"
        );
    /**
     * @var array
     */
    protected $properties = array();

    public function __construct($props, $propertyTypes = null)
    {
        if ($propertyTypes) {
            $this->propertyTypes = $propertyTypes;
        } else {
            $this->propertyTypes = self::$propertyTable;
        }
        if ($props) {
            $d = array_intersect_key($props, $this->propertyTypes);
        } else {
            $d = array();
        }
        $this->properties = $d;
    }


    /**
     * Look for additional properties in the 'properties' dictionary,
     * and if present - the 'delivery_info' dictionary.
     */
    public function get($name)
    {
        if (isset($this->properties[$name])) {
            return $this->properties[$name];
        }

        if (isset($this->delivery_info) && isset($this->delivery_info[$name])) {
            return $this->delivery_info[$name];
        }

        throw new \OutOfBoundsException("No such property");
    }


    /**
     * Given the raw bytes containing the property-flags and
     * property-list from a content-frame-header, parse and insert
     * into a dictionary stored in this object as an attribute named
     * 'properties'.
     */
    public function load_properties($rawBytes)
    {
        $reader = new Reader($rawBytes);
        $flagBits = false;
        // Read 16-bit shorts until we get one with a low bit set to zero
        $flags = array();
        while (true) {
            $flagBits = $reader->readShort();
            $flags[] = $flagBits;
            if (($flagBits & 1) == 0) {
                break;
            }
        }

        $shift = 0;
        $data = array();
        foreach ($this->propertyTypes as $key => $proptype) {
            if ($shift == 0) {
                if (!$flags) {
                    break;
                }
                $flagBits = array_shift($flags);
                $shift = 15;
            }
            if ($flagBits & (1 << $shift)) {
                $proptype = ucfirst($proptype);
                $data[$key] = $reader->{'read' . $proptype}();
            }

            $shift -= 1;
        }
        $this->properties = $data;
    }

    /**
     * @todo add runtime support for message decode
     * @throws \RuntimeException
     */
    public function decode()
    {
        $this->body;
        $this->content_encoding;
        throw new \RuntimeException('decode is currently unsupproted');
        return $this->body;
    }


    /**
     * serialize the 'properties' attribute (a dictionary) into the
     * raw bytes making up a set of property flags and a property
     * list, suitable for putting into a content frame header.
     */
    public function serializeProperties()
    {
        $shift = 15;
        $flagBits = 0;
        $flags = array();
        $rawBytes = new Writer();

        foreach ($this->propertyTypes as $key => $proptype) {
            if (isset($this->properties[$key])) {
                $value = $this->properties[$key];
            } else {
                $value = null;
            }

            if ($value != null) {
                if ($shift == 0) {
                    $flags[] = $flagBits;
                    $flagBits = 0;
                    $shift = 15;
                }

                $flagBits |= (1 << $shift);
                if ($proptype != 'bit') {
                    $proptype = ucfirst($proptype);
                    $rawBytes->{'write' . $proptype}($value);
                }

            }
            $shift -= 1;
        }

        $flags[] = $flagBits;
        $result = new Writer();
        foreach ($flags as $flagBits) {
            $result->writeShort($flagBits);
        }

        $result->write($rawBytes->getvalue());

        return $result->getvalue();
    }
}
