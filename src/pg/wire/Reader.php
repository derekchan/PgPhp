<?php

namespace pg\wire;

class Reader
{
    private $buff = '';
    private $buffLen = 0;
    private $p = 0;
    private $msgLen = 0;

    function __construct ($buff = '') {
        $this->set($buff);
    }

    function get () {
        return $this->buff;
    }
    function set ($buff) {
        $this->buff = $buff;
        $this->buffLen = strlen($buff);
        $this->p = 0;
    }
    function clear () {
        $this->buff = '';
        $this->p = $this->buffLen = 0;
    }
    function isSpent () {
        return ! ($this->p < $this->buffLen);
    }
    function hasN ($n) {
        return ($n == 0) || ($this->p + $n <= $this->buffLen);
    }

    function append ($buff) {
        $this->buff .= $buff;
        $this->buffLen += strlen($buff);
    }


    /**
     * Read and return up to $n messages, formatted as Message objects.
     */
    function chomp ($n = 0) {
        $i = $max = 0;
        $ret = array();
        while ($this->hasN(5) && ($n == 0 || $i++ < $n)) {
            $msgType = substr($this->buff, $this->p, 1);
            $tmp = unpack("N", substr($this->buff, $this->p + 1));
            $this->msgLen = array_pop($tmp);

            if (! $this->hasN($this->msgLen)) {
                // Split response message, calling code is now expected to read more
                // data from the Connection and append to *this* reader to complete.
                break;
            }
            $this->p += 5;

            switch ($msgType) {
                case 'R':
                    $ret[] = $this->readAuthentication();
                    break;
                case 'K':
                    $ret[] = $this->readBackendKeyData();
                    break;
                case 'B':
                    $ret[] = $this->readBind();
                    break;
                case '2':
                    $ret[] = $this->readBindComplete();
                    break;
                case '3':
                    $ret[] = $this->readCloseComplete();
                    break;
                case 'C':
                    $ret[] = $this->readCommandComplete();
                    break;
                case 'd':
                    $ret[] = $this->readCopyData();
                    break;
                case 'c':
                    $ret[] = $this->readCopyDone();
                    break;
                case 'G':
                    $ret[] = $this->readCopyInResponse();
                    break;
                case 'H':
                    $ret[] = $this->readCopyOutResponse();
                    break;
                case 'D':
                    $ret[] = $this->readDataRow();
                    break;
                case 'I':
                    $ret[] = $this->readEmptyQueryResponse();
                    break;
                case 'E':
                    $ret[] = $this->readErrorResponse();
                    break;
                case 'V':
                    $ret[] = $this->readFunctionCallResponse();
                    break;
                case 'n':
                    $ret[] = $this->readNoData();
                    break;
                case 'N':
                    $ret[] = $this->readNoticeResponse();
                    break;
                case 'A':
                    $ret[] = $this->readNotificationResponse();
                    break;
                case 't':
                    $ret[] = $this->readParameterDescription();
                    break;
                case 'S':
                    $ret[] = $this->readParameterStatus();
                    break;
                case '1':
                    $ret[] = $this->readParseComplete();
                    break;
                case 's':
                    $ret[] = $this->readPortalSuspended();
                    break;
                case 'Z':
                    $ret[] = $this->readReadyForQuery();
                    break;
                case 'T':
                    $ret[] = $this->readRowDescription();
                    break;
                default:
                    throw new \Exception("Unknown message type", 98765);
            }
        }
        return $ret;
    }

    /**
     * Accounts for many different possible auth messages.
     */
    function readAuthentication () {
        $tmp = unpack('N', substr($this->buff, $this->p));
        $authType = reset($tmp);
        $this->p += 4;
        switch ($authType) {
            case 0:
                return new Message('AuthenticationOk', 'R', array($authType));
            case 2:
                return new Message('AuthenticationKerberosV5', 'R', array($authType));
            case 3:
                return new Message('AuthenticationCleartextPassword', 'R', array($authType));
            case 5:
                $salt = substr($this->buff, $this->p, 4);
                $this->p += 4;
                return new Message('AuthenticationMD5Password', 'R', array($authType, $salt));
            case 6:
                return new Message('AuthenticationSCMCredential', 'R', array($authType));
            case 7:
                return new Message('AuthenticationGSS', 'R', array($authType));
            case 8:
                throw new \Exception("Unsupported auth message: AuthenticationGSSContinue", 6745);
            case 9:
                return new Message('AuthenticationSSPI', 'R', array($authType));
            default:
                throw new \Exception("Unknown auth message type: {$authType}", 3674);

        }
    }

    function readBackendKeyData () {
        $tmp = unpack('Ni/Nj', substr($this->buff, $this->p));
        $this->p += 8;
        return new Message('BackendKeyData', 'K', array_values($tmp));
    }

    function readBindComplete () {
        return new Message('BindComplete', '2', array());
    }

