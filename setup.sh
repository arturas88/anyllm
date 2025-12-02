#!/bin/bash

# AnyLLM Local Development Setup Script

set -e

echo "🚀 Setting up AnyLLM for local development..."
echo ""

# Check PHP version
echo "📋 Checking PHP version..."
PHP_VERSION=$(php -r "echo PHP_VERSION_ID;")
if [ "$PHP_VERSION" -lt 80200 ]; then
    echo "❌ PHP 8.2+ required. Current version: $(php -v | head -n 1)"
    exit 1
fi
echo "✅ PHP version OK: $(php -v | head -n 1)"
echo ""

# Install dependencies
echo "📦 Installing Composer dependencies..."
if [ ! -d "vendor" ]; then
    composer install
else
    echo "✅ Dependencies already installed. Updating..."
    composer update
fi
echo ""

# Check for .env file
echo "🔑 Checking environment setup..."
if [ ! -f ".env" ]; then
    if [ -f ".env.example" ]; then
        echo "⚠️  No .env file found. Copying from .env.example..."
        cp .env.example .env
        echo "✅ Created .env file from template"
    else
        echo "⚠️  No .env file found. Creating template..."
        cat > .env << 'EOF'
# OpenAI (required for basic testing)
OPENAI_API_KEY=your-openai-api-key-here

# Optional providers
# ANTHROPIC_API_KEY=your-anthropic-api-key-here
# GOOGLE_AI_API_KEY=your-google-api-key-here
# MISTRAL_API_KEY=your-mistral-api-key-here
# XAI_API_KEY=your-xai-api-key-here
# OPENROUTER_API_KEY=your-openrouter-api-key-here

# Optional: Database for persistence features
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_DATABASE=anyllm
# DB_USERNAME=root
# DB_PASSWORD=

# Optional: Redis for caching
# REDIS_HOST=127.0.0.1
# REDIS_PORT=6379
EOF
    echo "✅ Created .env file. Please add your API keys!"
else
    echo "✅ .env file exists"
fi
echo ""

# Regenerate autoloader
echo "🔄 Regenerating autoloader..."
composer dump-autoload
echo ""

# Run tests
echo "🧪 Running tests..."
if ./vendor/bin/phpunit --testdox; then
    echo "✅ All tests passed!"
else
    echo "⚠️  Some tests failed. Check output above."
fi
echo ""

# Check code style
echo "🎨 Checking code style..."
if composer cs-fix -- --dry-run --diff 2>/dev/null; then
    echo "✅ Code style OK"
else
    echo "⚠️  Code style issues found. Run 'composer cs-fix' to fix."
fi
echo ""

echo "✨ Setup complete!"
echo ""
echo "Next steps:"
echo "1. Add your API keys to .env file"
echo "2. Run: php examples/basic-usage.php"
echo "3. Read: README.md for detailed guide"
echo ""

