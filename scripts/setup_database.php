<?php
// Database setup script for HeyTeacher login system
$host = 'localhost';
$user = 'root';
$pass = 'mysql';

// Create connection without database
$conn = new mysqli($host, $user, $pass);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if it doesn't exist
$sql = "CREATE DATABASE IF NOT EXISTS heyteacher_db";
if ($conn->query($sql) === TRUE) {
    echo "Database 'heyteacher_db' created successfully or already exists<br>";
} else {
    echo "Error creating database: " . $conn->error . "<br>";
}

// Select the database
$conn->select_db('heyteacher_db');

// Create users table
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "Table 'users' created successfully or already exists<br>";
} else {
    echo "Error creating table: " . $conn->error . "<br>";
}

// Create a default admin user if table is empty
$result = $conn->query("SELECT COUNT(*) as count FROM users");
$row = $result->fetch_assoc();

if ($row['count'] == 0) {
    $default_username = 'admin';
    $default_password = 'admin123';
    
    $stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
    $stmt->bind_param("ss", $default_username, $default_password);
    
    if ($stmt->execute()) {
        echo "Default admin user created:<br>";
        echo "Username: admin<br>";
        echo "Password: admin123<br>";
        echo "<strong>Please change this password after first login!</strong><br>";
    } else {
        echo "Error creating default user: " . $stmt->error . "<br>";
    }
    $stmt->close();
} else {
    echo "Users table already has data<br>";
}

$conn->close();
echo "<br><a href='index.php'>Go to Login Page</a>";
?>





