<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Provider
    |--------------------------------------------------------------------------
    */
    'default' => env('LLM_PROVIDER', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | Provider Configurations
    |--------------------------------------------------------------------------
    */
    'providers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'organization' => env('OPENAI_ORGANIZATION'),
            'project' => env('OPENAI_PROJECT'),
            'base_uri' => env('OPENAI_BASE_URI'),
            'default_model' => env('OPENAI_DEFAULT_MODEL', 'gpt-5.1-mini'),
            'timeout' => 60,
        ],

        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'base_uri' => env('ANTHROPIC_BASE_URI'),
            'default_model' => env('ANTHROPIC_DEFAULT_MODEL', 'claude-sonnet-4-5'),
            'timeout' => 120,
        ],

        'google' => [
            'api_key' => env('GOOGLE_AI_API_KEY'),
            'project_id' => env('GOOGLE_PROJECT_ID'),
            'default_model' => env('GOOGLE_DEFAULT_MODEL', 'gemini-2.5-flash'),
        ],

        'mistral' => [
            'api_key' => env('MISTRAL_API_KEY'),
            'default_model' => env('MISTRAL_DEFAULT_MODEL', 'mistral-medium-2508'),
        ],

        'xai' => [
            'api_key' => env('XAI_API_KEY'),
            'default_model' => env('XAI_DEFAULT_MODEL', 'grok-4.1-fast-non-reasoning'),
        ],

        'openrouter' => [
            'api_key' => env('OPENROUTER_API_KEY'),
            'default_model' => env('OPENROUTER_DEFAULT_MODEL', 'google/gemini-3-pro-preview'),
            'app_name' => env('APP_NAME'),
            'site_url' => env('APP_URL'),
        ],

        'ollama' => [
            'base_uri' => env('OLLAMA_BASE_URI', 'http://localhost:11434'),
            'default_model' => env('OLLAMA_DEFAULT_MODEL', 'llama3.2'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'enabled' => env('LLM_LOGGING_ENABLED', true),
        'driver' => env('LLM_LOG_DRIVER', 'database'), // database, file, null
        'table' => 'llm_log',
        'file_path' => storage_path('logs/llm.log'),
        'log_requests' => true,
        'log_responses' => true,
        'log_errors' => true,
        'mask_api_keys' => true,
        'truncate_content' => 10000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cost Tracking Configuration
    |--------------------------------------------------------------------------
    */
    'cost_tracking' => [
        'enabled' => env('LLM_COST_TRACKING_ENABLED', true),
        'table' => 'llm_usage',
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    */
    'queue' => [
        'enabled' => env('LLM_QUEUE_ENABLED', false),
        'driver' => env('LLM_QUEUE_DRIVER', 'database'),
        'table' => 'llm_task',
        'default_priority' => 50,
        'max_retries' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Client Configuration
    |--------------------------------------------------------------------------
    */
    'http' => [
        'timeout' => env('LLM_HTTP_TIMEOUT', 60),
        'retry_times' => env('LLM_RETRY_TIMES', 3),
        'retry_sleep' => env('LLM_RETRY_SLEEP', 1000),
        'retry_multiplier' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Conversation Management
    |--------------------------------------------------------------------------
    */
    'conversations' => [
        'enabled' => env('LLM_CONVERSATIONS_ENABLED', true),
        
        // Repository driver: 'database', 'redis', 'file', or null for in-memory only
        'repository' => env('LLM_CONVERSATION_REPOSITORY', 'database'),
        
        // Database settings
        'table' => 'llm_conversation',
        'messages_table' => 'llm_message',
        
        // Redis settings (if using redis repository)
        'redis' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => env('REDIS_PORT', 6379),
            'password' => env('REDIS_PASSWORD', null),
            'database' => env('REDIS_DB', 0),
        ],
        
        // File storage path (if using file repository)
        'file_storage_path' => env('LLM_CONVERSATION_STORAGE', sys_get_temp_dir() . '/anyllm-conversations'),
        
        // Auto-summarization settings
        'auto_summarize' => env('LLM_AUTO_SUMMARIZE', true),
        'summary_model' => env('LLM_SUMMARY_MODEL', 'gpt-4o-mini'),
        'summarize_after_messages' => env('LLM_SUMMARIZE_AFTER', 20),
        'keep_recent_messages' => env('LLM_KEEP_RECENT_MESSAGES', 5),
        
        // Token optimization
        'max_context_tokens' => env('LLM_MAX_CONTEXT_TOKENS', 4000),
        'summary_prompt_template' => 'Summarize the following conversation concisely, preserving key information, decisions, and context:',
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Aliases
    |--------------------------------------------------------------------------
    */
    'aliases' => [
        'fast' => 'openai:gpt-5.1-nano',
        'balanced' => 'openai:gpt-5.1-mini',
        'smart' => 'openai:gpt-5.1',
        'creative' => 'anthropic:claude-sonnet-4-5',
        'reasoning' => 'anthropic:claude-opus-4-5',
        'vision' => 'openai:gpt-5.1',
        'cheap' => 'google:gemini-2.5-flash',
    ],
];

