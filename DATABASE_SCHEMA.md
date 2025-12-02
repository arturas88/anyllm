# Database Schema - Production Hardened

Complete database schema for **AnyLLM** - optimized for SaaS and multi-tenant applications.

---

## 🎯 Supported Databases

AnyLLM supports multiple database drivers:

- **SQLite** - Perfect for local development (zero setup, file-based)
- **MySQL** - Production-ready, high performance
- **PostgreSQL** - Advanced features, excellent JSON support

**Note**: All schemas are compatible across all supported databases. SQLite is recommended for local development as it requires no server setup.

---

## 🎯 Design Principles

1. **Multi-Tenancy Ready** - Organization/Team/User hierarchy
2. **Environment Aware** - Track dev/staging/production
3. **Full Audit Trail** - Track who, when, where, why
4. **Performance Optimized** - Strategic indexes for common queries
5. **Scalable** - Designed for millions of records
6. **Secure** - Encrypted keys, rate limiting, cost controls

---

## 📊 Tables Overview

| Table | Purpose | Records Expected |
|-------|---------|------------------|
| `llm_conversation` | Conversation metadata & summaries | Millions |
| `llm_message` | Individual messages | Tens of millions |
| `llm_log` | Request/response logs | Hundreds of millions |
| `llm_usage` | Usage analytics (aggregated) | Millions |
| `llm_task` | Async queue | Thousands (active) |
| `llm_api_key` | API key management | Thousands |
| `llm_api_key_rotation` | Key rotation history | Thousands |
| `llm_api_key_usage` | Daily key usage summary | Millions |
| `llm_agent_execution` | Agent execution tracking & state | Millions |
| `llm_workflow_execution` | Workflow execution tracking & state | Millions |
| `llm_approval_request` | Pending approval requests | Hundreds of thousands (active) |
| `llm_approval_history` | Approval decision audit trail | Tens of millions |

---

## 🗄️ Detailed Schema

### 1. **llm_conversation** - Conversation Management

Stores conversation metadata, summaries, and configuration.

```sql
CREATE TABLE llm_conversation (
    -- Primary key
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    uuid VARCHAR(36) UNIQUE NOT NULL,
    
    -- Multi-tenancy
    organization_id VARCHAR(255) NULL,
    team_id VARCHAR(255) NULL,
    user_id VARCHAR(255) NULL,
    session_id VARCHAR(255) NULL,
    
    -- Environment
    environment VARCHAR(20) DEFAULT 'production',
    
    -- Basic info
    title VARCHAR(255) NULL,
    metadata JSON NULL,
    
    -- Summary management
    summary TEXT NULL,
    summary_token_count INT DEFAULT 0,
    summarized_at TIMESTAMP NULL,
    messages_summarized INT DEFAULT 0,
    
    -- Token tracking
    total_messages INT DEFAULT 0,
    total_tokens_used INT DEFAULT 0,
    total_cost DECIMAL(10,6) DEFAULT 0,
    
    -- Configuration
    auto_summarize BOOLEAN DEFAULT TRUE,
    summarize_after_messages INT DEFAULT 20,
    keep_recent_messages INT DEFAULT 5,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    
    -- Indexes
    INDEX idx_uuid (uuid),
    INDEX idx_user_created (user_id, created_at),
    INDEX idx_session_created (session_id, created_at),
    INDEX idx_org_created (organization_id, created_at),
    INDEX idx_org_user (organization_id, user_id),
    INDEX idx_environment (environment)
);
```

**Key Features:**
- ✅ UUID for public API exposure
- ✅ Soft deletes for data retention
- ✅ Auto-summarization configuration
- ✅ Multi-tenant support
- ✅ Token/cost tracking per conversation

---

### 2. **llm_message** - Individual Messages

Stores each message within conversations.

