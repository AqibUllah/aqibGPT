<?php

return [
    'default' => env('AI_DEFAULT_MODEL', 'gemini'),
    'providers' => [
        'ollama' => [
            'model' => env('OLLAMA_MODEL', 'llama3.1'),
            'url' => env('OLLAMA_URL', 'http://127.0.0.1:11434'),
            'default_prompt' => env('OLLAMA_DEFAULT_PROMPT', 'Hello, how can I assist you today?'),
            'connection' => [
                'timeout' => env('OLLAMA_CONNECTION_TIMEOUT', 300),
            ]
        ],
        'gemini' => [
            'label' => 'Gemini',
            'api_key' => env('GEMINI_API_KEY'),
            'endpoint' => env('GEMINI_ENDPOINT', ''),
            'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),
        ],
        'openai' => [
            'label' => 'OpenAI',
            'api_key' => env('OPENAI_API_KEY'),
            'endpoint' => env('OPENAI_ENDPOINT', 'https://api.openai.com/v1/chat/completions'),
            'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        ],
    ],
];
