-- This script runs once when the database is first created

-- Create pg_stat_statements extension for query monitoring
CREATE
EXTENSION IF NOT EXISTS pg_stat_statements;

-- Create pgcrypto for cryptographic functions
CREATE
EXTENSION IF NOT EXISTS pgcrypto;

-- Create UUID extension
CREATE
EXTENSION IF NOT EXISTS "uuid-ossp";

-- Create btree_gin for advanced indexing
CREATE
EXTENSION IF NOT EXISTS btree_gin;

-- Create btree_gist for advanced indexing
CREATE
EXTENSION IF NOT EXISTS btree_gist;

-- Grant necessary permissions to the current user created by POSTGRES_USER
GRANT pg_read_all_stats TO CURRENT_USER;
GRANT pg_stat_scan_tables TO CURRENT_USER;

-- CREATE SCHEMA IF NOT EXISTS app AUTHORIZATION CURRENT_USER;

-- ALTER DATABASE CURRENT_DATABASE SET search_path TO app, public;

-- Log completion
DO
$$
BEGIN
    RAISE
NOTICE 'Database initialization completed successfully';
END $$;
