#!/bin/bash

# Determine the project root directory
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_ROOT="$( cd "$SCRIPT_DIR/.." && pwd )"

# Stop all existing processes
echo "Stopping all existing processes..."
"$SCRIPT_DIR/stop_all.sh"

# Clean up all PID files
echo "Removing all PID files..."
rm -f "$PROJECT_ROOT/data/pids"/*.pid

# Clean the system cache
echo "Cleaning the system cache..."
php "$PROJECT_ROOT/tools/clean_system.php"

# Configure the frontend
"$SCRIPT_DIR/configure_frontend.sh" "local" "localhost" "8080"

# Start the backend
echo "Starting backend on port 8080..."
cd "$PROJECT_ROOT" && php -S localhost:8080 router.php > /dev/null 2>&1 &
BACKEND_PID=$!
echo "Backend started with PID: $BACKEND_PID"

# Start the frontend
echo "Starting frontend on port 8081..."
cd "$PROJECT_ROOT" && php -S localhost:8081 -t frontend > /dev/null 2>&1 &
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
        cd "$PROJECT_ROOT" && php src/core/TradingBotManager.php > /dev/null 2>&1 &
        BOTS_PID=$!
        echo "Bots started with new PID: $BOTS_PID"
    fi
else
    cd "$PROJECT_ROOT" && php src/core/TradingBotManager.php > /dev/null 2>&1 &
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
echo "To stop all processes use: ./scripts/stop_all.sh"
echo ""
echo "Showing logs..."
tail -f "$PROJECT_ROOT/data/logs/bot.log" 