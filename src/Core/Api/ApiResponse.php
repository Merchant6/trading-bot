<?php

namespace Merchant\TradingBot\Core\Api;

use React\Http\Message\Response;

final class ApiResponse
{
    public static function json(array $payload, int $status = 200): Response
    {
        return new Response(
            $status,
            [
                'Content-Type' => 'application/json',
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type',
            ],
            json_encode($payload, JSON_PRETTY_PRINT)
        );
    }
}
