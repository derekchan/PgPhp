<?php
/**
 * Created by PhpStorm.
 * User: csy
 * Date: 2015/8/7
 * Time: 下午 3:15
 */

namespace pg\wire;

/**
 * Simple container class for a single protocol-level message.
 */
class Message
{
    /** Name of the message type */
    private $name;

    /** Character of the message type */
    private $char;

    /** Array of message data, all data fields in order, excluding
    the message type and message length fields. */
    private $data;

    function __construct ($name, $char, $data = array()) {
        $this->name = $name;
        $this->char = $char;
        $this->data = $data;
        if (! $this->name || ! $this->char) {
            throw new \Exception("Message type is not complete", 554);
        }
    }

    function getName () {
        return $this->name;
    }
    function getType () {
        return $this->char;
    }
    function getData () {
        return $this->data;
    }
}



