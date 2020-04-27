<?php

namespace Loyaltylion\Core\Helper;

class Client
{

    const API_HOST = 'api.loyaltylion.com';
    private $connection;
    private $base_uri;
    private $config;

    public function __construct(\Loyaltylion\Core\Helper\Config $config)
    {
        $this->config = $config;
        if(isset($_SERVER['LOYALTYLION_API_HOST'])) {
          $this->base_uri = 'https://' .  $_SERVER['LOYALTYLION_API_HOST'] . '/v2';
        } else {
          $this->base_uri = 'https://' .  self::API_HOST . '/v2';
        }
    }

    public function getClient($token, $secret)
    {
        $connection = new Connection($token, $secret, $this->base_uri);
        $events = new Activities($connection);
        $orders = new Orders($connection);
        return [$connection, $events, $orders];
    }

    protected function parseResponse($response)
    {
        if (isset($response->error)) {
            // this kind of error is from curl itself, e.g. a request timeout, so just return that error
            return (object)array(
                'success' => false,
                'status' => $response->status,
                'error' => $response->error,
            );
        }

        $result = array(
            'success' => (int)$response->status >= 200 && (int)$response->status <= 204
        );

        if (!$result['success']) {
            // even if curl succeeded, it can still fail if the request was invalid - we
            // usually have the error as the body so just stick that in
            $result['error'] = $response->body;
            $result['status'] = $response->status;
        }

        return (object)$result;
    }
}
