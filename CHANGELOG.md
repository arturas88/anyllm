# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-12-01

### 🎉 Initial Release - Production-Ready!

#### Added - Core Features
- **7 LLM Provider Integrations**
  - OpenAI (GPT-4o, DALL-E, Whisper)
  - Anthropic (Claude Opus/Sonnet/Haiku 4.5)
  - Google AI (Gemini 2.5 Flash, Gemini 3 Pro)
  - Mistral AI (Large/Medium/Small + Pixtral OCR)
  - xAI (Grok Beta)
  - OpenRouter (100+ models)
  - Ollama (Local models)
  
- **Unified Interface**
  - Same API for all providers
  - Easy provider switching
  - Provider-specific optimizations
  
- **Message System**
  - Role-based messages (System, User, Assistant, Tool)
  - Multi-modal content (Text, Images, Files)
  - Provider format mapping
  
- **Streaming Support**
  - Real-time response streaming
  - StreamController with pause/resume/cancel
  - SSE formatting for web apps
  - Token counting during streaming
  - Buffer management
  
- **Structured Output**
  - JSON Schema generation from PHP classes
  - PHP 8 attributes for schema definitions
  - Automatic response hydration
  - Type-safe structured data
  
- **Tool/Function Calling**
  - Automatic parameter extraction
  - JSON Schema generation
  - Tool execution handling
  - Multiple tools support
  
- **Agents & Workflows**
  - Autonomous agent execution
  - Multi-iteration problem solving
  - Multi-step workflows
  - Variable interpolation
  - Execution history

#### Added - Production Infrastructure
- **Database Persistence**
  - DatabaseConversationRepository (MySQL, PostgreSQL, SQLite)
  - RedisConversationRepository
  - FileConversationRepository
  - Full CRUD operations
  - Search and pagination
  - Metadata support
  
- **Logging System**
  - DatabaseLogDriver with analytics
  - FileLogDriver with rotation
  - NullLogDriver for testing
  - Query and filter logs
  - Usage statistics
  - Cost analytics
  
- **Rate Limiting**
  - MemoryRateLimiter (in-memory)
  - RedisRateLimiter (distributed)
  - DatabaseRateLimiter (persistent)
  - Per-user, per-key limiting
  - Configurable time windows
  
- **Caching**
  - RedisCache (distributed)
  - MemcachedCache (fast)
  - DatabaseCache (queryable)
  - FileCache (simple)
  - ArrayCache (testing)
  - Remember pattern
  - TTL support
  
- **Retry Logic**
  - Exponential backoff
  - Configurable retries
  - Smart failure detection
  - Automatic on rate limits and 5xx errors

#### Added - Advanced Features
- **Embeddings Support**
  - Generate embeddings via providers
  - Vector similarity calculations
  - Semantic search utilities
  - RAG-ready operations
  - VectorMath utility class
  
- **Middleware System**
  - Request/response interception
  - MiddlewarePipeline for chaining
  - Built-in middleware:
    - LoggingMiddleware
    - CachingMiddleware
    - RateLimitMiddleware
    - MetricsMiddleware
  - Custom middleware support
  
- **Metrics & Monitoring**
  - MetricsCollector (counters, gauges, histograms)
  - MetricsDashboard (text & HTML)
  - Prometheus export
  - JSON export
  - Real-time tracking
  - Performance analytics

#### Added - Developer Tools
- **Token Counter**
  - Estimate tokens before API calls
  - Check against limits
  - Truncate text to fit
  - Format for display
  
- **Configuration Validator**
  - Validate provider configs
  - Custom validation rules
  - Helpful error messages
  
- **Prompt Templates**
  - Variable interpolation
  - Pre-built templates
  - Template validation
  - File loading support
  
- **Testing Support**
  - FakeProvider for mocking
  - Assertion methods
  - Response faking

#### Added - Conversation Management
- **Persistent Conversations**
  - Store across restarts
  - Multiple storage backends
  - Auto-summarization
  - Token optimization
  - Cost tracking per conversation
  - Search and pagination

#### Added - Documentation
- Comprehensive README
- Getting Started guide
- 15 working examples
- Inline code documentation
- API reference via PHPDoc

#### Added - Examples
- basic-usage.php
- all-providers-demo.php
- embeddings-example.php
- middleware-example.php
- streaming-advanced.php
- metrics-example.php
- conversation-persistence.php
- logging-example.php
- rate-limiting-example.php
- cache-drivers-example.php
- quick-wins-demo.php
- openrouter-advanced.php
- mistral-ocr.php
- conversation-management.php

### Changed
- N/A (initial release)

### Deprecated
- N/A (initial release)

### Removed
- N/A (initial release)

### Fixed
- N/A (initial release)

### Security
- Secure API key handling
- Rate limiting for abuse prevention
- Input validation throughout
- No sensitive data in exceptions

---

## [1.0.1] - 2025-12-01

### Added
- **TROUBLESHOOTING.md** - Comprehensive troubleshooting guide for common issues
- **setup.sh** - Automated setup script for quick project initialization
- **test-setup.php** - Test setup helper script
- **bootstrap.php** - Bootstrap file for better autoloading
- **.php-cs-fixer.dist.php** - PHP CS Fixer configuration
- **Enhanced README** - Improved installation instructions with step-by-step guide
- **Database Schema Documentation** - Added SQLite support notes and compatibility information
- **vlucas/phpdotenv** - Added to dev dependencies for environment variable management

### Changed
- **README.md** - Enhanced with:
  - Prerequisites section
  - Step-by-step installation guide
  - Better quick start examples
  - Improved code examples with comments
  - More detailed usage instructions
