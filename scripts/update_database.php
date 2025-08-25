<?php
// Database update script to add subjects column
$host = 'localhost';
$user = 'root';
$pass = 'mysql';

$conn = new mysqli($host, $user, $pass, 'heyteacher_db');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Add subjects column to users table
$sql = "ALTER TABLE users ADD COLUMN subjects JSON";
if ($conn->query($sql) === TRUE) {
    echo "✅ Subjects column added successfully to users table<br>";
} else {
    if ($conn->errno == 1060) {
        echo "ℹ️ Subjects column already exists<br>";
    } else {
        echo "❌ Error adding subjects column: " . $conn->error . "<br>";
    }
}

// Update existing users to have access to all subjects by default
$sql = "UPDATE users SET subjects = '[\"Physics\", \"Chemistry\", \"Biology\"]' WHERE subjects IS NULL";
if ($conn->query($sql) === TRUE) {
    echo "✅ Updated existing users with default subject access<br>";
} else {
    echo "❌ Error updating existing users: " . $conn->error . "<br>";
}

$conn->close();
echo "<br><a href='index.php'>Go to Login Page</a>";
?>

