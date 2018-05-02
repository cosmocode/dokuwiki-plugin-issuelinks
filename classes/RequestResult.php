<?php

namespace dokuwiki\plugin\issuelinks\classes;

class RequestResult
{
    public $code;
    public $body;

    public function __construct($code = null, $body = null)
    {
        $this->code = $code;
        $this->body = $body;
    }
}
