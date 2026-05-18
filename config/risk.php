<?php

return [
    'risk_percentage' => (float)($_ENV['RISK_PERCENTAGE'] ?? 2.0),
    'max_position_percentage' => (float)($_ENV['MAX_POSITION_PERCENTAGE'] ?? 10.0),
    'max_leverage' => (int)($_ENV['MAX_LEVERAGE'] ?? 20),
    'take_profit_percentage' => (float)($_ENV['TAKE_PROFIT_PERCENTAGE'] ?? 15.0),
    'take_profit_ceiling_percentage' => (float)($_ENV['TAKE_PROFIT_CEILING_PERCENTAGE'] ?? 20.0),
    'stop_loss_percentage' => (float)($_ENV['STOP_LOSS_PERCENTAGE'] ?? 15.0),
    'trailing_stop_percentage' => (float)($_ENV['TRAILING_STOP_PERCENTAGE'] ?? 5.0),
    'daily_loss_limit_percentage' => (float)($_ENV['DAILY_LOSS_LIMIT_PERCENTAGE'] ?? 6.0),
    'cool_down_period' => (int)($_ENV['COOL_DOWN_PERIOD'] ?? 30),
];
