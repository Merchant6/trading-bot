<?php

use Merchant\TradingBot\Core\Utils\HttpClientManager;
use React\Http\Browser;

/**
 * Generate a HMAC signature 
 * 
 * @param array|string $data
 * @param string $secret
 * @param string $algo
 * @return string
 */
function hmac(array|string $data, string $secret, string $algo = 'sha256'): string
{
    return hash_hmac($algo, $data, $secret);
}

/**
 * Get an instance of Browser class
 * 
 * @return React\Http\Browser
 */
function http(): Browser
{
    return HttpClientManager::getBrowser();
}