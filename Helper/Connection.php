<?php

namespace Loyaltylion\Core\Helper;

class Connection extends \Magento\Framework\HTTP\Client\Curl
{
    private $_token;
    private $_secret;
    private $_base_uri;
    private $_curl;

    public function __construct($curl, $token, $secret, $base_uri)
    {
        $this->_curl = $curl;
        $this->_token = $token;
        $this->_secret = $secret;
        $this->_base_uri = $base_uri;
    }

    public function post($path, $data = [])
    {
        return $this->_request("POST", $path, $data);
    }

    public function put($path, $data = [])
    {
        return $this->_request("PUT", $path, $data);
    }

    private function _request($method, $path, $data)
    {
        $this->_curl->setTimeout(5);
        $this->_curl->setCredentials($this->_token, $this->_secret);
        $this->_curl->setOption(
            CURLOPT_USERAGENT,
            "loyaltylion-php-client-v2.0.1"
        );
        $this->_curl->addHeader("Content-Type", "application/json");
        $uri = $this->_base_uri . $path;

        switch ($method) {
            case "POST":
                $this->_curl->post($uri, json_encode($data));
                break;
            case "PUT":
                $this->_curl->setOption(CURLOPT_POSTFIELDS, json_encode($data));
                $this->_curl->makeRequest("PUT", $uri);
                break;
        }

        $body = $this->_curl->getBody();
        $headers = $this->_curl->getHeaders();
        $status = $this->_curl->getStatus();

        $response = [
            "status" => $status,
            "headers" => $headers,
            "body" => $body,
        ];

        return (object) $response;
    }
}
