<?php

namespace pg;

/**
 *
 * Copyright (C) 2010, 2011 Robin Harvey (harvey.robin@gmail.com)
 *
 * This  library is  free  software; you  can  redistribute it  and/or
 * modify it under the terms  of the GNU Lesser General Public License
 * as published by the Free Software Foundation; either version 2.1 of
 * the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful, but
 * WITHOUT  ANY  WARRANTY;  without   even  the  implied  warranty  of
 * MERCHANTABILITY or  FITNESS FOR A PARTICULAR PURPOSE.   See the GNU
 * Lesser General Public License for more details.
 *
 * You should  have received a copy  of the GNU  Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation,  Inc.,  51 Franklin  Street,  Fifth  Floor, Boston,  MA
 * 02110-1301 USA
 */

/**
 * Implementation classes for Postgres wirelevel protocol, version 3 only.
 */
class Pg
{
    /**  These are the fields that are returned as part of a ErrorResponse response.
    /**  See http:/** www.postgresql.org/docs/9.0/static/protocol-message-formats.html

    /**
     * Severity: the field contents are ERROR, FATAL, or PANIC (in an error message), or WARNING, NOTICE, DEBUG, INFO, or LOG (in a notice message), or a localized translation of one of these. Always present.
     */
    const ERR_SEVERITY = 'S';
    
    /**
     * Code: the SQLSTATE code for the error (see Appendix A). Not localizable. Always present.
     */ 
    const ERR_CODE = 'C';
    
    /**
     * Message: the primary human-readable error message. This should be accurate but terse (typically one line). Always present.
     */ 
    const ERR_MESSAGE = 'M';
    
    /**
     * Detail: an optional secondary error message carrying more detail about the problem. Might run to multiple lines.
     */
    const ERR_DETAIL = 'D';
    /**
     * Hint: an optional suggestion what to do about the problem. This is intended to differ from Detail in that it offers advice (potentially inappropriate) rather than hard facts. Might run to multiple lines.
     */
    const ERR_HINT = 'H';
    /**
     * Position: the field value is a decimal ASCII integer, indicating an error cursor position as an index into the original query string. The first character has index 1, and positions are measured in characters not bytes.
     */
    const ERR_POSITION = 'P';
    /**
     * Internal position: this is defined the same as the P field, but it is used when the cursor position refers to an internally generated command rather than the one submitted by the client. The q field will always appear when this field appears.
     */
    const ERR_INTERNAL = 'p';
    /**
     * Internal query: the text of a failed internally-generated command. This could be, for example, a SQL query issued by a PL/pgSQL function.
     */
    const ERR_INTERNAL_QUERY = 'q';
    /**
     * Where: an indication of the context in which the error occurred. Presently this includes a call stack traceback of active procedural language functions and internally-generated queries. The trace is one entry per line, most recent first.
     */
    const ERR_WHERE = 'W';
    /**
     * File: the file name of the source-code location where the error was reported.
     */
    const ERR_FILE = 'F';
    /**
     * Line: the line number of the source-code location where the error was reported.
     */
    const ERR_LINE = 'L';
    /**
     * Routine: the name of the source-code routine reporting the error.
     */
    const ERR_ROUTINE = 'R';

    const HEXDUMP_BIN = '/usr/bin/hexdump -C';

    public static function hexdump($subject) {
        if ($subject === '') {
            return "00000000\n";
        }
        $pDesc = array(
            array('pipe', 'r'),
            array('pipe', 'w'),
            array('pipe', 'r')
        );
        $pOpts = array('binary_pipes' => true);
        if (($proc = proc_open(self::HEXUMP_BIN, $pDesc, $pipes, null, null, $pOpts)) === false) {
            throw new \Exception("Failed to open hexdump proc!", 675);
        }
        fwrite($pipes[0], $subject);
        fclose($pipes[0]);
        $ret = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $errs = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        if ($errs) {
            printf("[ERROR] Stderr content from hexdump pipe: %s\n", $errs);
        }
        proc_close($proc);
        return $ret;
    }
}