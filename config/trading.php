<?php

return [
    'api_host' => $_ENV['API_HOST'] ?? '127.0.0.1',
    'api_port' => (int)($_ENV['API_PORT'] ?? 8080),
    'paper_trading' => filter_var($_ENV['PAPER_TRADING'] ?? true, FILTER_VALIDATE_BOOLEAN),
    'default_symbol' => $_ENV['DEFAULT_SYMBOL'] ?? 'BTCUSDT',
    'default_contract_type' => $_ENV['DEFAULT_CONTRACT_TYPE'] ?? 'PERPETUAL',
    'default_interval' => $_ENV['DEFAULT_INTERVAL'] ?? '5m',
    'default_limit' => (int)($_ENV['DEFAULT_LIMIT'] ?? 100),
    'state_file' => $_ENV['TRADING_STATE_FILE'] ?? __DIR__ . '/../storage/trading-state.json',
];
