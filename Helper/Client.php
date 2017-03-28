<?php

namespace Loyaltylion\Core\Helper;

class Client
{

    private $token;
    private $secret;
    private $connection;
    public $activities, $events;
    public $orders;
    private $base_uri = 'http://api.loyaltylion.dev/v2';
    private $config;

    public function __construct(\Loyaltylion\Core\Helper\Config $config)
    {
        $this->token = $config->getToken();
        $this->secret = $config->getSecret();
        $this->config = $config;

        if (!$this->config->isEnabled()) {
            throw new \Exception("Please provide a valid token and secret");
        }

        if (isset($extra['base_uri'])) $this->base_uri = $extra['base_uri'];

        $this->connection = new Connection($this->token, $this->secret, $this->base_uri);

        $this->activities = $this->events = new Activities($this->connection);
        $this->orders = new Orders($this->connection);
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
