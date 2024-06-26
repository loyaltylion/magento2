<?php

namespace Loyaltylion\Core\Helper;

use Magento\Framework\HTTP\Client\Curl;

class Client
{
    private $_curl;

    public function __construct(\Magento\Framework\HTTP\Client\Curl $curl)
    {
        $this->_curl = $curl;
    }

    public function getClient($base_uri, $token, $secret)
    {
        $connection = new Connection($this->_curl, $token, $secret, $base_uri);
        $events = new Activities($connection);
        $orders = new Orders($connection);
        return [$connection, $events, $orders];
    }

    protected function parseResponse($response)
    {
        $result = [
            "success" =>
                (int) $response->status >= 200 &&
                (int) $response->status <= 204,
        ];

        if (!$result["success"]) {
            // even if curl succeeded, it can still fail if the request was
            // invalid - we usually have the error as the body so just stick that in
            $result["error"] = $response->body;
            $result["status"] = $response->status;
        }

        return (object) $result;
    }
}
