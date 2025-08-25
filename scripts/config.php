<?php
// Load environment variables from .env file
require_once __DIR__ . '/../vendor/autoload.php';

// Load .env file if it exists
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv->load();
}

// Get API keys from environment variables with fallbacks
$OPENAI_API_KEY = $_ENV['OPENAI_API_KEY'] ?? $_SERVER['OPENAI_API_KEY'] ?? null;
$GPT_ASSISTANT_ID = $_ENV['GPT_ASSISTANT_ID'] ?? $_SERVER['GPT_ASSISTANT_ID'] ?? null;

// Validate that required environment variables are set
if (!$OPENAI_API_KEY) {
    die('Error: OPENAI_API_KEY environment variable is not set. Please check your .env file.');
}

if (!$GPT_ASSISTANT_ID) {
    die('Error: GPT_ASSISTANT_ID environment variable is not set. Please check your .env file.');
}

// Database configuration from environment variables
$DB_HOST = $_ENV['DB_HOST'] ?? $_SERVER['DB_HOST'] ?? 'localhost';
$DB_USER = $_ENV['DB_USER'] ?? $_SERVER['DB_USER'] ?? 'root';
$DB_PASS = $_ENV['DB_PASS'] ?? $_SERVER['DB_PASS'] ?? 'mysql';
$DB_NAME = $_ENV['DB_NAME'] ?? $_SERVER['DB_NAME'] ?? 'heyteacher_db';
?>
