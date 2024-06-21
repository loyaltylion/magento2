<?php

namespace Loyaltylion\Core\Helper;

class Orders extends Client
{
    private $_connection;

    public function __construct(Connection $connection)
    {
        $this->_connection = $connection;
    }

    /**
     * Create an order in LoyaltyLion
     *
     * @param  [type] $data [description]
     * @return [type]       [description]
     */
    public function create($data)
    {
        $response = $this->_connection->post("/orders", $data);

        return $this->parseResponse($response);
    }

    /**
     * Update an order by its merchant_id in LoyaltyLion
     *
     * This is an idempotent update which is safe to call everytime an order
     * is updated
     *
     * @param  [type] $id   [description]
     * @param  [type] $data [description]
     * @return [type]       [description]
     */
    public function update($id, $data)
    {
        $response = $this->_connection->put("/orders/" . $id, $data);

        return $this->parseResponse($response);
    }

    public function setCancelled($id)
    {
        $response = $this->_connection->put("/orders/" . $id . "/cancelled");

        return $this->parseResponse($response);
    }

    public function setPaid($id)
    {
        $response = $this->_connection->put("/orders/" . $id . "/paid");

        return $this->parseResponse($response);
    }

    public function setRefunded($id)
    {
        $response = $this->_connection->put("/orders/" . $id . "/refunded");

        return $this->parseResponse($response);
    }

    public function addPayment($id, $data)
    {
        $response = $this->_connection->post(
            "/orders/" . $id . "/payments",
            $data
        );

        return $this->parseResponse($response);
    }

    public function addRefund($id, $data)
    {
        $response = $this->_connection->post(
            "/orders/" . $id . "/refunds",
            $data
        );

        return $this->parseResponse($response);
    }
}
