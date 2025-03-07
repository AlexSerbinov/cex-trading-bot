#!/bin/bash

# run_clean.sh
# Stop all existing processes
echo "Stopping all existing processes..."
# Determine the current script directory
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
# Determine the project root directory
PROJECT_ROOT="$( cd "$SCRIPT_DIR/.." && pwd )"

# Use absolute paths
"$SCRIPT_DIR/stop_all.sh"

# Clean all PID files
echo "Removing all PID files..."
rm -f "$PROJECT_ROOT/data/pids"/*.pid

# Clean the system cache
echo "Cleaning the system cache..."
php "$PROJECT_ROOT/tools/clean_system.php"

# Start the system again
echo "Starting the system..."
"$SCRIPT_DIR/run_local.sh" all

# Show logs
echo "Showing logs..."
tail -f "$PROJECT_ROOT/data/logs/bot.log" 