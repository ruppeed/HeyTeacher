# HeyTeacher Login System Setup

## Overview
This is a simple login/registration system for the HeyTeacher application that will appear when you visit localhost on your AMPPS server.

## Setup Instructions

### 1. Database Setup
First, you need to set up the database. Visit this URL in your browser:
```
http://localhost/scripts/setup_database.php
```

This will:
- Create the `heyteacher_db` database
- Create the `users` table
- Create a default admin user (username: `admin`, password: `admin123`)

### 2. Access the Login Page
After setting up the database, visit:
```
http://localhost
```

This will automatically redirect you to the login page at:
```
http://localhost/scripts/
```

### 3. Default Login Credentials
- **Username:** admin
- **Password:** admin123

**Important:** Change this password after your first login!

## Features
- User registration and login
- Session management
- Bootstrap-styled responsive interface
- Secure password handling with prepared statements
- Redirect to selection page after successful login

## File Structure
- `index.php` - Root redirect to login page
- `scripts/index.php` - Main login/registration page
- `scripts/setup_database.php` - Database setup script
- `scripts/config.php` - Configuration file
- `scripts/dashboard.php` - Dashboard after login
- `scripts/selection.php` - Selection page after login

## AMPPS Configuration
Make sure your AMPPS server is running and:
- Apache is started
- MySQL is started
- The web root points to this directory

## Troubleshooting
If you get database connection errors:
1. Ensure MySQL is running in AMPPS
2. Check that the MySQL credentials in `scripts/index.php` match your AMPPS setup
3. Default AMPPS MySQL credentials are usually:
   - Username: `root`
   - Password: `mysql`
   - Host: `localhost`














