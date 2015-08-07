<?php

namespace pg\wire;

class Writer
{
    private $buff;
    function __construct ($buff = '') {
        $this->buff = $buff;
    }

    function get () { return $this->buff; }
    function set ($buff) { $this->buff = $buff; }
    function clear () { $this->buff = ''; }

    // Lots of stuff hard-coded in here!
    function writeBind ($pName, $stName, $params=array()) {
        $buff = "{$pName}\x00{$stName}\x00\x00\x01\x00\x00" . pack('n', count($params));
        // Next, the following pair of fields appear for each parameter
        foreach ($params as $p) {
            $buff .= pack('N', strlen($p)) . $p;
        }
        $buff .= "\x00\x01\x00\x00";
        $this->buff .= 'B' . pack('N', strlen($buff) + 4) . $buff;
    }

    function writeCancelRequest() {
        throw new \Exception("Unimplemented writer method: " . __METHOD__);
    }

    function writeClose () {
        throw new \Exception("Unimplemented writer method: " . __METHOD__);
    }

    function writeCopyData ($data) {
        $this->buff .= 'd' . pack('N', 4 + strlen($data)) . "{$data}";
    }
    function writeCopyDone () {
        $this->buff .= 'c' . pack('N', 4);
    }
    function writeCopyFail ($reason) {
        $this->buff .= 'c' . pack('N', 5 + strlen($reason)) . "{$reason}\x00";
    }
    function writeDescribe ($flag, $name) {
        $this->buff .= "D" . pack('N', 6 + strlen($name)) . "${flag}{$name}\x00";
    }
    function writeExecute ($stName, $maxRows=0) {
        $this->buff .= 'E' . pack('N', strlen($stName) + 9) . "{$stName}\x00" . pack('N', $maxRows);

    }
    function writeFlush () {
        throw new \Exception("Unimplemented writer method: " . __METHOD__);
    }
    function writeFunctionCall () {
        throw new \Exception("Function call protocol message is not implemented, as per the advise here:" .
            "http://www.postgresql.org/docs/9.0/static/protocol-flow.html#AEN84425", 8961);
    }
    function writeParse ($stName, $q, $bindParams = array()) {
        $buff = "{$stName}\x00{$q}\x00" . pack('n', count($bindParams));
        foreach ($bindParams as $bp) {
            $buff .= pack('N', $bp);
        }
        $this->buff .= 'P' . pack('N', strlen($buff) + 4) . $buff;
    }
    function writePasswordMessage ($msg) {
        $this->buff .= 'p' . pack('N', strlen($msg) + 5) . "{$msg}\x00";
    }
    function writeQuery ($q) {
        $this->buff .= 'Q' . pack('N', strlen($q) + 5) . "{$q}\x00";
    }
    function writeSSLRequest () {
        throw new \Exception("Unimplemented writer method: " . __METHOD__);
    }

    function writeStartupMessage ($user, $database) {
        $start = pack('N', 196608);
        $start .= "user\x00{$user}\x00";
        $start .= "database\x00{$database}\x00\x00";
        $this->buff .= pack('N', strlen($start) + 4) . $start;
    }
    function writeSync () {
        $this->buff .= "S\x00\x00\x00\x04";
    }
    function writeTerminate () {
        $this->buff .= 'X' . pack('N', 4);
    }
}
