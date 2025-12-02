#!/bin/bash

# Pre-push checks for AnyLLM
# This script runs code style checks and tests before allowing a push

set -e

echo "🔍 Running pre-push checks..."

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if composer is available
if ! command -v composer &> /dev/null; then
    echo -e "${RED}❌ Composer is not installed or not in PATH${NC}"
    exit 1
fi

# Check if vendor directory exists
if [ ! -d "vendor" ]; then
    echo -e "${YELLOW}⚠️  Vendor directory not found. Installing dependencies...${NC}"
    composer install --no-interaction --no-progress
fi

# 1. Check code style
echo -e "\n${YELLOW}📝 Checking code style...${NC}"
if composer cs-fix -- --dry-run --diff > /dev/null 2>&1; then
    echo -e "${GREEN}✅ Code style check passed${NC}"
else
    echo -e "${RED}❌ Code style check failed!${NC}"
    echo -e "${YELLOW}Run 'composer cs-fix' to fix the issues${NC}"
    composer cs-fix -- --dry-run --diff
    exit 1
fi

# 2. Run PHPStan (static analysis)
echo -e "\n${YELLOW}🔬 Running PHPStan...${NC}"
if vendor/bin/phpstan analyse --memory-limit=2G > /dev/null 2>&1; then
    echo -e "${GREEN}✅ PHPStan check passed${NC}"
else
    echo -e "${RED}❌ PHPStan check failed!${NC}"
    vendor/bin/phpstan analyse --memory-limit=2G
    exit 1
fi

# 3. Run tests
echo -e "\n${YELLOW}🧪 Running tests...${NC}"
if composer test > /dev/null 2>&1; then
    echo -e "${GREEN}✅ Tests passed${NC}"
else
    echo -e "${RED}❌ Tests failed!${NC}"
    composer test
    exit 1
fi

# 4. Validate example files syntax
echo -e "\n${YELLOW}📚 Validating example files...${NC}"
ERRORS=0
for file in examples/*.php; do
    if [ -f "$file" ]; then
        if php -l "$file" > /dev/null 2>&1; then
            echo -e "  ${GREEN}✓${NC} $(basename "$file")"
        else
            echo -e "  ${RED}✗${NC} $(basename "$file")"
            ERRORS=$((ERRORS + 1))
        fi
    fi
done

if [ $ERRORS -eq 0 ]; then
    echo -e "${GREEN}✅ All example files are valid${NC}"
else
    echo -e "${RED}❌ Found $ERRORS invalid example file(s)${NC}"
    exit 1
fi

echo -e "\n${GREEN}✨ All pre-push checks passed! Ready to push.${NC}"
exit 0

