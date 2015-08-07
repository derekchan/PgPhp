<?php

namespace pg;

/**
 * Wrapper for the Postgres Simple Query API
 */
class Query
{
    private $q;
    private $r;

    private $copyData;

    function __construct ($q = '') {
        $this->setQuery($q);
    }

    function setQuery ($q) {
        $this->q = $q;
    }

    function getQuery () {
        return $this->q;
    }

    function addResult (Result $res) {
        $this->r[] = $res;
    }

    function getResults () {
        return $this->r;
    }

    function pushCopyData ($dt) {
        $this->copyData[] = $dt;
    }

    function popCopyData () {
        return array_pop($this->copyData);
    }

}