```sql
CREATE TABLE llm_message (
    -- Primary key
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    conversation_id BIGINT UNSIGNED NOT NULL,
    
    -- Multi-tenancy (denormalized)
    organization_id VARCHAR(255) NULL,
    user_id VARCHAR(255) NULL,
    
    -- Message info
    role VARCHAR(20) NOT NULL, -- system, user, assistant, tool
    content LONGTEXT NOT NULL,
    metadata JSON NULL,
    
    -- Token tracking
    prompt_tokens INT DEFAULT 0,
    completion_tokens INT DEFAULT 0,
    total_tokens INT DEFAULT 0,
    cost DECIMAL(10,6) NULL,
    
    -- Model info
    model VARCHAR(255) NULL,
    provider VARCHAR(255) NULL,
    finish_reason VARCHAR(255) NULL,
    
    -- Tool calls
    tool_calls JSON NULL,
    tool_call_id VARCHAR(255) NULL,
    
    -- Summary tracking
    included_in_summary BOOLEAN DEFAULT FALSE,
    summarized_at TIMESTAMP NULL,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Foreign keys
    FOREIGN KEY (conversation_id) REFERENCES llm_conversation(id) ON DELETE CASCADE,
    
    -- Indexes
    INDEX idx_conversation_created (conversation_id, created_at),
    INDEX idx_conversation_role (conversation_id, role),
    INDEX idx_org (organization_id),
    INDEX idx_user (user_id)
);
```

**Key Features:**
- ✅ Full message history
- ✅ Token/cost per message
- ✅ Tool calling support
- ✅ Summarization tracking
- ✅ Cascade delete with conversations

---

### 3. **llm_log** - Request/Response Logging

Complete audit trail of all LLM interactions.

```sql
CREATE TABLE llm_log (
    -- Primary key
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    
    -- Request tracing (distributed systems)
    request_id VARCHAR(36) NOT NULL,
    trace_id VARCHAR(36) NULL,
    parent_request_id VARCHAR(36) NULL,
    
    -- Basic info
    event_type VARCHAR(20) NOT NULL, -- request, response, error, stream_chunk
    provider VARCHAR(50) NOT NULL,
    method VARCHAR(100) NOT NULL,
    model VARCHAR(255) NULL,
    
    -- Multi-tenancy
    organization_id VARCHAR(255) NULL,
    team_id VARCHAR(255) NULL,
    user_id VARCHAR(255) NULL,
    session_id VARCHAR(255) NULL,
    
    -- Environment
    environment VARCHAR(20) DEFAULT 'production',
    
    -- Request/Response data
    request JSON NULL,
    response JSON NULL,
    context JSON NULL,
    
    -- Performance metrics
    duration FLOAT NULL, -- milliseconds
    prompt_tokens INT NULL,
    completion_tokens INT NULL,
    total_tokens INT NULL,
    cost DECIMAL(10,6) NULL,
    
    -- Audit fields
    ip_address VARCHAR(45) NULL, -- IPv4/IPv6
    user_agent TEXT NULL,
    api_key_id VARCHAR(255) NULL,
    
    -- Timestamp
    created_at TIMESTAMP(6) DEFAULT CURRENT_TIMESTAMP(6),
    
    -- Indexes
    INDEX idx_request_id (request_id),
    INDEX idx_trace_id (trace_id),
    INDEX idx_parent_request (parent_request_id),
    INDEX idx_provider_created (provider, created_at),
    INDEX idx_user_created (user_id, created_at),
    INDEX idx_org_created (organization_id, created_at),
    INDEX idx_environment_created (environment, created_at),
    INDEX idx_trace_created (trace_id, created_at),
    INDEX idx_api_key (api_key_id),
    INDEX idx_event_type (event_type)
);
```

**Key Features:**
- ✅ Distributed tracing support
- ✅ Complete audit trail
- ✅ Performance monitoring
- ✅ Security tracking (IP, User Agent)
- ✅ Microsecond timestamps

---

### 4. **llm_usage** - Usage Analytics

Aggregated usage statistics for analytics and billing.

