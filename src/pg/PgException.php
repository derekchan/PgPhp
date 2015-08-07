<?php

namespace pg;

/**
 * Exception wrapper for a Pg ErrorResponse message
 */
class PgException extends \Exception
{
    private $eData;
    function __construct (wire\Message $em = null, $errNum = 0) {
        if (!$em) {
            parent::__construct("Invalid error condition - no message supplied", 7643);
        } else if ($em->getName() != 'ErrorResponse') {
            parent::__construct("Unexpected input message for PgException: " . $em->getName(), 3297);
        } else {
            $errCode = -1;
            $errMsg = '(no pg error message found)';
            foreach ($em->getData() as $eField) {
                $this->eData[$eField[0]] = $eField[1];
                switch ($eField[0]) {
                    case Pg::ERR_CODE:
                        $errCode = $eField[1];
                        break;
                    case Pg::ERR_MESSAGE:
                        $errMsg = $eField[1];
                        break;
                }
            }
            parent::__construct($errMsg, $errNum);
        }
    }

    function getErrorFields () {
        return $this->eData;
    }

    function getSqlState () {
        return (isset($this->eData[Pg::ERR_CODE])) ?
            $this->eData[Pg::ERR_CODE]
            : false;
    }
}
