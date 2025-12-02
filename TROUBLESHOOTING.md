# Troubleshooting Guide

Common issues and their solutions when working with AnyLLM.

## Empty Response from Structured Output

### Symptoms
```
RuntimeException: Received empty response from provider
```
or
```
InvalidRequestException: OpenAI returned empty content
```

### Causes & Solutions

#### 1. Missing or Invalid API Key ⭐ Most Common

**Check**:
```bash
# Check if API key is set
echo $OPENAI_API_KEY

# Or check .env file
cat .env | grep OPENAI_API_KEY
```

**Solution**:
```bash
# Get your API key from: https://platform.openai.com/api-keys
# Then set it:
echo "OPENAI_API_KEY=sk-your-actual-key-here" > .env

# Or export it:
export OPENAI_API_KEY=sk-your-actual-key-here
```

**Verify**:
```bash
php test-setup.php
```

#### 2. Using Placeholder API Key

**Problem**: You're using the example placeholder key

**Check**:
```bash
cat .env
# If you see: OPENAI_API_KEY=sk-your-openai-api-key-here
# This is the placeholder, not a real key!
```

**Solution**: Replace with your actual API key from https://platform.openai.com/api-keys

#### 3. Model Doesn't Support Structured Outputs

**Problem**: Not all models support structured outputs

**Supported Models**:
- ✅ `gpt-4o` (recommended)
- ✅ `gpt-4o-mini` (recommended)
- ✅ `gpt-4o-2024-08-06` and later
- ❌ `gpt-3.5-turbo` (older versions)
- ❌ `gpt-4` (older versions)

**Solution**: Use `gpt-4o-mini` or `gpt-4o`

#### 4. Certificate/Network Issues

**Symptoms**:
```
cURL error 77: error setting certificate file
```

**Solution** (Development only):
```bash
# Option 1: Download certificates
curl https://curl.se/ca/cacert.pem -o cacert.pem
export CURL_CA_BUNDLE=$(pwd)/cacert.pem

# Option 2: Use system certificates
export CURL_CA_BUNDLE=/etc/ssl/certs/ca-certificates.crt

# Option 3: Disable SSL verification (NOT for production!)
# Add to your code temporarily:
// $httpClient->setOption(CURLOPT_SSL_VERIFYPEER, false);
```

#### 5. Rate Limit Exceeded

**Symptoms**:
```
RateLimitException: Rate limit exceeded
```

**Solution**:
- Wait a few minutes
- Check your OpenAI usage limits
- Upgrade your OpenAI plan if needed

## Authentication Errors

### Symptoms
```
AuthenticationException: Incorrect API key provided
```

### Solutions

1. **Verify API key format**: Should start with `sk-`
2. **Check for extra spaces**: `sk-abc123` not ` sk-abc123 `
3. **Regenerate key**: Create a new one at https://platform.openai.com/api-keys
4. **Check organization**: If using organization, verify `OPENAI_ORGANIZATION` is set

## Class Not Found Errors

### Symptoms
```
Fatal error: Class 'AnyLLM\AnyLLM' not found
```

### Solution
```bash
composer dump-autoload
```

## Tests Failing

### Symptoms
```
Tests: 3, Errors: 2
```

### Solutions

1. **Check PHP version**:
   ```bash
   php -v  # Should be 8.2+
   ```

2. **Reinstall dependencies**:
   ```bash
   rm -rf vendor
   composer install
   ```

3. **Check for syntax errors**:
   ```bash
   composer phpstan
   ```

## Examples Not Working

### Symptoms
Examples crash or return errors

### Debug Steps

1. **Run setup verification**:
   ```bash
   php test-setup.php
   ```

2. **Check API key is set**:
   ```bash
   echo $OPENAI_API_KEY
   ```

3. **Test with simple example**:
   ```php
   <?php
   require 'bootstrap.php';
   use AnyLLM\AnyLLM;
   
   $llm = AnyLLM::openai(apiKey: $_ENV['OPENAI_API_KEY']);
   $response = $llm->generateText('gpt-4o-mini', 'Say hello');
   echo $response->text;
   ```

4. **Enable error reporting**:
   ```php
   error_reporting(E_ALL);
   ini_set('display_errors', '1');
   ```

## Schema Validation Errors

### Symptoms
```
InvalidRequestException: Invalid schema for response_format
```

### Common Issues

1. **Missing `items` in array**:
   ```php
   // ❌ Wrong
   public array $steps;
   
   // ✅ Correct
   /** @var array<string> */
   public array $steps;
   ```

2. **Missing property in `required`**:
   - All properties must be in `required` array
   - Even nullable properties!

3. **Wrong type format for nullable**:
   ```json
   // ❌ Wrong
   {"type": "string", "nullable": true}
   
   // ✅ Correct
   {"type": ["string", "null"]}
   ```

### Debug Schema

```php
$schema = Schema::fromClass(YourClass::class);
echo json_encode($schema->toJsonSchema(), JSON_PRETTY_PRINT);
```

Check:
- ✅ All arrays have `items`
- ✅ All properties in `required`
- ✅ Nullable properties use `["type", "null"]`
- ✅ `additionalProperties: false` is set

## Performance Issues

### Slow API Responses

**Solutions**:
1. Use caching:
   ```php
   $cache = CacheFactory::create('redis', $config);
   $cache->remember($key, fn() => $llm->chat(...), 3600);
   ```

2. Use faster models:
   - `gpt-4o-mini` (fastest)
   - `gpt-4o` (balanced)

3. Reduce token count:
   - Shorter prompts
   - Lower `max_tokens`
   - Enable streaming for better UX

## Database/Redis Issues

### Connection Errors

**Check configuration**:
```php
// Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=anyllm
DB_USERNAME=root
DB_PASSWORD=

// Redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

**Test connection**:
```bash
# MySQL
mysql -h 127.0.0.1 -u root -p anyllm

# Redis
redis-cli ping
```

## Still Having Issues?

### Debugging Checklist

- [ ] Run `php test-setup.php`
- [ ] Check `composer test` passes
- [ ] Verify API key is set correctly
- [ ] Check PHP version (8.2+)
- [ ] Review error messages carefully
- [ ] Check OpenAI API status: https://status.openai.com
- [ ] Try with a simple example first
- [ ] Enable error reporting

### Get Help

1. **Check documentation**:
   - `README.md` - Complete guide with quick start and examples

2. **Review examples**:
   - Start with `examples/basic-usage.php`
   - Check `tests/SimpleTest.php` for working code

3. **Common Commands**:
   ```bash
   php test-setup.php          # Verify setup
   composer test               # Run tests
   composer phpstan            # Static analysis
   php examples/basic-usage.php # Test examples
   ```

### Quick Fixes

```bash
# Reset everything
rm -rf vendor composer.lock
composer install
composer dump-autoload

# Verify setup
php test-setup.php

# Run tests
composer test
```

## Environment-Specific Issues

### Laravel Herd

**Issue**: Certificate errors

**Solution**:
```bash
export CURL_CA_BUNDLE=/path/to/herd/cacert.pem
```

### Docker

**Issue**: Network connectivity

**Solution**: Ensure container has network access

### Windows

**Issue**: Path separators

**Solution**: Use forward slashes `/` in paths

---

**Still stuck?** Check the examples in `examples/` directory - they show working implementations of all features.

