#!/bin/bash

echo "=========================================================="
echo "    POS Restaurant Microservices System Launcher"
echo "=========================================================="

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
cd "$SCRIPT_DIR"

echo "[1/3] Running Database Migrations & Initial Seed..."
php scripts/seed.php

echo "[2/3] Running Automated Business Logic Tests..."
php tests/run_tests.php

if [ $? -ne 0 ]; then
    echo "[ERROR] Tests failed! Aborting startup."
    exit 1
fi

echo "[3/3] Starting Microservices & Frontend..."

# Kill any existing instances running on ports 8000, 8001, 8002, 8003
fuser -k 8000/tcp 8001/tcp 8002/tcp 8003/tcp 2>/dev/null

# Start POS Service (Port 8001)
php -S 127.0.0.1:8001 -t services/pos/public > logs/pos_server.log 2>&1 &
POS_PID=$!
echo " - POS Service started on http://127.0.0.1:8001 (PID: $POS_PID)"

# Start Recipe & Inventory Service (Port 8002)
php -S 127.0.0.1:8002 -t services/recipe-inventory/public > logs/inventory_server.log 2>&1 &
INV_PID=$!
echo " - Recipe & Inventory Service started on http://127.0.0.1:8002 (PID: $INV_PID)"

# Start Revenue Service (Port 8003)
php -S 127.0.0.1:8003 -t services/revenue/public > logs/revenue_server.log 2>&1 &
REV_PID=$!
echo " - Revenue Service started on http://127.0.0.1:8003 (PID: $REV_PID)"

# Start Frontend App (Port 8000)
php -S 127.0.0.1:8000 -t frontend > logs/frontend_server.log 2>&1 &
FRONT_PID=$!
echo " - Frontend UI started on http://127.0.0.1:8000 (PID: $FRONT_PID)"

echo "=========================================================="
echo " All Microservices Are Running Successfully!"
echo " Open your browser at: http://localhost:8000"
echo " Press Ctrl+C to stop all services."
echo "=========================================================="

trap "kill $POS_PID $INV_PID $REV_PID $FRONT_PID 2>/dev/null; exit" INT TERM EXIT
wait
