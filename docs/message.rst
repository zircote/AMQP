===============
\\AMQP\\Message
===============

----------------------------
Supported Message Properties
----------------------------

+---------------------+---------------+
| Property Name       | Property Type |
+=====================+===============+
| content_type        | shortstr      |
+---------------------+---------------+
| content_encoding    | shortstr      |
+---------------------+---------------+
| application_headers | table         |
+---------------------+---------------+
| delivery_mode       | octet         |
+---------------------+---------------+
| priority            | octet         |
+---------------------+---------------+
| correlation_id      | shortstr      |
+---------------------+---------------+
| reply_to            | shortstr      |
+---------------------+---------------+
| expiration          | shortstr      |
+---------------------+---------------+
| message_id          | shortstr      |
+---------------------+---------------+
| timestamp           | timestamp     |
+---------------------+---------------+
| type                | shortstr      |
+---------------------+---------------+
| user_id             | shortstr      |
+---------------------+---------------+
| app_id              | shortstr      |
+---------------------+---------------+
| cluster_id          | shortst       |
+---------------------+---------------+


Getting message properties
----------------------------

.. code-block:: php

  /* @var \AMQP\Message $message */
  $message->get('type');
  $message->get('delivery_mode');


Acknowledging messages
----------------------

.. code-block:: php

  /* @var \AMQP\Message $message */
  $message->delivery_info['channel']
    ->basicAck($message->delivery_info['delivery_tag']);

  $deliveryInfo = $message->get('delivery_info');
  $deliveryInfo['channel']
    ->basicAck($deliveryInfo['delivery_tag']);

Delivery Info Array
---------------------

.. code-block:: php

  /* @var \AMQP\Message $message */
  $deliveryInfo = $message->get('delivery_info');
  $delivery_info = array(
    'channel' => (\AMQP\Channel) $channel,
    'consumer_tag' => $consumer_tag,
    'delivery_tag' => $delivery_tag,
    'redelivered' => $redelivered,
    'exchange' => $exchange,
    'routing_key' => $routing_key
  );

Alternative Property Access
----------------------------

.. code-block:: php

  /* @var \AMQP\Message $message */
  $message->get('channel');