```sql
CREATE TABLE llm_usage (
    -- Primary key
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    
    -- Provider info
    provider VARCHAR(50) NOT NULL,
    model VARCHAR(100) NOT NULL,
    
    -- Multi-tenancy
    organization_id VARCHAR(255) NULL,
    team_id VARCHAR(255) NULL,
    user_id VARCHAR(255) NULL,
    
    -- Linkage
    conversation_id BIGINT UNSIGNED NULL,
    message_id BIGINT UNSIGNED NULL,
    request_id VARCHAR(36) NULL,
    
    -- Environment
    environment VARCHAR(20) DEFAULT 'production',
    
    -- Token tracking
    input_tokens INT DEFAULT 0,
    output_tokens INT DEFAULT 0,
    cached_tokens INT DEFAULT 0,
    total_tokens INT DEFAULT 0,
    
    -- Cost tracking
    cost DECIMAL(10,6) NULL,
    
    -- Metadata
    metadata JSON NULL,
    
    -- Timestamps
    date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Foreign keys
    FOREIGN KEY (conversation_id) REFERENCES llm_conversation(id) ON DELETE SET NULL,
    FOREIGN KEY (message_id) REFERENCES llm_message(id) ON DELETE SET NULL,
    
    -- Indexes
    INDEX idx_provider_created (provider, created_at),
    INDEX idx_user_created (user_id, created_at),
    INDEX idx_model_created (model, created_at),
    INDEX idx_org_date (organization_id, date),
    INDEX idx_org_user_date (organization_id, user_id, date),
    INDEX idx_conversation_created (conversation_id, created_at),
    INDEX idx_date (date),
    INDEX idx_request (request_id),
    INDEX idx_environment (environment)
);
```

**Key Features:**
- ✅ Fast date-based aggregation
- ✅ Links to conversations/messages
- ✅ Separate date column for analytics
- ✅ Total tokens convenience field

---

### 5. **llm_task** - Queue System

Async task processing for LLM operations.

```sql
CREATE TABLE llm_task (
    -- Primary key
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    uuid VARCHAR(36) UNIQUE NOT NULL,
    
    -- Multi-tenancy
    organization_id VARCHAR(255) NULL,
    team_id VARCHAR(255) NULL,
    user_id VARCHAR(255) NULL,
    
    -- Environment
    environment VARCHAR(20) DEFAULT 'production',
    
    -- Queue management
    queue_name VARCHAR(100) DEFAULT 'default',
    task_type VARCHAR(50) NOT NULL,
    status VARCHAR(20) DEFAULT 'pending',
    
    -- Task data
    parameters JSON NOT NULL,
    result JSON NULL,
    error TEXT NULL,
    
    -- Priority & retry
    priority INT DEFAULT 50,
    attempts INT DEFAULT 0,
    max_retries INT DEFAULT 3,
    timeout INT DEFAULT 300, -- seconds
    
    -- Worker tracking
    worker_id VARCHAR(255) NULL,
    worker_host VARCHAR(255) NULL,
    
    -- Timestamps
    available_at TIMESTAMP NULL,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    failed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_uuid (uuid),
    INDEX idx_status_available (status, available_at),
    INDEX idx_status_priority (status, priority),
    INDEX idx_queue_status_available (queue_name, status, available_at),
    INDEX idx_org_status (organization_id, status),
    INDEX idx_environment (environment),
    INDEX idx_queue (queue_name)
);
```

**Key Features:**
- ✅ Multi-queue support
- ✅ Worker tracking
- ✅ Timeout handling
- ✅ Priority scheduling
- ✅ Retry logic

---

### 6. **llm_api_key** - API Key Management

Secure API key storage and management.

```sql
CREATE TABLE llm_api_key (
    -- Primary key
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    uuid VARCHAR(36) UNIQUE NOT NULL,
    
    -- Multi-tenancy
    organization_id VARCHAR(255) NULL,
    team_id VARCHAR(255) NULL,
    user_id VARCHAR(255) NULL,
    
    -- Provider association
    provider VARCHAR(50) NOT NULL,
    
    -- Key storage (encrypted)
    encrypted_key TEXT NOT NULL,
    key_hash VARCHAR(64) UNIQUE NOT NULL,
    key_prefix VARCHAR(10) NULL,
    
    -- Key metadata
    name VARCHAR(255) NULL,
    description TEXT NULL,
    scopes JSON NULL,
    
    -- Rate limiting (per key)
    rate_limit_per_minute INT NULL,
    rate_limit_per_hour INT NULL,
    rate_limit_per_day INT NULL,
    rate_limit_per_month INT NULL,
    
    -- Cost controls
    cost_limit_daily DECIMAL(10,4) NULL,
    cost_limit_monthly DECIMAL(10,4) NULL,
    current_daily_cost DECIMAL(10,4) DEFAULT 0,
    current_monthly_cost DECIMAL(10,4) DEFAULT 0,
    
    -- Token limits
    token_limit_daily INT NULL,
    token_limit_monthly INT NULL,
    current_daily_tokens INT DEFAULT 0,
    current_monthly_tokens INT DEFAULT 0,
    
    -- Status & lifecycle
    is_active BOOLEAN DEFAULT TRUE,
    is_test_key BOOLEAN DEFAULT FALSE,
    last_used_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    rotated_at TIMESTAMP NULL,
    
    -- Audit fields
    created_by VARCHAR(255) NULL,
    last_used_ip VARCHAR(45) NULL,
    total_requests INT DEFAULT 0,
    total_tokens INT DEFAULT 0,
    total_cost DECIMAL(10,4) DEFAULT 0,
    
    -- Security
    failed_attempts INT DEFAULT 0,
    locked_at TIMESTAMP NULL,
    lock_reason TEXT NULL,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    
    -- Indexes
    INDEX idx_uuid (uuid),
    INDEX idx_key_hash (key_hash),
    INDEX idx_org_active (organization_id, is_active),
    INDEX idx_user_active (user_id, is_active),
    INDEX idx_provider_active (provider, is_active),
    INDEX idx_active_last_used (is_active, last_used_at),
    INDEX idx_expires_active (expires_at, is_active),
    INDEX idx_is_active (is_active),
    INDEX idx_is_test (is_test_key)
);
```

