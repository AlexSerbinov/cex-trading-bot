#!/bin/bash

# Determine the project root directory
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_ROOT="$( cd "$SCRIPT_DIR/.." && pwd )"

# Check if necessary config files exist
if [ ! -f "$PROJECT_ROOT/config/bots_config.json" ]; then
    echo "Creating empty bots_config.json..."
    echo '{"enabledPairs": ["BTC_USDT", "ETH_USDT"]}' > "$PROJECT_ROOT/config/bots_config.json"
fi

# Check if main config file exists
if [ ! -f "$PROJECT_ROOT/config/config.php" ]; then
    echo "Config file config.php is missing!"
    echo "Create config.php with the required parameters"
    exit 1
fi

echo "Config files prepared!" 