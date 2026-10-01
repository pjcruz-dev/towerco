<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Assistant (tenant help / RAG)
    |--------------------------------------------------------------------------
    */

    'enabled' => (bool) env('AI_ASSISTANT_ENABLED', true),

    'embedding_provider' => env('AI_ASSISTANT_EMBEDDING_PROVIDER', 'local'),

    'vector_store' => env('AI_ASSISTANT_VECTOR_STORE', 'database'),

    // Prefer Gemini when an AI Studio key is present and provider is not explicitly set.
    'llm_provider' => env('AI_ASSISTANT_LLM_PROVIDER') ?: (
        filled(env('AI_ASSISTANT_GOOGLE_AI_API_KEY', env('GOOGLE_AI_API_KEY', '')))
            ? 'gemini'
            : 'local'
    ),

    'chunking' => [
        'size' => (int) env('AI_ASSISTANT_CHUNK_SIZE', 800),
        'overlap' => (int) env('AI_ASSISTANT_CHUNK_OVERLAP', 120),
    ],

    'retrieval' => [
        // When false, Ask TowerOS skips knowledge-base RAG (LLM + read-only tools only).
        'enabled' => (bool) env('AI_ASSISTANT_RETRIEVAL_ENABLED', false),
        // Local hash embeddings need a low floor; raise to ~0.25 when using OpenAI / Bedrock embeddings.
        'top_k' => (int) env('AI_ASSISTANT_RETRIEVAL_TOP_K', 5),
        'min_score' => (float) env('AI_ASSISTANT_RETRIEVAL_MIN_SCORE', 0.05),
        'min_lexical_hits' => (int) env('AI_ASSISTANT_RETRIEVAL_MIN_LEXICAL_HITS', 1),
        'min_combined_score' => (float) env('AI_ASSISTANT_RETRIEVAL_MIN_COMBINED_SCORE', 0.12),
    ],

    'conversation' => [
        // Prior turns included in the LLM prompt for follow-up resolution.
        'history_turns' => (int) env('AI_ASSISTANT_CONVERSATION_HISTORY_TURNS', 6),
    ],

    'local_embedding' => [
        'dimensions' => (int) env('AI_ASSISTANT_LOCAL_EMBEDDING_DIMENSIONS', 256),
    ],

    'bedrock' => [
        'region' => env('AI_ASSISTANT_BEDROCK_REGION', env('AWS_DEFAULT_REGION', 'ap-southeast-1')),
        'embedding_model_id' => env('AI_ASSISTANT_BEDROCK_EMBEDDING_MODEL', 'amazon.titan-embed-text-v2:0'),
        'dimensions' => (int) env('AI_ASSISTANT_BEDROCK_EMBEDDING_DIMENSIONS', 1024),
        'chat_model_id' => env('AI_ASSISTANT_BEDROCK_CHAT_MODEL', 'anthropic.claude-3-5-sonnet-20240620-v1:0'),
        'max_tokens' => (int) env('AI_ASSISTANT_BEDROCK_MAX_TOKENS', 1024),
        'temperature' => (float) env('AI_ASSISTANT_BEDROCK_TEMPERATURE', 0.2),
    ],

    'openai' => [
        'api_key' => env('AI_ASSISTANT_OPENAI_API_KEY', env('OPENAI_API_KEY', '')),
        'base_url' => env('AI_ASSISTANT_OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'chat_model' => env('AI_ASSISTANT_OPENAI_CHAT_MODEL', 'gpt-4o-mini'),
        // Comma-separated allowlist for the in-chat model selector.
        'chat_models' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env(
                'AI_ASSISTANT_OPENAI_CHAT_MODELS',
                'gpt-4o-mini,gpt-4o,gpt-4.1-mini,gpt-4.1',
            )),
        ))),
        'embedding_model' => env('AI_ASSISTANT_OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
        'dimensions' => (int) env('AI_ASSISTANT_OPENAI_EMBEDDING_DIMENSIONS', 1536),
        'max_tokens' => (int) env('AI_ASSISTANT_OPENAI_MAX_TOKENS', 1024),
        'temperature' => (float) env('AI_ASSISTANT_OPENAI_TEMPERATURE', 0.2),
        'timeout' => (int) env('AI_ASSISTANT_OPENAI_TIMEOUT', 60),
    ],

    'cursor' => [
        'api_key' => env('AI_ASSISTANT_CURSOR_API_KEY', env('CURSOR_API_KEY', '')),
        'base_url' => env('AI_ASSISTANT_CURSOR_BASE_URL', 'https://api.cursor.com/v1'),
        'model' => env('AI_ASSISTANT_CURSOR_MODEL', 'composer-2.5'),
        // Comma-separated allowlist — must match Cursor API model ids for the key (see GET /v1/models).
        'chat_models' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env(
                'AI_ASSISTANT_CURSOR_CHAT_MODELS',
                'composer-2.5,grok-4.5,grok-4.6,default',
            )),
        ))),
        'max_wait_seconds' => (int) env('AI_ASSISTANT_CURSOR_MAX_WAIT_SECONDS', 120),
        'poll_interval_ms' => (int) env('AI_ASSISTANT_CURSOR_POLL_INTERVAL_MS', 1500),
        'timeout' => (int) env('AI_ASSISTANT_CURSOR_TIMEOUT', 90),
        // Optional map of UI aliases → API model ids (e.g. auto-smart → default).
        'model_aliases' => [
            'auto' => 'default',
            'auto-smart' => 'default',
            'composer-2' => 'composer-2.5',
            'composer2' => 'composer-2.5',
        ],
    ],

    /*
    | Google AI Studio (Gemini) — Generative Language API
    | Get a key: https://aistudio.google.com/apikey
    */
    'gemini' => [
        'api_key' => env('AI_ASSISTANT_GOOGLE_AI_API_KEY', env('GOOGLE_AI_API_KEY', '')),
        'base_url' => env('AI_ASSISTANT_GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        'chat_model' => env('AI_ASSISTANT_GEMINI_CHAT_MODEL', 'gemini-3.6-flash'),
        // Comma-separated allowlist for the in-chat model selector (must support generateContent).
        'chat_models' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env(
                'AI_ASSISTANT_GEMINI_CHAT_MODELS',
                'gemini-3.8-flash,gemini-3.7-flash,gemini-3.6-flash,gemini-3.5-flash,gemini-3.5-flash-lite,gemini-3.1-pro-preview,gemini-3.1-flash-lite,gemini-3-flash-preview,gemini-2.5-pro,gemini-2.5-flash,gemini-2.5-flash-lite,gemini-flash-latest,gemini-pro-latest',
            )),
        ))),
        'max_tokens' => (int) env('AI_ASSISTANT_GEMINI_MAX_TOKENS', 2048),
        'temperature' => (float) env('AI_ASSISTANT_GEMINI_TEMPERATURE', 0.2),
        'timeout' => (int) env('AI_ASSISTANT_GEMINI_TIMEOUT', 60),
    ],

    'cost' => [
        'php_per_usd' => (float) env('AI_ASSISTANT_PHP_PER_USD', 58),
        // Defaults approximate Gemini Flash list prices (USD per 1M tokens).
        'input_usd_per_mtok' => (float) env('AI_ASSISTANT_COST_INPUT_USD_PER_MTOK', 0.075),
        'output_usd_per_mtok' => (float) env('AI_ASSISTANT_COST_OUTPUT_USD_PER_MTOK', 0.30),
    ],

    'opensearch' => [
        'endpoint' => env('AI_ASSISTANT_OPENSEARCH_ENDPOINT', ''),
        'index' => env('AI_ASSISTANT_OPENSEARCH_INDEX', 'toweros-ai-knowledge'),
        'region' => env('AI_ASSISTANT_OPENSEARCH_REGION', env('AWS_DEFAULT_REGION', 'ap-southeast-1')),
        'vector_field' => env('AI_ASSISTANT_OPENSEARCH_VECTOR_FIELD', 'embedding'),
    ],

    'rate_limit_per_minute' => (int) env('AI_ASSISTANT_RATE_LIMIT_PER_MINUTE', 20),

    // Per-user daily ask cap (0 = disabled).
    'daily_ask_limit' => (int) env('AI_ASSISTANT_DAILY_ASK_LIMIT', 0),

    'queue' => env('AI_ASSISTANT_QUEUE', env('TOWEROS_QUEUE_INTEGRATIONS', 'toweros-integrations')),

    /*
    |--------------------------------------------------------------------------
    | Read-only operational tools (Phase 9)
    |--------------------------------------------------------------------------
    */
    'tools' => [
        'enabled' => (bool) env('AI_ASSISTANT_TOOLS_ENABLED', true),
        'max_per_request' => (int) env('AI_ASSISTANT_TOOLS_MAX_PER_REQUEST', 2),
        'max_rows' => (int) env('AI_ASSISTANT_TOOLS_MAX_ROWS', 10),
        'timeout_seconds' => (int) env('AI_ASSISTANT_TOOLS_TIMEOUT_SECONDS', 5),
        // Stage-2 allowlisted planner when heuristics miss (follow-ups / module bias).
        'fallback_planner_enabled' => (bool) env('AI_ASSISTANT_TOOLS_FALLBACK_PLANNER', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Controlled write actions (Phase 10) — propose + confirm only
    |--------------------------------------------------------------------------
    */
    'actions' => [
        'enabled' => (bool) env('AI_ASSISTANT_ACTIONS_ENABLED', true),
        'proposal_ttl_minutes' => (int) env('AI_ASSISTANT_ACTIONS_TTL_MINUTES', 30),
    ],

];
