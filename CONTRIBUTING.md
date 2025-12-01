# Contributing to AnyLLM

Thank you for considering contributing to AnyLLM! This document provides guidelines and instructions.

## Code of Conduct

- Be respectful and inclusive
- Provide constructive feedback
- Focus on what is best for the community

## How to Contribute

### Reporting Bugs

1. Check if the bug has already been reported in Issues
2. Include PHP version, provider, and error messages
3. Provide a minimal code example to reproduce
4. Include relevant logs if available

### Suggesting Features

1. Check if the feature has been requested
2. Explain the use case and benefits
3. Provide example code of how it would work
4. Consider if it fits the library's scope

### Submitting Pull Requests

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Make your changes
4. Add tests for new features
5. Update documentation
6. Ensure all tests pass
7. Commit with clear messages
8. Push to your fork
9. Create a Pull Request

## Development Setup

```bash
# Clone your fork
git clone https://github.com/your-username/anyllm.git
cd anyllm

# Install dependencies
composer install

# Copy environment file
cp .env.example .env

# Add your API keys for testing
```

## Coding Standards

### PHP Standards
- Follow PSR-12 coding style
- Use strict types (`declare(strict_types=1);`)
- Type-hint everything (parameters and return types)
- Use PHP 8.2+ features where appropriate

### Documentation
- Add PHPDoc blocks for all public methods
- Include `@param` and `@return` tags
- Add usage examples in complex classes
- Update README if adding features

### Testing
- Write unit tests for new features
- Ensure examples work correctly
- Test with multiple providers when applicable
- Verify backward compatibility

## Project Structure

```
src/
├── Agents/           # Agent and workflow system
├── Config/           # Configuration management
├── Contracts/        # Interfaces
├── Conversations/    # Conversation management
├── Cost/             # Cost tracking
├── Enums/            # Enumerations
├── Exceptions/       # Exception classes
├── Http/             # HTTP client and retry logic
├── Logging/          # Logging system
├── Messages/         # Message system
├── Metrics/          # Metrics and monitoring
├── Middleware/       # Middleware system
├── Providers/        # Provider implementations
├── Responses/        # Response classes
├── Streaming/        # Streaming utilities
├── StructuredOutput/ # Structured output
├── Support/          # Utility classes
├── Testing/          # Testing support
└── Tools/            # Tool system
```

## Adding a New Provider

1. Create provider class extending `AbstractProvider`
2. Implement required methods:
   - `name()`
   - `getBaseUri()`
   - `getDefaultHeaders()`
   - `mapRequest()`
   - `mapResponse()`
   - `mapStreamChunk()`
3. Add to `Provider` enum
4. Add pricing to `PricingRegistry`
5. Add to `AnyLLM` factory
6. Create example file
7. Update README
8. Add tests

## Adding a New Feature

1. Create feature interfaces in `Contracts/`
2. Implement in relevant classes
3. Add configuration support if needed
4. Create comprehensive example
5. Add tests
6. Update documentation

## Testing

```bash
# Run all tests
./vendor/bin/phpunit

# Run specific test
./vendor/bin/phpunit tests/SimpleTest.php

# Run with coverage
./vendor/bin/phpunit --coverage-html coverage
```

## Documentation

Update documentation when:
- Adding new features
- Changing existing behavior
- Adding new providers
- Adding new examples

## Release Process

1. Update version in `composer.json`
2. Update `CHANGELOG.md`
3. Create git tag
4. Push tag to trigger CI/CD
5. Verify release on Packagist

## Questions?

- Open a discussion on GitHub
- Check existing documentation
- Look at examples in `examples/`

Thank you for contributing! 🎉