**Key Features:**
- ✅ Encrypted key storage
- ✅ Rate limiting per key
- ✅ Cost controls per key
- ✅ Key rotation support
- ✅ Security lockout
- ✅ Test vs production keys

---

### 7. **llm_api_key_rotation** - Key Rotation History

Audit trail of API key rotations.

```sql
CREATE TABLE llm_api_key_rotation (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    api_key_id BIGINT UNSIGNED NOT NULL,
    old_key_hash VARCHAR(64) NOT NULL,
    new_key_hash VARCHAR(64) NOT NULL,
    rotated_by VARCHAR(255) NULL,
    reason VARCHAR(255) NULL,
    rotated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (api_key_id) REFERENCES llm_api_key(id) ON DELETE CASCADE,
    
    INDEX idx_api_key_rotated (api_key_id, rotated_at),
    INDEX idx_old_hash (old_key_hash),
    INDEX idx_new_hash (new_key_hash)
);
```

---

### 8. **llm_api_key_usage** - Daily Key Usage

Aggregated daily usage per API key.

```sql
CREATE TABLE llm_api_key_usage (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    api_key_id BIGINT UNSIGNED NOT NULL,
    date DATE NOT NULL,
    total_requests INT DEFAULT 0,
    successful_requests INT DEFAULT 0,
    failed_requests INT DEFAULT 0,
    total_tokens INT DEFAULT 0,
    total_cost DECIMAL(10,4) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (api_key_id) REFERENCES llm_api_key(id) ON DELETE CASCADE,
    
    UNIQUE KEY unique_key_date (api_key_id, date),
    INDEX idx_date_requests (date, total_requests)
);
```

---

### 9. **llm_agent_execution** - Agent Execution Tracking

Tracks agent executions with state management and Human In The Loop support.

```sql
CREATE TABLE llm_agent_execution (
    -- Primary key
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    uuid VARCHAR(36) UNIQUE NOT NULL,
    
    -- Multi-tenancy
    organization_id VARCHAR(255) NULL,
    team_id VARCHAR(255) NULL,
    user_id VARCHAR(255) NULL,
    
    -- Environment
    environment VARCHAR(20) DEFAULT 'production',
    
    -- Agent info
    agent_type VARCHAR(50) DEFAULT 'agent',
    model VARCHAR(255) NOT NULL,
    provider VARCHAR(50) NOT NULL,
    system_prompt TEXT NULL,
    input TEXT NULL,
    
    -- Execution state
    status VARCHAR(20) DEFAULT 'running', -- running, paused, completed, failed, cancelled
    current_iteration INT DEFAULT 0,
    max_iterations INT DEFAULT 10,
    
    -- Results
    final_content TEXT NULL,
    messages JSON NULL,
    tool_executions JSON NULL,
    context JSON NULL,
    
    -- Usage tracking
    total_tokens INT DEFAULT 0,
    prompt_tokens INT DEFAULT 0,
    completion_tokens INT DEFAULT 0,
    cost DECIMAL(10,6) NULL,
    
    -- Pending approvals (Human In The Loop)
    has_pending_approval BOOLEAN DEFAULT FALSE,
    pending_approval_type VARCHAR(50) NULL, -- tool_execution, final_response
    pending_approval_data JSON NULL,
    
    -- Linkage
    conversation_id VARCHAR(255) NULL,
    parent_execution_id VARCHAR(255) NULL,
    
    -- Timestamps
    started_at TIMESTAMP NULL,
    paused_at TIMESTAMP NULL,
    resumed_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    failed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_uuid (uuid),
    INDEX idx_status_approval (status, has_pending_approval),
    INDEX idx_user_status_created (user_id, status, created_at),
    INDEX idx_org_status_created (organization_id, status, created_at),
    INDEX idx_agent_type_status (agent_type, status)
);
```

