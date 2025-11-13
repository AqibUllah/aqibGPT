<?php

return [
    'default' => env('AI_DEFAULT_MODEL', 'gemini'),
    'providers' => [
        'ai-studio' => [
            'label' => 'Ai Studio',
            'api_key' => env('AI_STUDIO_API_KEY'),
            'endpoint' => env('AI_STUDIO_ENDPOINT', ''),
            'model' => env('AI_STUDIO_MODEL', ''),
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
