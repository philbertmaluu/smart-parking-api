#!/bin/bash

# Camera Detection Scheduler Runner
# This script runs the Laravel scheduler which fetches camera data every 2 seconds

echo "Starting Laravel Scheduler for Camera Detection..."
echo "This will run the fetch:camera-data command every 2 seconds"
echo "Press Ctrl+C to stop"
echo ""

cd "$(dirname "$0")"

# Run in background and continuously
while true; do
    php artisan schedule:run >> /dev/null 2>&1
    sleep 1
done