**Key Features:**
- ✅ Execution state tracking (running, paused, completed)
- ✅ Human In The Loop support (pending approvals)
- ✅ Full message and tool execution history
- ✅ Usage and cost tracking per execution
- ✅ Pause/resume capability

---

### 10. **llm_workflow_execution** - Workflow Execution Tracking

Tracks workflow executions with step-level state management and approvals.

```sql
CREATE TABLE llm_workflow_execution (
    -- Primary key
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    uuid VARCHAR(36) UNIQUE NOT NULL,
    
    -- Multi-tenancy
    organization_id VARCHAR(255) NULL,
    team_id VARCHAR(255) NULL,
    user_id VARCHAR(255) NULL,
    
    -- Environment
    environment VARCHAR(20) DEFAULT 'production',
    
    -- Workflow info
    workflow_name VARCHAR(255) NULL,
    default_model VARCHAR(255) NOT NULL,
    provider VARCHAR(50) NOT NULL,
    steps_config JSON NULL,
    input_variables JSON NULL,
    
    -- Execution state
    status VARCHAR(20) DEFAULT 'running', -- running, paused, completed, failed, cancelled
    current_step VARCHAR(255) NULL,
    completed_steps INT DEFAULT 0,
    total_steps INT DEFAULT 0,
    
    -- Results
    step_results JSON NULL,
    context_variables JSON NULL,
    final_output TEXT NULL,
    
    -- Usage tracking
    total_tokens INT DEFAULT 0,
    cost DECIMAL(10,6) NULL,
    
    -- Pending approvals (Human In The Loop)
    has_pending_approval BOOLEAN DEFAULT FALSE,
    pending_step_name VARCHAR(255) NULL,
    pending_approval_data JSON NULL,
    
    -- Linkage
    conversation_id VARCHAR(255) NULL,
    parent_execution_id VARCHAR(255) NULL,
    
    -- Timestamps
    started_at TIMESTAMP NULL,
    paused_at TIMESTAMP NULL,
    resumed_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    failed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_uuid (uuid),
    INDEX idx_status_approval (status, has_pending_approval),
    INDEX idx_user_status_created (user_id, status, created_at),
    INDEX idx_org_status_created (organization_id, status, created_at),
    INDEX idx_current_step_status (current_step, status)
);
```

**Key Features:**
- ✅ Step-by-step execution tracking
- ✅ Human In The Loop at step level
- ✅ Workflow context variable management
- ✅ Step result storage
- ✅ Pause/resume capability

---

### 11. **llm_approval_request** - Approval Request Management

Manages pending approval requests for Human In The Loop operations.

