# ReactPHP Trading Bot

A real-time, asynchronous cryptocurrency trading platform built with ReactPHP. It supports event-driven market polling, modular strategy signals, risk-managed order execution, paper trading, a REST API, and a React.js dashboard.

## Features

- **Asynchronous Execution**: Built using ReactPHP for real-time, non-blocking operations.
- **Customizable Strategies**: Strategy registry with Bollinger RSI, moving-average crossover, MACD, RSI mean reversion, breakout, and volume spike strategies.
- **Risk Controls**: Configurable risk per trade, max position size, max leverage, take profit, stop loss, trailing stop value, cooldown, and daily loss limit settings.
- **Paper Trading Mode**: Enabled by default so signals can be reviewed before live order placement.
- **React Dashboard**: Frontend controls for bot state, selected strategy, symbol, interval, risk settings, and event feed.
- **REST API**: ReactPHP API server for dashboard and operational integrations.
- **Order and Position Management**: Ensures no duplicate orders or positions by checking the current state of orders and positions for a trading pair.
- **Logger Integration**: All activities are logged for transparency and tracking.
- **Binance Futures API**: Supports automated trading through the Binance Futures API.
- **Testing**: Unit and feature tests to ensure the correctness and stability of the bot.

## Requirements

- PHP 8.2 or higher
- Composer (dependency manager)
- Node.js 20+ for the React dashboard
- Binance Futures API Key and Secret
- ReactPHP (installed via Composer)
- Basic knowledge of cryptocurrency trading and APIs

## Installation

### Step 1: Clone the Repository

Clone the project repository:

```bash
git clone https://github.com/YourUsername/reactphp-trading-bot.git
cd reactphp-trading-bot
```

### Step 2: Install Dependencies

Run the following command to install the required dependencies:

```bash
composer install
```

### Step 3: Set Up API Keys

Create a `.env` file in the root directory and add your Binance API keys:

```env
BINANCE_API_KEY=your_api_key
BINANCE_API_SECRET=your_api_secret
BINANCE_SECRET_KEY=your_api_secret
BINANCE_API_URL=https://fapi.binance.com
PRICE_FETCH_INTERVAL=5
COOL_DOWN_PERIOD=30
PAPER_TRADING=true
```

### Step 4: Run the Bot

Start the bot with the following command:

```bash
php index.php --symbol=BTCUSDT --leverage=5 --strategy=bollinger-rsi --paper=true
```

The bot will begin executing the configured strategy. Paper trading is enabled by default.

### Step 5: Run the API

```bash
composer api
```

The API runs at `http://127.0.0.1:8080` by default.

### Step 6: Run the React Dashboard

```bash
cd frontend
npm install
npm run dev
```

The dashboard runs at `http://127.0.0.1:5173`.

## Strategies

### Available Strategies

- `bollinger-rsi`
- `ma-crossover`
- `macd`
- `rsi-mean-reversion`
- `breakout`
- `volume-spike`

To add new strategies, create a class in `src/Core/Trades/Strategy`, implement `StrategyInterface`, and register it in `config/strategies.php`.

## API Endpoints

- `GET /api/status`
- `GET /api/strategies`
- `GET /api/config`
- `GET /api/events`
- `POST /api/bot/start`
- `POST /api/bot/stop`

The API stores runtime state in `storage/trading-state.json`.

## Order and Position Management

The bot checks both open orders and open positions to ensure that no duplicate trades are executed for a specific trading pair:

- **Open Orders**: Before placing a new order, it verifies if any orders are already open for the pair.
- **Open Positions**: If an open position exists for the pair, the bot will refrain from placing additional orders until the position is closed.

## Logging

All activities, such as order placements and errors, are logged in the `storage/logs/app.log` file. You can change the logging behavior in `src/Core/Logger.php`.

## Testing

This bot includes unit and feature tests located in the `tests` directory. To run the tests, use PHPUnit:

```bash
composer test
```

## Safety Notes

Live cryptocurrency trading can lose money quickly. Keep `PAPER_TRADING=true` until strategy behavior, sizing, and exchange credentials are verified. Use low leverage and testnet credentials before enabling live execution.

## Contributing

Feel free to fork the repository and submit pull requests. When contributing:

1. Ensure your code adheres to the existing coding standards.
2. Write tests for new features or bug fixes.
3. Provide clear commit messages and pull request descriptions.
