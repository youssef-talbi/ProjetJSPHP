<?php
// Database configuration
$db_config = array(
    'host' => 'localhost',
    'username' => 'root',
    'password' => '',
    'dbname' => 'freelance_platform'
);


// Connection function
function getDbConnection(): false|PDO
{
    global $db_config;
    
    try {
        $conn = new PDO("mysql:host={$db_config['host']};dbname={$db_config['dbname']}", 
                        $db_config['username'], 
                        $db_config['password']);
        
        // Set the PDO error mode to exception
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        return $conn;
    } catch(PDOException $e) {
        // Log error
        error_log("Database Connection Error: " . $e->getMessage());
        
        // Return false on connection failure
        return false;
    }
}

// Function to check if database exists, if not create it
function setupDatabase() {
    global $db_config;
    
    try {
        // Connect without specifying database
        $conn = new PDO("mysql:host={$db_config['host']}", 
                        $db_config['username'], 
                        $db_config['password']);
        
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create database if it doesn't exist
        $conn->exec("CREATE DATABASE IF NOT EXISTS {$db_config['dbname']} 
                    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        
        return true;
    } catch(PDOException $e) {
        error_log("Database Setup Error: " . $e->getMessage());
        return false;
    }
}
