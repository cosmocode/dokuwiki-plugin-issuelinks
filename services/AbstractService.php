<?php

namespace dokuwiki\plugin\issuelinks\services;

use dokuwiki\plugin\issuelinks\classes\ExternalServerException;
use dokuwiki\plugin\issuelinks\classes\HTTPRequestException;

abstract class AbstractService implements ServiceInterface
{
    protected static $instance = [];

    public static function getInstance($forcereload = false)
    {
        $class = static::class;
        if (empty(self::$instance[$class]) || $forcereload) {
            self::$instance[$class] = new $class();
        }
        return self::$instance[$class];
    }

    /**
     * Make an http request
     *
     * Throws an exception on errer or if the request was not successful
     *
     * You can query the $dokuHTTPClient for more result headers later
     *
     * @param \DokuHTTPClient $dokuHTTPClient an Instance of \DokuHTTPClient which will make the request
     * @param string          $url            the complete URL to which to make the request
     * @param array           $headers        This headers will be merged with the headers in the DokuHTTPClient
     * @param array           $data           an array of the data which is to be send
     * @param string          $method         a HTTP verb, like GET, POST or DELETE
     *
     * @return array the result that was returned from the server, json-decoded
     *
     * @throws HTTPRequestException
     */
    protected function makeHTTPRequest(\DokuHTTPClient $dokuHTTPClient, $url, $headers, array $data, $method)
    {
        $dokuHTTPClient->headers = array_merge($dokuHTTPClient->headers ?: [], $headers);
        $dataToBeSend = json_encode($data);
        try {
            $success = $dokuHTTPClient->sendRequest($url, $dataToBeSend, $method);
        } catch (\HTTPClientException $e) {
            throw new HTTPRequestException('request error', $dokuHTTPClient, $url, $method);
        }

        if (!$success || $dokuHTTPClient->status < 200 || $dokuHTTPClient->status > 206) {
            if ($dokuHTTPClient->status >= 500) {
                throw new ExternalServerException('request error', $dokuHTTPClient, $url, $method);
            }
            throw new HTTPRequestException('request error', $dokuHTTPClient, $url, $method);
        }
        return json_decode($dokuHTTPClient->resp_body, true);
    }

    public function getRepoPageText()
    {
        return '';
    }
}
