<?php
namespace AMQP;

/**
 *
 */
use AMQP\Wire\GenericContent;

/**
 *
 * A Message for use with the Channnel.basic_* methods.
 *
 * @property string $content_type
 * @property string $content_encoding
 * @property array  $application_headers
 * @property string $delivery_mode
 * @property string $priority
 * @property string $correlation_id
 * @property string $reply_to
 * @property string $expiration
 * @property string $message_id
 * @property string $timestamp
 * @property string $type
 * @property string $user_id
 * @property string $app_id
 * @property string $cluster_id
 */
class Message extends GenericContent
{

    protected static $propertyTable
        = array(
            "content_type"        => "shortstr",
            "content_encoding"    => "shortstr",
            "application_headers" => "table",
            "delivery_mode"       => "octet",
            "priority"            => "octet",
            "correlation_id"      => "shortstr",
            "reply_to"            => "shortstr",
            "expiration"          => "shortstr",
            "message_id"          => "shortstr",
            "timestamp"           => "timestamp",
            "type"                => "shortstr",
            "user_id"             => "shortstr",
            "app_id"              => "shortstr",
            "cluster_id"          => "shortst"
        );

    public function __construct($body = '', $properties = array())
    {
        $this->body = $body;

        parent::__construct($properties, $propTypes = self::$propertyTable);
    }
}
