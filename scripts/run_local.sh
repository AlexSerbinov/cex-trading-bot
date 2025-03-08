#!/bin/bash

# run_local.sh

# Function for correct shutdown
cleanup() {
    echo "Stopping all processes..."
    # Determine the current script directory
    SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
    # Determine the project root directory
    PROJECT_ROOT="$( cd "$SCRIPT_DIR/.." && pwd )"
    
    # Stop all BotRunner.php processes
    pkill -f "BotRunner.php"
    # Stop all TradingBotManager.php processes
    pkill -f "TradingBotManager.php"
    # Stop the HTTP server
    pkill -f "php -S localhost:8080"
    # Clean up PID files
    rm -f "$PROJECT_ROOT/data/pids"/*.pid
    exit 0
}

# Catch the SIGINT (Ctrl+C) and other termination signals
trap cleanup SIGINT SIGTERM EXIT

# Function for starting the HTTP server
start_http_server() {
    echo "Starting HTTP server on port 8080..."
    # Determine the current script directory
    SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
    # Determine the project root directory
    PROJECT_ROOT="$( cd "$SCRIPT_DIR/.." && pwd )"
    
    # Start HTTP server from the project root directory with router.php
    cd "$PROJECT_ROOT" && php -S localhost:8080 router.php &
    HTTP_PID=$!
    echo "HTTP server started with PID: $HTTP_PID"
}

# Function for starting the bot manager
start_bot_manager() {
    echo "Starting bot manager..."
    # Determine the current script directory
    SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
    # Determine the project root directory
    PROJECT_ROOT="$( cd "$SCRIPT_DIR/.." && pwd )"
    
    cd "$PROJECT_ROOT" && php src/core/TradingBotManager.php &
    MANAGER_PID=$!
    echo "Bot manager started with PID: $MANAGER_PID"
}

# Check the command line arguments
if [ $# -eq 0 ]; then
    echo "Usage: $0 {http|bots|all|stop}"
    exit 1
fi

case "$1" in
    http)
        start_http_server
        echo "HTTP server started. Press Ctrl+C to stop."
        tail -f data/logs/bot.log
        ;;
    bots)
        start_bot_manager
        echo "Bot manager started. Press Ctrl+C to stop."
        tail -f data/logs/bot.log
        ;;
    all)
        echo "Starting all processes..."
        start_http_server
        start_bot_manager
        echo "All processes started. Press Ctrl+C to stop."
        tail -f data/logs/bot.log
        ;;
    stop)
        cleanup
        ;;
    *)
        echo "Unknown command: $1"
        echo "Usage: $0 {http|bots|all|stop}"
        exit 1
        ;;
esac

# Infinite loop to keep the script active
# and for correct trap operation
while true; do
    sleep 1
done