- **DATABASE_SCHEMA.md** - Added:
  - SQLite compatibility notes
  - Database driver comparison
  - SQLite-specific syntax adjustments
- **FakeProvider** - Improved:
  - Better token calculation logic
  - Automatic TextResponse to ChatResponse conversion
  - More accurate fake response handling
- **All Examples** - Updated with:
  - Better error handling
  - Improved code comments
  - More consistent patterns
  - Enhanced documentation
- **Conversation Management** - Enhanced:
  - Improved conversation repository implementations
  - Better error handling
  - More robust file-based storage
- **Structured Output** - Improved:
  - Better schema generation
  - Enhanced type handling
  - More robust validation
- **Provider Implementations** - Refined:
  - Better error messages
  - Improved request handling
  - Enhanced response parsing
- **Middleware System** - Enhanced:
  - Better context handling
  - Improved error propagation
- **Metrics Dashboard** - Improved:
  - Better HTML rendering
  - Enhanced data visualization
- **Cache Implementations** - Refined:
  - Better error handling
  - Improved FileCache implementation
  - Enhanced Redis/Memcached support
- **Rate Limiting** - Improved:
  - Better distributed rate limiting
  - Enhanced error handling
- **Token Counter** - Enhanced:
  - More accurate token estimation
  - Better truncation logic
- **Prompt Templates** - Improved:
  - Better variable interpolation
  - Enhanced validation
- **Code Quality** - Across the codebase:
  - Improved type hints
  - Better error handling
  - Enhanced documentation
  - More consistent code style

### Fixed
- Fixed token calculation in FakeProvider to use proper integer casting
- Fixed TextResponse to ChatResponse conversion in FakeProvider
- Improved error handling across all providers
- Fixed various edge cases in conversation management
- Improved database migration compatibility
- Fixed cache key handling in various cache drivers
- Enhanced retry logic for better reliability

### Removed
- **GETTING_STARTED.md** - Content merged into README.md for better discoverability

### Security
- Improved API key handling
- Enhanced input validation
- Better error message sanitization

---

## [Unreleased]

### Planned for Future Releases
- Vector store integrations (Pinecone, Weaviate, Qdrant, Chroma)
- Laravel integration package
- Additional provider integrations

---

## [1.1.0] - 2025-12-02

### Added
- **Async/Promise Support**
  - Async methods for all providers (`generateTextAsync`, `chatAsync`)
  - Non-blocking API calls using Guzzle promises
  - Promise chaining and error handling
  - Compatible with existing synchronous methods
  
- **Batch Request Processing**
  - `BatchProcessor` class for concurrent request processing
  - Process multiple requests in parallel
  - Significant performance improvements for bulk operations
  - Support for timeout handling and error recovery
  
- **Content Moderation**
  - `ContentModerationInterface` for providers supporting moderation
  - `ModerationResponse` class with category flags and scores
  - OpenAI moderation API integration
  - Batch moderation support
  - Async moderation methods

- **Agents System**
  - `Agent` class for autonomous multi-iteration problem solving
  - Automatic tool/function calling integration
  - Configurable maximum iterations
  - Human-in-the-loop callbacks:
    - `withBeforeToolExecution()` - Request approval before tool execution
    - `withAfterToolExecution()` - Review/modify tool execution results
    - `withBeforeFinalResponse()` - Review/modify final response
  - Tool execution tracking with duration metrics
  - Usage aggregation across iterations
  - `AgentResult` with complete execution history

- **Workflows System**
  - `Workflow` class for multi-step orchestration
  - Variable interpolation with `{{variable}}` syntax
  - Nested property access (e.g., `{{step.property}}`)
  - Per-step model configuration
  - Per-step structured output support
  - Per-step tool/function calling
  - Human-in-the-loop callbacks:
    - `withBeforeStep()` - Request approval before step execution
    - `withAfterStep()` - Review/modify step results
  - Context passing between steps
  - `WorkflowResult` with step-by-step results
  - Pre-set workflow variables

- **Database Migrations**
  - `create_llm_agent_executions_table.php` - Track agent execution history
  - `create_llm_workflow_executions_table.php` - Track workflow execution history
  - `create_llm_approval_requests_table.php` - Store approval requests
  - `create_llm_approval_history_table.php` - Track approval decisions

### Changed
- `HttpClientInterface` extended with async methods (`postAsync`, `multipartAsync`)
- `ProviderInterface` extended with async methods (`generateTextAsync`, `chatAsync`)
- `AbstractProvider` includes default async implementations

### Examples Added
- `async-promise-example.php` - Demonstrates async/promise usage
- `batch-processing-example.php` - Shows batch processing capabilities
- `content-moderation-example.php` - Content moderation examples
- `agent-basic.php` - Basic agent usage examples
- `agent-with-tools.php` - Agent with tool/function calling
- `agent-human-in-loop.php` - Human-in-the-loop agent patterns
- `workflow-basic.php` - Basic workflow examples
- `workflow-advanced.php` - Advanced workflow patterns
- `workflow-human-in-loop.php` - Human-in-the-loop workflow patterns

---

## Version History

- **1.1.0** (2025-12-02) - Agents, Workflows, Async/Promise support, Batch Processing, Content Moderation
- **1.0.1** (2025-12-01) - Documentation improvements, bug fixes, and code quality enhancements
- **1.0.0** (2025-12-01) - Initial production-ready release

---

## Installation

```bash
composer require arturas88/anyllm
```

For contribution guidelines, see [CONTRIBUTING.md](CONTRIBUTING.md).

