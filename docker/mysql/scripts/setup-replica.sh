#!/bin/bash
# MySQL Read Replica Setup Script
# This script configures a MySQL instance as a read replica

set -e

MASTER_HOST=${MYSQL_MASTER_HOST:-mysql}
MASTER_PORT=${MYSQL_MASTER_PORT:-3306}
REPL_USER=${MYSQL_REPLICATION_USER:-repl_user}
REPL_PASSWORD=${MYSQL_REPLICATION_PASSWORD:-repl_password}
ROOT_PASSWORD=${MYSQL_ROOT_PASSWORD:-root_password}

echo "Waiting for master database to be ready..."
until mysql -h"$MASTER_HOST" -P"$MASTER_PORT" -u"$REPL_USER" -p"$REPL_PASSWORD" -e "SELECT 1" >/dev/null 2>&1; do
    echo "Master database is unavailable - sleeping"
    sleep 5
done

echo "Master database is ready!"

# Get master status
MASTER_STATUS=$(mysql -h"$MASTER_HOST" -P"$MASTER_PORT" -u"$REPL_USER" -p"$REPL_PASSWORD" -e "SHOW MASTER STATUS\G")
MASTER_LOG_FILE=$(echo "$MASTER_STATUS" | grep "File:" | awk '{print $2}')
MASTER_LOG_POS=$(echo "$MASTER_STATUS" | grep "Position:" | awk '{print $2}')

echo "Master log file: $MASTER_LOG_FILE"
echo "Master log position: $MASTER_LOG_POS"

# Wait for local MySQL to be ready
until mysql -uroot -p"$ROOT_PASSWORD" -e "SELECT 1" >/dev/null 2>&1; do
    echo "Local MySQL is unavailable - sleeping"
    sleep 2
done

echo "Configuring replication..."

# Stop slave if already running
mysql -uroot -p"$ROOT_PASSWORD" -e "STOP SLAVE;" 2>/dev/null || true

# Configure replication
mysql -uroot -p"$ROOT_PASSWORD" <<-EOSQL
    CHANGE MASTER TO
        MASTER_HOST='$MASTER_HOST',
        MASTER_PORT=$MASTER_PORT,
        MASTER_USER='$REPL_USER',
        MASTER_PASSWORD='$REPL_PASSWORD',
        MASTER_LOG_FILE='$MASTER_LOG_FILE',
        MASTER_LOG_POS=$MASTER_LOG_POS,
        MASTER_CONNECT_RETRY=10;
EOSQL

# Start slave
mysql -uroot -p"$ROOT_PASSWORD" -e "START SLAVE;"

# Check slave status
echo "Checking replication status..."
mysql -uroot -p"$ROOT_PASSWORD" -e "SHOW SLAVE STATUS\G"

echo "Replication setup complete!"
