<?php

namespace pg;

class ResultSet extends Result implements \Iterator, \ArrayAccess, \Countable
{
    const ASSOC = 1;
    const NUMERIC = 2;

    public $fetchStyle = self::ASSOC;
    private $colNames = array();
    private $colTypes = array();
    private $rows = array();
    private $i = 0;

    function __construct (wire\Message $rDesc) {
        parent::__construct($rDesc);
        $this->initCols($rDesc);
    }

    function offsetExists ($n) {
        return array_key_exists($n, $this->rows);
    }

    function offsetGet ($n) {
        return $this->rows[$n];
    }

    function offsetSet ($ofs, $val) {
        throw new \Exception("ResultSet is a read-only data handler", 9865);
    }

    function offsetUnset ($ofs) {
        throw new \Exception("ResultSet is a read-only data handler [2]", 9866);
    }

    function count () {
        return count($this->rows);
    }

    function addRow (wire\Message $msg) {
        $aRow = array();
        foreach ($msg->getData() as $i => $dt) {
            if ($i == 0) {
                continue;
            }
            $aRow[] = $dt[1];
        }
        $this->rows[] = $aRow;
    }

    /** Munge the column meta data in to something that's easier to work with */
    private function initCols (wire\Message $msg) {
        foreach ($msg->getData() as $i => $row) {
            if ($i == 0) {
                continue;
            }
            $this->colNames[] = $row[0];
            $this->colTypes[] = $row[3];
        }
    }

    function rewind () {
        $this->i = 0;
    }

    function current () {
        return ($this->fetchStyle == self::ASSOC) ?
            array_combine($this->colNames, $this->rows[$this->i])
            : $this->rows[$this->i];
    }

    function key () {
        return $this->i;
    }

    function next () {
        $this->i++;
    }

    function valid () {
        return $this->i < count($this->rows);
    }
}
