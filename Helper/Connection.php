<?php

namespace Loyaltylion\Core\Helper;

class Connection
{
    private $_token;
    private $_secret;
    private $_base_uri;
    private $_timeout = 5;

    public function __construct($token, $secret, $base_uri)
    {
        $this->_token = $token;
        $this->_secret = $secret;
        $this->_base_uri = $base_uri;
    }

    public function post($path, $data = [])
    {
        return $this->_request('POST', $path, $data);
    }

    public function put($path, $data = [])
    {
        return $this->_request('PUT', $path, $data);
    }

    private function _request($method, $path, $data)
    {
        $options = [
            CURLOPT_URL => $this->_base_uri . $path,
            CURLOPT_USERAGENT => 'loyaltylion-php-client-v2.0.0',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->_timeout,
            CURLOPT_USERPWD => $this->_token . ':' . $this->_secret,
        ];

        switch ($method) {
            case 'POST':
                $options += [
                    CURLOPT_POST => true,
                ];
                break;
            case 'PUT':
                $options += [
                    CURLOPT_CUSTOMREQUEST => 'PUT',
                ];
        }

        if (!empty($data)) {
            $body = json_encode($data);

            $options += [
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($body),
                ],
            ];
        }

        // now make the request
        $curl = curl_init();
        curl_setopt_array($curl, $options);

        $body = curl_exec($curl);
        $headers = curl_getinfo($curl);
        $error_code = curl_errno($curl);
        $error_msg = curl_error($curl);

        if ($error_code !== 0) {
            $response = [
                'status' => $headers['http_code'],
                'error' => $error_msg,
            ];
        } else {
            $response = [
                'status' => $headers['http_code'],
                'headers' => $headers,
                'body' => $body,
            ];
        }

        return (object) $response;
    }
}
