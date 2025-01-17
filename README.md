# ReactPHP Trading Bot

A real-time, asynchronous cryptocurrency trading bot built with ReactPHP. This bot integrates with Binance Futures to automate trading strategies like Bollinger Bands and RSI. The bot supports high-performance, non-blocking operations for optimal trading execution.

## Features

- **Asynchronous Execution**: Built using ReactPHP for real-time, non-blocking operations.
- **Customizable Strategies**: Implement and modify trading strategies easily, such as Bollinger Bands and RSI.
- **Order and Position Management**: Ensures no duplicate orders or positions by checking the current state of orders and positions for a trading pair.
- **Logger Integration**: All activities are logged for transparency and tracking.
- **Binance Futures API**: Supports automated trading through the Binance Futures API.
- **Testing**: Unit and feature tests to ensure the correctness and stability of the bot.

## Requirements

- PHP 8.2 or higher
- Composer (dependency manager)
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
```

### Step 4: Run the Bot

Start the bot with the following command:

```bash
php index.php
```

The bot will begin executing the configured strategy using the Binance Futures API.

## Strategies

### Bollinger Bands Strategy

This bot uses the Bollinger Bands strategy by default. It places buy orders when the price is near the lower band and sell orders near the upper band.

### Other Strategies

To add new strategies, create a new class in the `src/Core/Trades/Strategy` directory, implement the strategy logic, and integrate it into the bot's execution flow.

## Order and Position Management

The bot checks both open orders and open positions to ensure that no duplicate trades are executed for a specific trading pair:

- **Open Orders**: Before placing a new order, it verifies if any orders are already open for the pair.
- **Open Positions**: If an open position exists for the pair, the bot will refrain from placing additional orders until the position is closed.

## Logging

All activities, such as order placements and errors, are logged in the `storage/logs/app.log` file. You can change the logging behavior in `src/Core/Logger.php`.

## Testing

This bot includes unit and feature tests located in the `tests` directory. To run the tests, use PHPUnit:

```bash
php vendor/bin/phpunit
```

## Contributing

Feel free to fork the repository and submit pull requests. When contributing:

1. Ensure your code adheres to the existing coding standards.
2. Write tests for new features or bug fixes.
3. Provide clear commit messages and pull request descriptions.
