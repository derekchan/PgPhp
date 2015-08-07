<?php

namespace pg;


/**
 * This class is used to work with the extended query protocol, call parse() to send
 * the query to the Postgres server, then call execute one or more times to execute
 * the prepared query.  Results are returned from execute in the same way as from
 * Query->getResults().
 */
class Statement
{
    private $conn; // Underlying Connection object
    private $sql;  // SQL command
    private $name = false; // Name of the statement / portal
    private $ppTypes = array(); // Input parameter types
    private $canExecute = false; // Internal state flag
    private $paramDesc; // wire\Message of type ParameterDescription
    private $resultDesc; // wire\Message of type NoData or RowDescription


    function __construct (\pg\Connection $conn) {
        $this->conn = $conn;
        $this->reader = new wire\Reader;
        $this->writer = new wire\Writer;
    }

    function getState () { return $this->st; }


    function setSql ($q) {
        $this->sql = $q;
    }

    function setParseParamTypes (array $oids) {
        $this->ppTypes;
    }

    function setName ($name) {
        $this->name = $name;
    }

    // Sends protocol messages parse, describe, sync; blocks for response
    function parse () {
        $this->writer->clear();
        $this->writer->writeParse($this->name, $this->sql, $this->ppTypes);
        $this->writer->writeDescribe('S', $this->name);
        $this->writer->writeSync();
        $this->conn->write($this->writer->get());

        // Wait for the response
        $complete = false;
        $this->reader->clear();
        while (! $complete) {
            $this->reader->append($this->conn->read());
            foreach ($this->reader->chomp() as $m) {
                switch ($m->getName()) {
                    case 'RowDescription':
                    case 'NoData':
                        $this->resultDesc = $m;
                        break;
                    case 'ParameterDescription':
                        $this->paramDesc = $m;
                        break;
                    case 'ReadyForQuery':
                        $complete = true;
                        break;
                    case 'ErrorResponse':
                        throw new PgException($m, 7591);
                }
            }
        }
        $this->canExecute = true;
        return true;
    }

    // Sends protocol messages: bind, execute, sync, blocks for response
    function execute (array $params=array(), $rowLimit=0) {
        if (! $this->canExecute) {
            throw new \Exception("Statement is not ready to execute", 7425);
        }
        $this->writer->clear();
        $this->writer->writeBind($this->name, $this->name, $params);
        $this->writer->writeExecute($this->name, $rowLimit);
        $this->writer->writeSync();
        $this->conn->write($this->writer->get());

        $this->reader->clear();
        $complete = $rSet = false;
        $ret = array();

        while (! $complete) {
            $this->reader->append($this->conn->read());
            foreach ($this->reader->chomp() as $m) {
                switch ($m->getName()) {
                    case 'BindComplete':
                        // Ignore this one.
                        break;
                    case 'RowData':
                        if (! $rSet) {
                            $rSet = new ResultSet($this->resultDesc);
                        }
                        $rSet->addRow($m);
                        break;
                    case 'CommandComplete':
                        if ($rSet) {
                            $ret[] = $rSet;
                            $rSet = false;
                        } else {
                            $ret[] = new Result($m);
                        }
                        break;
                    case 'ReadyForQuery':
                        $complete = true;
                        break;
                    case 'ErrorResponse':
                        throw new PgException($m, 9653);
                }
            }
        }
        return $ret;
    }


    function close () {
    }
}
