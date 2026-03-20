-- Create database with proper charset for international characters
CREATE DATABASE IF NOT EXISTS diecast_empire 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

-- Grant privileges to the application user
GRANT ALL PRIVILEGES ON diecast_empire.* TO 'diecast_user'@'%';
FLUSH PRIVILEGES;

-- Set MySQL configuration for better performance
SET GLOBAL innodb_buffer_pool_size = 268435456; -- 256MB
SET GLOBAL max_connections = 200;
SET GLOBAL query_cache_size = 67108864; -- 64MB