```sql
CREATE TABLE llm_approval_request (
    -- Primary key
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    uuid VARCHAR(36) UNIQUE NOT NULL,
    
    -- Multi-tenancy
    organization_id VARCHAR(255) NULL,
    team_id VARCHAR(255) NULL,
    user_id VARCHAR(255) NULL,
    requested_by VARCHAR(255) NULL,
    
    -- Environment
    environment VARCHAR(20) DEFAULT 'production',
    
    -- Execution linkage
    execution_type VARCHAR(20) NOT NULL, -- agent, workflow
    execution_id VARCHAR(255) NOT NULL,
    execution_uuid VARCHAR(36) NULL,
    
    -- Approval details
    approval_type VARCHAR(50) NOT NULL, -- tool_execution, step_execution, final_response, step_result
    approval_key VARCHAR(255) NULL, -- tool_name, step_name, etc.
    description TEXT NULL,
    request_data JSON NULL,
    context JSON NULL,
    
    -- Approval state
    status VARCHAR(20) DEFAULT 'pending', -- pending, approved, rejected, expired, cancelled
    decision_reason TEXT NULL,
    decision_metadata JSON NULL,
    
    -- Approver info
    approved_by VARCHAR(255) NULL,
    approved_at TIMESTAMP NULL,
    rejected_by VARCHAR(255) NULL,
    rejected_at TIMESTAMP NULL,
    
    -- Timeouts
    timeout_minutes INT NULL,
    expires_at TIMESTAMP NULL,
    
    -- Priority
    priority INT DEFAULT 50,
    
    -- Notification tracking
    notification_sent BOOLEAN DEFAULT FALSE,
    notification_sent_at TIMESTAMP NULL,
    notification_channels JSON NULL,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_uuid (uuid),
    INDEX idx_execution (execution_type, execution_id, status),
    INDEX idx_status_expires (status, expires_at),
    INDEX idx_user_status_created (user_id, status, created_at),
    INDEX idx_org_status_created (organization_id, status, created_at),
    INDEX idx_approval_type_status (approval_type, status)
);
```

**Key Features:**
- ✅ Approval request lifecycle management
- ✅ Timeout and expiration handling
- ✅ Notification tracking
- ✅ Priority-based ordering
- ✅ Full audit trail

---

### 12. **llm_approval_history** - Approval Decision Audit Trail

Complete audit trail of all approval decisions.

```sql
CREATE TABLE llm_approval_history (
    -- Primary key
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    
    -- Linkage
    approval_request_id VARCHAR(36) NULL,
    execution_type VARCHAR(20) NOT NULL,
    execution_id VARCHAR(255) NOT NULL,
    
    -- Multi-tenancy
    organization_id VARCHAR(255) NULL,
    team_id VARCHAR(255) NULL,
    user_id VARCHAR(255) NULL,
    
    -- Approval details
    approval_type VARCHAR(50) NOT NULL,
    approval_key VARCHAR(255) NULL,
    action VARCHAR(20) NOT NULL, -- approved, rejected, modified, auto_approved, auto_rejected
    
    -- Decision data
    original_data TEXT NULL,
    modified_data TEXT NULL,
    decision_reason TEXT NULL,
    metadata JSON NULL,
    
    -- Actor info
    acted_by VARCHAR(255) NULL,
    acted_by_type VARCHAR(20) NULL, -- user, system, auto
    
    -- Timestamp
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_execution_created (execution_type, execution_id, created_at),
    INDEX idx_approval_type_action (approval_type, action, created_at),
    INDEX idx_user_created (user_id, created_at),
    INDEX idx_org_created (organization_id, created_at)
);
```

**Key Features:**
- ✅ Complete audit trail
- ✅ Tracks modifications to approved data
- ✅ Actor identification (user/system/auto)
- ✅ Compliance and security auditing

---

## 🚀 Performance Optimization

### Index Strategy

**Conversation Queries:**
```sql
-- Find user's recent conversations
SELECT * FROM llm_conversation 
WHERE user_id = ? AND deleted_at IS NULL 
ORDER BY created_at DESC;
-- Uses: idx_user_created

-- Find organization's conversations
SELECT * FROM llm_conversation 
WHERE organization_id = ? 
ORDER BY created_at DESC;
-- Uses: idx_org_created
```

**Analytics Queries:**
```sql
-- Daily usage by organization
SELECT date, SUM(total_tokens), SUM(cost) 
FROM llm_usage 
WHERE organization_id = ? AND date BETWEEN ? AND ?
GROUP BY date;
-- Uses: idx_org_date

-- Provider performance
SELECT provider, AVG(duration), COUNT(*) 
FROM llm_log 
WHERE created_at >= ? 
GROUP BY provider;
-- Uses: idx_provider_created
```

**Queue Processing:**
```sql
-- Get next task
SELECT * FROM llm_task 
WHERE queue_name = ? AND status = 'pending' 
  AND available_at <= NOW()
ORDER BY priority DESC, created_at ASC 
LIMIT 1;
-- Uses: idx_queue_status_available
```

