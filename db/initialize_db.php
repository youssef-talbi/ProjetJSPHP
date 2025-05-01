<?php
// Database initialization script
// This file creates the database and tables if they don't exist

// Include database configuration
require_once __DIR__ . '/../config/database.php';

// Function to create database and tables
function initialize_database() {
    global $db_config;
    
    try {
        // First, try to create the database if it doesn't exist
        $conn = new PDO("mysql:host={$db_config['host']}", 
                        $db_config['username'], 
                        $db_config['password']);
        
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create database if it doesn't exist
        $conn->exec("CREATE DATABASE IF NOT EXISTS {$db_config['dbname']} 
                    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        
        echo "Database created or already exists.\n";
        
        // Connect to the database
        $conn = new PDO("mysql:host={$db_config['host']};dbname={$db_config['dbname']}", 
                        $db_config['username'], 
                        $db_config['password']);
        
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Read the schema file
        $schema_file = __DIR__ . '/schema.sql';
        $sql = file_get_contents($schema_file);
        
        // Split the SQL file at each semicolon
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        
        // Execute each statement
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                $conn->exec($statement);
            }
        }
        
        echo "Database tables created successfully.\n";
        
        // Insert initial data if needed
        insert_initial_data($conn);
        
        return true;
    } catch(PDOException $e) {
        echo "Database initialization error: " . $e->getMessage() . "\n";
        return false;
    }
}

// Function to insert initial data
function insert_initial_data($conn) {
    try {
        // Check if categories table is empty
        $stmt = $conn->query("SELECT COUNT(*) FROM categories");
        $count = $stmt->fetchColumn();
        
        if ($count == 0) {
            // Insert main categories
            $categories = [
                ['Web Development', NULL, 'Website development services including frontend and backend'],
                ['Mobile Apps', NULL, 'Mobile application development for iOS and Android'],
                ['Design & Creative', NULL, 'Graphic design, logo design, and creative services'],
                ['Writing & Translation', NULL, 'Content writing, copywriting, and translation services'],
                ['Marketing', NULL, 'Digital marketing, SEO, and social media services'],
                ['Video & Animation', NULL, 'Video editing, animation, and motion graphics'],
                ['Admin Support', NULL, 'Virtual assistance and administrative support'],
                ['IT & Networking', NULL, 'IT services, networking, and system administration']
            ];
            
            $stmt = $conn->prepare("INSERT INTO categories (category_name, parent_category_id, description) VALUES (?, ?, ?)");
            
            foreach ($categories as $category) {
                $stmt->execute($category);
            }
            
            echo "Initial categories inserted.\n";
            
            // Insert skills
            $skills = [
                ['HTML/CSS', 1, 'Creating and styling web pages'],
                ['JavaScript', 1, 'Frontend and backend programming language'],
                ['PHP', 1, 'Server-side scripting language'],
                ['WordPress', 1, 'Content management system'],
                ['React', 1, 'JavaScript library for building user interfaces'],
                ['Node.js', 1, 'JavaScript runtime environment'],
                ['iOS Development', 2, 'Development for Apple iOS devices'],
                ['Android Development', 2, 'Development for Android devices'],
                ['React Native', 2, 'Cross-platform mobile app development'],
                ['Flutter', 2, 'UI toolkit for building natively compiled applications'],
                ['Logo Design', 3, 'Creating brand logos and identity'],
                ['Graphic Design', 3, 'Creating visual content for various media'],
                ['UI/UX Design', 3, 'Designing user interfaces and experiences'],
                ['Illustration', 3, 'Creating drawings and visual elements'],
                ['Content Writing', 4, 'Creating written content for websites and blogs'],
                ['Copywriting', 4, 'Writing persuasive marketing copy'],
                ['Translation', 4, 'Converting content from one language to another'],
                ['SEO', 5, 'Search engine optimization'],
                ['Social Media Marketing', 5, 'Marketing through social media platforms'],
                ['Email Marketing', 5, 'Marketing through email campaigns']
            ];
            
            $stmt = $conn->prepare("INSERT INTO skills (skill_name, category_id, description) VALUES (?, ?, ?)");
            
            foreach ($skills as $skill) {
                $stmt->execute($skill);
            }
            
            echo "Initial skills inserted.\n";
        } else {
            echo "Initial data already exists.\n";
        }
        
        return true;
    } catch(PDOException $e) {
        echo "Error inserting initial data: " . $e->getMessage() . "\n";
        return false;
    }
}

// Run the initialization
if (php_sapi_name() === 'cli') {
    // If running from command line
    echo "Starting database initialization...\n";
    initialize_database();
} else {
    // If accessed via web
    echo "<pre>";
    echo "Starting database initialization...\n";
    initialize_database();
    echo "</pre>";
}
