<?php

namespace Loyaltylion\Core\Helper;

class Client
{
    public function getClient($base_uri, $token, $secret)
    {
        $connection = new Connection($token, $secret, $base_uri);
        $events = new Activities($connection);
        $orders = new Orders($connection);
        return [$connection, $events, $orders];
    }

    protected function parseResponse($response)
    {
        if (isset($response->error)) {
            // this kind of error is from curl itself
            // e.g. a request timeout, so just return that error
            return (object) [
                "success" => false,
                "status" => $response->status,
                "error" => $response->error,
            ];
        }

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
