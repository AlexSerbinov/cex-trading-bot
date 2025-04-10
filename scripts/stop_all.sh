#!/bin/bash

echo "Stopping all processes..."

# Display the list of processes before stopping for information
echo "Active processes before stopping:"
ps aux | grep -E "BotRunner|TradingBotManager|php -S" | grep -v grep

# Stop all BotRunner.php processes
echo "Stopping BotRunner.php processes..."
pkill -f "BotRunner.php"

# Stop all TradingBotManager.php processes
echo "Stopping TradingBotManager.php processes..."
pkill -f "TradingBotManager.php"

# Stop the backend HTTP server
echo "Stopping the backend HTTP server..."
pkill -f "php -S localhost:8080"

# Stop the frontend HTTP server
echo "Stopping the frontend HTTP server..."
pkill -f "php -S localhost:8081"

# Clean up PID files
echo "Cleaning up PID files..."
# Determine the current script directory
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
# Determine the project root directory
PROJECT_ROOT="$( cd "$SCRIPT_DIR/.." && pwd )"

rm -f "$PROJECT_ROOT/data/pids"/*.pid

# Check if any processes remain
echo "Remaining processes after stopping:"
REMAINING=$(ps aux | grep -E "BotRunner|TradingBotManager|php -S" | grep -v grep)
if [ -n "$REMAINING" ]; then
    echo "$REMAINING"
    echo "Forcing the termination of processes..."
    ps aux | grep -E "BotRunner|TradingBotManager|php -S" | grep -v grep | awk '{print $2}' | xargs -r kill -9
else
    echo "All processes stopped successfully."
fi

echo "Completed." 