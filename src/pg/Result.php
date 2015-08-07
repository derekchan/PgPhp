<?php

namespace pg;


/**
 * Todo: remove support for ErrorResponse - no needed now that PgException is in place.
 */
class Result
{
    private $raw;
    private $resultType; // CommandComplete or ErrorResponse
    private $command; // update, insert, etc.
    private $commandOid; // oid portion of CommandComplete response (optional)
    private $affectedRows = 0; // # of rows affected by CommandComplete
    private $errData; // Assoc array of error data

    function __construct (wire\Message $m) {
        $this->raw = $m;
        switch ($this->resultType = $m->getName()) {
            case 'ErrorResponse':
                $this->errData = array();
                foreach ($m->getData() as $row) {
                    $this->errData[$row[0]] = $row[1];
                }
                break;
            case 'CommandComplete':
                $msg = $m->getData();
                $bits = explode(' ', $msg[0]);

                $this->command = trim(strtoupper(array_shift($bits)));
                if (count($bits) > 1) {
                    list($this->commandOid, $this->affectedRows) = $bits;
                } else {
                    $this->affectedRows = reset($bits);
                }
                break;
            case 'RowDescription':
                $this->command = 'INSERT';
                break;
        }
    }

    function getResultType () {
        return $this->raw->getName();
    }

    function getCommand () {
        return $this->command;
    }

    function getRowsAffected () {
        return $this->affectedRows;
    }

    function getErrDetail () {
        return $this->errData;
    }
}