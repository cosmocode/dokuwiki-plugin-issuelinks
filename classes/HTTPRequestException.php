<?php

namespace dokuwiki\plugin\issuelinks\classes;

/**
 * Class HTTPRequestException
 *
 * A translatable exception
 *
 * @package dokuwiki\plugin\issuelinks\classes
 */
class HTTPRequestException extends IssueLinksException {
    protected $httpError;
    protected $responseBody;
    protected $url;
    protected $method;

    public function __construct($message, \DokuHTTPClient $httpClient, $url, $method) {
        $this->code = $httpClient->status;
        $this->httpError = $httpClient->error;
        $this->responseBody = $httpClient->resp_body;
        $this->url = $url;
        $this->method = $method;

        parent::__construct($message, $this->getCode(), $this->httpError);
    }

    /**
     * @return string
     */
    public function getHttpError() {
        return $this->httpError;
    }

    /**
     * @return string
     */
    public function getResponseBody() {
        return $this->responseBody;
    }

    /**
     * @return string
     */
    public function getUrl() {
        return $this->url;
    }

    /**
     * @return string
     */
    public function getMethod() {
        return $this->method;
    }


}
