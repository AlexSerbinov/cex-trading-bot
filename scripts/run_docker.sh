#!/bin/bash

# Determine the current script directory
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
# Determine the project root directory
PROJECT_ROOT="$( cd "$SCRIPT_DIR/.." && pwd )"

# Create necessary directories if they don't exist
mkdir -p "$PROJECT_ROOT/data/logs" "$PROJECT_ROOT/data/pids"
chmod -R 777 "$PROJECT_ROOT/data"

# Go to the project root
cd "$PROJECT_ROOT"

# Check command line arguments
if [ $# -eq 0 ]; then
    echo "Usage: $0 {up|down|restart|logs}"
    exit 1
fi

case "$1" in
    up)
        echo "Starting Docker containers..."
        docker-compose up -d
        echo "Containers started. Access the services at:"
        echo "- API: http://localhost:5561"
        echo "- Frontend: http://localhost:5562"
        ;;
    down)
        echo "Stopping Docker containers..."
        docker-compose down
        ;;
    restart)
        echo "Restarting Docker containers..."
        docker-compose down
        docker-compose up -d
        ;;
    logs)
        echo "Showing logs..."
        docker-compose logs -f
        ;;
    *)
        echo "Unknown command: $1"
        echo "Usage: $0 {up|down|restart|logs}"
        exit 1
        ;;
esac 