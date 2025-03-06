#!/bin/bash

echo "Docker version:"
docker --version

echo "Docker Compose version:"
docker-compose --version

echo "Checking directory structure:"
ls -la

echo "Checking core directories:"
ls -la src/core || echo "src/core directory not found!"
ls -la src/api || echo "src/api directory not found!"
ls -la frontend || echo "frontend directory not found!"

echo "Checking data directory:"
ls -la data || mkdir -p data/logs data/pids && chmod -R 777 data

echo "Checking key files:"
ls -la src/core/TradingBotManager.php || echo "TradingBotManager.php not found!"
ls -la src/api/index.php || echo "API index.php not found!"
ls -la frontend/index.html || echo "Frontend index.html not found!"

echo "Cleaning Docker cache:"
docker-compose down
docker stop $(docker ps -a -q) 2>/dev/null || true
docker rm $(docker ps -a -q) 2>/dev/null || true
docker rmi -f $(docker images -q trading-bot*) 2>/dev/null || true
docker system prune -f

echo "Building with debug output:"
docker-compose build --no-cache

echo "Starting containers one by one:"
echo "Starting API container..."
docker-compose up -d api
sleep 5
echo "API container status:"
docker-compose ps api

echo "Starting frontend container..."
docker-compose up -d frontend
sleep 5
echo "Frontend container status:"
docker-compose ps frontend

echo "Starting trading-bot container..."
docker-compose up -d trading-bot
sleep 5
echo "All container status:"
docker-compose ps

echo "Container logs:"
docker-compose logs 