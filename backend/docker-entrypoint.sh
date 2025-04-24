#!/bin/bash
set -e

# Create necessary directories
mkdir -p /app/data/logs
mkdir -p /app/data/pids
mkdir -p /app/data/storage

# Set write permissions
chmod -R 777 /app/data

# Output environment information
echo "Backend started in environment: ${ENVIRONMENT}"
echo "Trade Server URL: ${TRADE_SERVER_URL}"

# Start bot manager in the background if it's not API mode
if [ "$1" == "bot-manager" ] || [ "$1" == "both" ]; then
    echo "Starting bot manager..."
    php /app/src/core/TradingBotManager.php &
    echo "Bot manager started with PID: $!"
fi

# If both server and bot manager need to be started
if [ "$1" == "both" ]; then
    # Start PHP server
    echo "Starting API server..."
    exec php -S 0.0.0.0:8080 router.php
elif [ "$1" == "bot-manager" ]; then
    # Create a method for monitoring logs
    echo "Starting log monitoring..."
    exec tail -f /app/data/logs/bot.log
else
    # Default - execute the command passed in CMD
    echo "Starting with command: $@"
    exec "$@"
fi 