    function readCloseComplete () {
        throw new \Exception("Message read method not implemented: " . __METHOD__);
    }

    function readCommandComplete () {
        return new Message('CommandComplete', 'C', array($this->_readString()));
    }

    function readCopyData () {
        $data = array(substr($this->buff, $this->p, $this->msgLen - 4));
        $this->p += $this->msgLen - 4;
        $ret =  new Message('CopyData', 'd', $data);
        return $ret;
    }

    function readCopyDone () {
        return new Message('CopyDone', 'C', array());
    }

    function readCopyInResponse () {
        return $this->copyResponseImpl('CopyInResponse', 'G');
    }

    private function copyResponseImpl ($msgName, $msgCode) {
        $t = unpack('Ca/nb', substr($this->buff, $this->p));
        $data = array_values($t);
        $this->p += 3;
        $cols = array();
        for ($i = 0; $i < $data[1]; $i++) {
            $t = unpack('n', substr($this->buff, $this->p));
            $cols[] = reset($t);
            $this->p += 2;
        }
        $data[] = $cols;
        return new Message($msgName, $msgCode, $data);
    }

    function readCopyOutResponse () {
        return $this->copyResponseImpl('CopyOutResponse', 'H');
    }

    function readDataRow () {
        $data = array();
        $ep = $this->p + $this->msgLen - 5;
        $tmp = unpack('n', substr($this->buff, $this->p));
        $this->p += 2;
        $data[] = reset($tmp);

        while ($this->p < $ep) {
            $row = array();
            $fLen = substr($this->buff, $this->p, 4);
            $this->p += 4;
            if ($fLen === "\xff\xff\xff\xff") {
                // This is a NULL, map to a null
                $row = array(0, NULL);
            } else {
                $tmp = unpack('N', $fLen);
                $row[] = reset($tmp);
                $row[] = substr($this->buff, $this->p, $row[0]);
                $this->p += $row[0];
            }
            $data[] = $row;
        }
        return new Message('RowData', 'D', $data);
    }

    function readEmptyQueryResponse () {
        return new Message('EmptyQueryResponse', 'I', array());
    }

    function readErrorResponse () {
        $data = $this->readNoticeDataError();
        return new Message('ErrorResponse', 'E', $data);
    }

    private function readNoticeDataError () {
        $data = array();
        $ep = $this->p + $this->msgLen - 5;
        while ($this->p < $ep) {
            $ft = substr($this->buff, $this->p++, 1);
            $row = array($ft, $this->_readString());
            $data[] = $row;
        }
        $tmp = unpack('C', substr($this->buff, $this->p++));
        if (reset($tmp) !== 0) {
            throw new \Exception("Protocol error - missed error response end", 4380);
        }
        return $data;
    }

    function readFunctionCallResponse () {
        throw new \Exception("Message read method not implemented: " . __METHOD__);
    }

    function readNoData () {
        return new Message('NoData', 'n', array());
    }

    function readNoticeResponse () {
        $data = $this->readNoticeDataError();
        return new Message('NoticeResponse', 'N', $data);
    }

    function readNotificationResponse () {
        throw new \Exception("Message read method not implemented: " . __METHOD__);
    }

    function readParameterDescription () {
        $data = array();
        $tmp = unpack('n', substr($this->buff, $this->p));
        $this->p += 2;
        $nParams = reset($tmp);
        for ($i = 0; $i < $nParams; $i++) {
            $tmp = unpack('N', substr($this->buff, $this->p));
            $this->p += 4;
            $data[] = reset($tmp);
        }
        return new Message('ParameterDescription', 't', $data);
    }

    function readParameterStatus () {
        $data = array();
        $data[] = $this->_readString();
        $data[] = $this->_readString();
        return new Message('ParameterStatus', 'S', $data);
    }

    function readParseComplete () {
        return new Message('ParseComplete', 'B', array());
    }

    function readPortalSuspended () {
        throw new \Exception("Message read method not implemented: " . __METHOD__);
    }

    function readReadyForQuery () {
        return new Message('ReadyForQuery', 'Z', array(substr($this->buff, $this->p++, 1)));
    }

    function readRowDescription () {
        $data = array();
        $ep = $this->p + $this->msgLen - 4;
        $tmp = unpack('n', substr($this->buff, $this->p));
        $this->p += 2;
        $data[] = $tmp;

        while ($this->p < $ep) {
            $row = array();
            $row[] = $this->_readString();
            $tmp = unpack('Na/nb/Nc/nd/Ne/nf', substr($this->buff, $this->p));
            $row = array_merge($row, array_values($tmp));
            $this->p += 18;
            $data[] = $row;
        }
        return new Message('RowDescription', 'T', $data);
    }

    private function _readString () {
        $r = substr($this->buff, $this->p, strpos($this->buff, "\x00", $this->p) - $this->p);
        $this->p += strlen($r) + 1;
        return $r;
    }
}