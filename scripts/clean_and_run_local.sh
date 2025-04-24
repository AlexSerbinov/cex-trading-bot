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

# Stop the HTTP server
echo "Stopping the HTTP server..."
pkill -f "php -S localhost:8080"

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

# Determine the project root directory
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_ROOT="$( cd "$SCRIPT_DIR/.." && pwd )"

# Set the environment variable
export ENVIRONMENT=local

# Create a directory for logs if it doesn't exist
mkdir -p "$PROJECT_ROOT/data/logs/local"

# Stop all existing processes
echo "Stopping all existing processes..."
"$SCRIPT_DIR/stop_all.sh"

# Clean up all PID files
echo "Deleting all PID files..."
rm -f "$PROJECT_ROOT/data/pids"/*.pid

# Clean up system cache
echo "Cleaning up system cache..."
php "$PROJECT_ROOT/tools/clean_system.php"

# Configure the frontend - use the script we created
"$SCRIPT_DIR/configure_frontend.sh" "local" "localhost" "8080"

# Start the backend
echo "Starting backend on port 8080..."
cd "$PROJECT_ROOT" && php -S localhost:8080 router.php 2>&1 | tee -a "$PROJECT_ROOT/data/logs/local/bots_error.log" &
BACKEND_PID=$!
echo "Backend started with PID: $BACKEND_PID"

# Start the frontend
echo "Starting frontend on port 8081..."
cd "$PROJECT_ROOT" && php -S localhost:8081 -t frontend 2>&1 | tee -a "$PROJECT_ROOT/data/logs/local/frontend_error.log" &
FRONTEND_PID=$!
echo "Frontend started with PID: $FRONTEND_PID"

# Start the bots
echo "Starting bots..."
# Check if TradingBotManager is already running
BOT_MANAGER_LOCK="$PROJECT_ROOT/data/pids/trading_bot_manager.lock"

if [ -f "$BOT_MANAGER_LOCK" ]; then
    LOCK_PID=$(cat "$BOT_MANAGER_LOCK")
    echo "Found TradingBotManager lock file with PID: $LOCK_PID"
    
    # Check if the process with this PID exists and if it is TradingBotManager
    if ps -p $LOCK_PID > /dev/null && grep -q TradingBotManager /proc/$LOCK_PID/cmdline 2>/dev/null; then
        echo "TradingBotManager is already running with PID: $LOCK_PID. Using existing process."
        BOTS_PID=$LOCK_PID
    else
        echo "Lock file exists, but the process is not running or it is not TradingBotManager. Removing the old lock file."
        rm -f "$BOT_MANAGER_LOCK"
        cd "$PROJECT_ROOT" && php src/core/TradingBotManager.php 2>&1 | tee -a "$PROJECT_ROOT/data/logs/local/bot.log" &
        BOTS_PID=$!
        echo "Bots started with new PID: $BOTS_PID"
    fi
else
    cd "$PROJECT_ROOT" && php src/core/TradingBotManager.php 2>&1 | tee -a "$PROJECT_ROOT/data/logs/local/bot.log" &
    BOTS_PID=$!
    echo "Bots started with PID: $BOTS_PID"
fi

# Save PIDs to files
echo $BACKEND_PID > "$PROJECT_ROOT/data/pids/backend.pid"
echo $FRONTEND_PID > "$PROJECT_ROOT/data/pids/frontend.pid"
echo $BOTS_PID > "$PROJECT_ROOT/data/pids/bots.pid"

echo ""
echo "System started!"
echo "Backend available at: http://localhost:8080"
echo "Swagger UI available at: http://localhost:8080/swagger-ui"
echo "Frontend available at: http://localhost:8081"
echo ""
echo "Showing logs..."
echo "For viewing errors, see files:"
echo "- $PROJECT_ROOT/data/logs/local/backend_error.log"
echo "- $PROJECT_ROOT/data/logs/local/frontend_error.log"
echo "- $PROJECT_ROOT/data/logs/local/bot.log"
echo ""
tail -f \
    "$PROJECT_ROOT/data/logs/local/bot.log" \
    "$PROJECT_ROOT/data/logs/local/backend_error.log" \
    "$PROJECT_ROOT/data/logs/local/frontend_error.log" 