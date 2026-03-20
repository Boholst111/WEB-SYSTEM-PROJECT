-- MySQL Replication Setup Script for Diecast Empire
-- This script sets up the primary database for replication

-- Create replication user
CREATE USER IF NOT EXISTS 'repl_user'@'%' IDENTIFIED BY 'repl_password';
GRANT REPLICATION SLAVE ON *.* TO 'repl_user'@'%';

-- Grant necessary privileges
GRANT SELECT, RELOAD, SHOW DATABASES, REPLICATION SLAVE, REPLICATION CLIENT ON *.* TO 'repl_user'@'%';

-- Flush privileges
FLUSH PRIVILEGES;

-- Show master status (for manual replication setup)
-- SHOW MASTER STATUS;
