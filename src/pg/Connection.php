<?php

namespace pg;

/**
 * Wrapper for a Socket connection to postgres.
 */
class Connection
{
    public $debug = false;
    private $sock;
    private $host;
    private $port;

    private $database;

    private $dbUser;
    private $dbPass;

    private $connected = false;

    /** Connection parameters, given by postgres during setup. */
    private $params = array();

    function __construct ($host = 'localhost', $port = 5432, $database = null, $dbUser = null, $dbPass = null) {

        $this->host = $host;
        $this->port = $port;
        $this->database = $database;
        $this->dbUser   = $dbUser;
        $this->dbPass   = $dbPass;

        if (! ($this->sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP))) {
            throw new \Exception("Failed to create inet socket", 7895);
        } else if (! socket_connect($this->sock, $this->host, $this->port)) {
            throw new \Exception("Failed to connect inet socket ({$this->host}, {$this->port})", 7564);
        }
    }


    function connect ($database=null, $dbUser=null, $dbPass=null) {

        $this->database = $database ?: $this->database;
        $this->dbUser   = $dbUser   ?: $this->dbUser;
        $this->dbPass   = $dbPass   ?: $this->dbPass;

        $w = new wire\Writer();
        $r = new wire\Reader();

        $w->writeStartupMessage($this->dbUser, $this->database);
        $this->write($w->get());

        // Read Authentication message
        $resp = $this->read();
        $r->set($resp);
        $msgs = $r->chomp();
        if (count($msgs) != 1) {
            throw new \Exception("Connect Error (1) expected a single message response", 986);
        } else if ($msgs[0]->getType() != 'R') {
            throw new \Exception("Connect Error (2) unexpected message type", 987);
        }

        $auth = $this->getAuthResponse($msgs[0]);

        // Write Authentication response
        $w->clear();
        $w->writePasswordMessage($auth);
        $this->write($w->get());

        // expect BackendKeyData, ParameterStatus, ErrorResponse or NoticeResponse
        $resp = $this->read();
        $r->set($resp);
        $msgs = $r->chomp();

        if (! $msgs) {
            throw new \Exception("Connect Error (3) - no auth response", 8065);
        } else if ($msgs[0]->getName() !== 'AuthenticationOk') {
            throw new \Exception("Connect Error (4) - Auth failed.", 8065);
        }
        $c = count($msgs);

        for ($i = 1; $i < $c; $i++) {
            switch ($msgs[$i]->getName()) {
                case 'ParameterStatus':
                    list($k, $v) = $msgs[$i]->getData();
                    $this->params[$k] = $v;
                    break;
                case 'ReadyForQuery':
                    $this->connected = true;
                    break;
                case 'ErrorResponse':
                    throw new PgException($msgs[$i]->getName(), 7585);
                case 'NoticeResponse':
                    throw new \Exception("Connect failed (6) - TODO: Test and implement", 8765);
            }
        }

    }

    private function getAuthResponse (wire\Message $authMsg) {
        list($authType, $salt) = $authMsg->getData();
        switch ($authType) {
            case 5:
                $cryptPwd2 = $this->pgMd5Encrypt($this->dbPass, $this->dbUser);
                $cryptPwd = $this->pgMd5Encrypt(substr($cryptPwd2, 3), $salt);
                return $cryptPwd;
        }
    }

    private function pgMd5Encrypt ($passwd, $salt) {
        $buff = $passwd . $salt;
        return "md5" . md5($buff);
    }

    function write ($buff) {
        $bw = 0;
        $contentLength = strlen($buff);
        if ($this->debug) {
            printf("Write:\n%s\n", Pg::hexdump($buff));
        }
        while (true) {
            if (($tmp = socket_write($this->sock, $buff)) === false) {
                throw new \Exception(sprintf("\nSocket write failed: %s\n",
                    $this->strError()), 7854);
            }
            $bw += $tmp;
            if ($bw < $contentLength) {
                $buff = substr($buff, $bw);
            } else {
                break;
            }
        }
        return $bw;
    }


    function select ($tvSec = null, $tvUsec = 0) {
        $read = $write = $ex = null;
        $read = array($this->sock);

        $this->interrupt = false;
        $ret = socket_select($read, $write, $ex, $tvSec, $tvUsec);
        if ($ret === false && $this->lastError() == SOCKET_EINTR) {
            $this->interrupt = true;
        }
        return $ret;
    }


    function read () {
        $select = $this->select(5);
        $buff = '';
        if ($select === false) {
            return false;
        } else if ($select > 0) {
            $buff = $this->readAll();
        }
        return $buff;
    }


    function lastError () {
        return socket_last_error();
    }

    function strError () {
        return socket_strerror($this->lastError());
    }

    function readAll ($readLen = 4096) {
        $buff = '';
        while (@socket_recv($this->sock, $tmp, $readLen, MSG_DONTWAIT)) {
            $buff .= $tmp;
        }
        if ($this->debug) {
            printf("Read:\n%s\n", Pg::hexdump($buff));
        }

        return $buff;
    }


    function close () {
        $w = new wire\Writer;
        $w->writeTerminate();
        $this->write($w->get());
        $this->connected = false;
        socket_close($this->sock);
    }


    /**
     * Invoke the given query and store all result messages in $q.
     * @param Query $q
     * @throws PgException
     * @throws \Exception
     */
    function runQuery (Query $q) {
        if (! $this->connected) {
            throw new \Exception("Query run failed (0)", 735);
        }
        $w = new wire\Writer;
        $w->writeQuery($q->getQuery());
        if (! $this->write($w->get())) {
            throw new \Exception("Query run failed (1)", 736);
        }

        $complete = false;
        $r = new wire\Reader;
        $rSet = null;
        while (! $complete) {
            $this->select();
            if (! ($buff = $this->readAll())) {
                trigger_error("Query read failed", E_USER_WARNING);
                break;
            }

            $r->append($buff);
            $msgs = $r->chomp();
            foreach ($msgs as $m) {
                switch ($m->getName()) {
                    case 'RowDescription':
                        $rSet = new ResultSet($m);
                        break;
                    case 'RowData':
                        if (! $rSet) {
                            throw new \Exception("Illegal state - no current row container", 1749);
                        }
                        $rSet->addRow($m);
                        break;
                    case 'CommandComplete':
                        if ($rSet) {
                            $q->addResult($rSet);
                            $rSet = null;
                        } else {
                            $q->addResult(new Result($m));
                        }
                        break;
                    case 'ErrorResponse':
                        // Note that responses and response data from previous commands will
                        // still be available as normal in the calling code (although this, and
                        // subsequent responses aren't!)
                        throw new PgException($m, 8751);
                    case 'ReadyForQuery':
                        $complete = true;
                        break;
                    case 'CopyInResponse':
                        if ($cir = $q->popCopyData()) {
                            $w->clear();
                            $w->writeCopyData($cir);
                            $w->writeCopyDone();
                            $this->write($w->get());
                        } else {
                            $w->clear();
                            $w->writeCopyFail('No input data provided');
                            $this->write($w->get());
                        }
                        break;
                    case 'NotificationResponse':
                        $this->handleNotify($m);
                        break;
                }
            }
        }
    }



    function addChannelListener ($cName, $callback) {
        if (preg_match('/[^a-zA-Z0-9_]/', $cName)) {
            throw new \Exception("Invalid channel name", 3476);
        }
        $q = new Query("LISTEN $cName");
        $rs = $this->runQuery($q);
        var_dump($rs); // TODO: Make sure we're attached!
        $this->notifiers[$cName] = $callback;
    }

    function testSelect () {
        echo "Call Select\n";
        $select = $this->select();
        echo "Select returned\n";
        $buff = '';
        if ($select === false) {
            return false;
        } else if ($select > 0) {
            $buff = $this->readAll();
        }
        return $buff;
    }

    function handleNotify (wire\Message $m) {
        $nData = $m->getData();
        if (! array_key_exists($nData[1], $this->notifiers)) {
            trigger_error("Received notice on unexpected channel", E_USER_WARNING);
            return false;
        } else {
            $nf = $this->notifiers[$nData[1]];
            $nf($m);
        }
    }
}

