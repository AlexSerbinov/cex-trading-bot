#!/bin/bash

# Зупинити всі контейнери
docker-compose down
docker-compose -f docker-compose-dev.yml down
docker-compose -f docker-compose-demo.yml down

# Визначити, яке середовище запускати
if [ "$1" == "dev" ]; then
    echo "Starting development environment..."
    docker-compose -f docker-compose-dev.yml build
    docker-compose -f docker-compose-dev.yml up -d
    echo "Development environment started!"
    echo "Backend available at: http://localhost:8080"
    echo "Frontend available at: http://localhost:80"
elif [ "$1" == "demo" ]; then
    echo "Starting demonstration environment..."
    docker-compose -f docker-compose-demo.yml build
    docker-compose -f docker-compose-demo.yml up -d
    echo "Demonstration environment started!"
    echo "Backend available at: http://localhost:8090"
    echo "Frontend available at: http://localhost:8082"
elif [ "$1" == "both" ]; then
    echo "Starting both environments..."
    docker-compose -f docker-compose-dev.yml build
    docker-compose -f docker-compose-dev.yml up -d
    docker-compose -f docker-compose-demo.yml build
    docker-compose -f docker-compose-demo.yml up -d
    echo "Both environments started!"
    echo "Dev backend available at: http://localhost:8080"
    echo "Dev frontend available at: http://localhost:80"
    echo "Demo backend available at: http://localhost:8090"
    echo "Demo frontend available at: http://localhost:8082"
else
    echo "Usage: $0 {dev|demo|both}"
    exit 1
fi

# Показати запущені контейнери
docker ps 