**Human In The Loop Queries:**
```sql
-- Find pending approvals for a user
SELECT * FROM llm_approval_request 
WHERE user_id = ? AND status = 'pending' 
  AND (expires_at IS NULL OR expires_at > NOW())
ORDER BY priority DESC, created_at ASC;
-- Uses: idx_user_status_created

-- Find executions waiting for approval
SELECT * FROM llm_agent_execution 
WHERE has_pending_approval = TRUE 
  AND status = 'paused'
ORDER BY created_at ASC;
-- Uses: idx_status_approval

-- Get approval history for an execution
SELECT * FROM llm_approval_history 
WHERE execution_type = ? AND execution_id = ?
ORDER BY created_at DESC;
-- Uses: idx_execution_created

-- Find workflow executions at a specific step
SELECT * FROM llm_workflow_execution 
WHERE current_step = ? AND status = 'running';
-- Uses: idx_current_step_status
```

---

## 🔒 Security Features

1. **Encrypted API Keys** - Never store keys in plain text
2. **Key Hashing** - Fast lookup without decryption
3. **Rate Limiting** - Multiple levels (minute/hour/day/month)
4. **Cost Controls** - Prevent runaway costs
5. **IP Tracking** - Detect suspicious usage
6. **Lockout Mechanism** - Security protection
7. **Soft Deletes** - Data retention and recovery

---

## 📈 Scalability Considerations

### Partitioning Strategies

**llm_log** - Partition by month:
```sql
PARTITION BY RANGE (YEAR(created_at) * 100 + MONTH(created_at));
```

**llm_usage** - Partition by year:
```sql
PARTITION BY RANGE (YEAR(date));
```

### Archiving Strategy

1. **Hot data** (< 30 days) - Primary tables
2. **Warm data** (30-365 days) - Compressed tables
3. **Cold data** (> 365 days) - Archive storage (S3/Glacier)

---

## 🎯 Migration Order

Run migrations in this order:
1. `create_llm_conversation_table.php` (conversation + message)
2. `create_llm_log_table.php`
3. `create_llm_usage_table.php`
4. `create_llm_task_table.php`
5. `create_llm_api_key_table.php`
6. `create_llm_agent_executions_table.php` (Human In The Loop)
7. `create_llm_workflow_executions_table.php` (Human In The Loop)
8. `create_llm_approval_requests_table.php` (Human In The Loop)
9. `create_llm_approval_history_table.php` (Human In The Loop)

### SQLite Notes

For SQLite, you may need to adjust some SQL syntax:
- `BIGINT UNSIGNED` → `INTEGER` (SQLite doesn't support UNSIGNED)
- `TIMESTAMP DEFAULT CURRENT_TIMESTAMP` → `DATETIME DEFAULT CURRENT_TIMESTAMP`
- `AUTO_INCREMENT` → `AUTOINCREMENT`
- Some MySQL-specific functions may need adjustment

The schema is designed to work with all three databases, but minor adjustments may be needed for SQLite-specific syntax.

---

## 📊 Storage Estimates

Assuming 1M users, 100K organizations:

| Table | Rows/Day | Size/Row | Daily Growth | Monthly Growth |
|-------|----------|----------|--------------|----------------|
| llm_conversation | 1M | 500B | 500MB | 15GB |
| llm_message | 10M | 1KB | 10GB | 300GB |
| llm_log | 50M | 2KB | 100GB | 3TB |
| llm_usage | 50M | 200B | 10GB | 300GB |
| llm_task | 1M | 1KB | 1GB | 30GB |
| llm_agent_execution | 500K | 2KB | 1GB | 30GB |
| llm_workflow_execution | 500K | 2KB | 1GB | 30GB |
| llm_approval_request | 100K | 1KB | 100MB | 3GB |
| llm_approval_history | 1M | 500B | 500MB | 15GB |

**Total: ~125GB/day, ~3.75TB/month**

---

## ✅ Production Checklist

- [ ] All migrations tested
- [ ] Indexes verified for query patterns
- [ ] Partitioning strategy decided
- [ ] Archiving policy defined
- [ ] Backup strategy implemented
- [ ] Monitoring set up
- [ ] Security audit completed
- [ ] Load testing performed

---

**Schema Version:** 1.1.0 (Production Hardened + Human In The Loop)
**Last Updated:** 2025-12-01

