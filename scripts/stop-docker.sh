#!/bin/bash

# Determine the project root directory
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_ROOT="$( cd "$SCRIPT_DIR/.." && pwd )"

echo "Stopping all Trading Bot Docker containers..."

# Stop all containers
cd "$PROJECT_ROOT" && docker-compose -f docker-compose-dev.yml down
cd "$PROJECT_ROOT" && docker-compose -f docker-compose-demo.yml down

echo "All Docker containers stopped!" 