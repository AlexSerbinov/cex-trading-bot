#!/bin/bash

# Determine the project root directory
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_ROOT="$( cd "$SCRIPT_DIR/.." && pwd )"

# Create necessary data directories if they don't exist
mkdir -p "$PROJECT_ROOT/data/logs"
mkdir -p "$PROJECT_ROOT/data/pids"
mkdir -p "$PROJECT_ROOT/data/storage"

# Set write permissions for data directories
chmod -R 777 "$PROJECT_ROOT/data"

# Prepare configuration files
echo "Preparing configuration files..."
"$SCRIPT_DIR/prepare-configs.sh"

echo "Stopping all containers..."
cd "$PROJECT_ROOT" && docker-compose -f docker-compose-dev.yml down
cd "$PROJECT_ROOT" && docker-compose -f docker-compose-demo.yml down

echo "Rebuilding Trading Bot Dev environment..."
cd "$PROJECT_ROOT" && docker-compose -f docker-compose-dev.yml build

# echo "Rebuilding Trading Bot Demo environment..."
cd "$PROJECT_ROOT" && docker-compose -f docker-compose-demo.yml build 

echo "Starting Trading Bot Dev environment..."
cd "$PROJECT_ROOT" && docker-compose -f docker-compose-dev.yml up -d

echo "Starting Trading Bot Demo environment..."
cd "$PROJECT_ROOT" && docker-compose -f docker-compose-demo.yml up -d

echo "Both environments rebuilt and started!"
echo ""
echo "Dev Environment:"
echo "Backend available at: http://localhost:5501"
echo "Swagger UI available at: http://localhost:5501/swagger-ui"
echo "Frontend available at: http://localhost:5502"
echo ""
echo "Demo Environment:"
echo "Backend available at: http://localhost:6501"
echo "Swagger UI available at: http://localhost:6501/swagger-ui"
echo "Frontend available at: http://localhost:6502"
echo ""
echo "To stop all containers use: ./scripts/stop-all.sh" 