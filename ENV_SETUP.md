# Environment Variables Setup

This project now uses environment variables to securely store sensitive configuration like API keys.

## Setup Instructions

### 1. Create Environment File

Copy the example environment file and configure it with your actual values:

```bash
cp env.example .env
```

### 2. Configure Your .env File

Edit the `.env` file with your actual API keys and configuration:

```env
# OpenAI API Configuration
OPENAI_API_KEY=your_actual_openai_api_key_here
GPT_ASSISTANT_ID=your_actual_assistant_id_here

# Database Configuration
DB_HOST=localhost
DB_USER=root
DB_PASS=mysql
DB_NAME=heyteacher_db
```

### 3. Security Notes

- **Never commit your `.env` file to version control**
- The `.env` file is already added to `.gitignore`
- Keep your API keys secure and don't share them
- Consider using different API keys for development and production

### 4. Required Environment Variables

| Variable | Description | Required |
|----------|-------------|----------|
| `OPENAI_API_KEY` | Your OpenAI API key | Yes |
| `GPT_ASSISTANT_ID` | Your ChatGPT Assistant ID | Yes |
| `DB_HOST` | Database host | No (defaults to localhost) |
| `DB_USER` | Database username | No (defaults to root) |
| `DB_PASS` | Database password | No (defaults to mysql) |
| `DB_NAME` | Database name | No (defaults to heyteacher_db) |

### 5. Testing

After setting up your `.env` file, test that everything works by running:

```bash
php scripts/test_pdf_analyzer.php
```

If you see any errors about missing environment variables, double-check your `.env` file configuration.

## Troubleshooting

### Common Issues

1. **"OPENAI_API_KEY environment variable is not set"**
   - Make sure your `.env` file exists in the project root
   - Check that the variable name is exactly `OPENAI_API_KEY`
   - Ensure there are no spaces around the `=` sign

2. **"Class 'Dotenv\Dotenv' not found"**
   - Run `composer install` to install dependencies
   - Make sure the `vendor/` directory exists

3. **Permission issues**
   - Ensure your web server can read the `.env` file
   - Check file permissions on the `.env` file

### Support

If you continue to have issues, check:
- PHP error logs
- Web server error logs
- That all required Composer packages are installed
