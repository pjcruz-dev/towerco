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

    'llm_provider' => env('AI_ASSISTANT_LLM_PROVIDER', 'local'),

    'chunking' => [
        'size' => (int) env('AI_ASSISTANT_CHUNK_SIZE', 800),
        'overlap' => (int) env('AI_ASSISTANT_CHUNK_OVERLAP', 120),
    ],

    'retrieval' => [
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
        'embedding_model' => env('AI_ASSISTANT_OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
        'dimensions' => (int) env('AI_ASSISTANT_OPENAI_EMBEDDING_DIMENSIONS', 1536),
        'max_tokens' => (int) env('AI_ASSISTANT_OPENAI_MAX_TOKENS', 1024),
        'temperature' => (float) env('AI_ASSISTANT_OPENAI_TEMPERATURE', 0.2),
        'timeout' => (int) env('AI_ASSISTANT_OPENAI_TIMEOUT', 60),
    ],

    'cursor' => [
        'api_key' => env('AI_ASSISTANT_CURSOR_API_KEY', env('CURSOR_API_KEY', '')),
        'base_url' => env('AI_ASSISTANT_CURSOR_BASE_URL', 'https://api.cursor.com/v1'),
        'model' => env('AI_ASSISTANT_CURSOR_MODEL', 'composer-2'),
        'max_wait_seconds' => (int) env('AI_ASSISTANT_CURSOR_MAX_WAIT_SECONDS', 120),
        'poll_interval_ms' => (int) env('AI_ASSISTANT_CURSOR_POLL_INTERVAL_MS', 1500),
        'timeout' => (int) env('AI_ASSISTANT_CURSOR_TIMEOUT', 30),
    ],

    'opensearch' => [
        'endpoint' => env('AI_ASSISTANT_OPENSEARCH_ENDPOINT', ''),
        'index' => env('AI_ASSISTANT_OPENSEARCH_INDEX', 'toweros-ai-knowledge'),
        'region' => env('AI_ASSISTANT_OPENSEARCH_REGION', env('AWS_DEFAULT_REGION', 'ap-southeast-1')),
        'vector_field' => env('AI_ASSISTANT_OPENSEARCH_VECTOR_FIELD', 'embedding'),
    ],

    'rate_limit_per_minute' => (int) env('AI_ASSISTANT_RATE_LIMIT_PER_MINUTE', 20),

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
