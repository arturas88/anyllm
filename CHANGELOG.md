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

## [Unreleased]

### Planned for Future Releases
- Vector store integrations (Pinecone, Weaviate, Qdrant, Chroma)
- Laravel integration package
- Async/Promise support
- Batch request processing
- Content moderation
- Additional provider integrations

---

## Version History

- **1.0.0** (2025-12-01) - Initial production-ready release

---

## Installation

```bash
composer require arturas88/anyllm
```

For contribution guidelines, see [CONTRIBUTING.md](CONTRIBUTING.md).

