<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Model\Api;

class Response
{
    /**
     * @param int $status 0 when LoyaltyLion could not be reached
     */
    public function __construct(
        public readonly int $status,
        public readonly string $body
    ) {
    }

    public function json(): array
    {
        $decoded = json_decode($this->body, true);

        return is_array($decoded) ? $decoded : [];
    }
}
