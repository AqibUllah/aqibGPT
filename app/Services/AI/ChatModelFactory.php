<?php

namespace App\Services\AI;

use App\Services\AI\Contracts\ChatModelStrategy;
use App\Services\AI\Strategies\AiStudioStrategy;
use App\Services\AI\Strategies\GeminiStrategy;
use App\Services\AI\Strategies\OpenAIStrategy;

class ChatModelFactory
{
    public static function make(string $provider): ChatModelStrategy
    {
        $providers = config('ai.providers');
        $config = $providers[$provider] ?? $providers[config('ai.default')];

        return match ($provider) {
            'ai-studio' => new AiStudioStrategy($config),
            'gemini' => new GeminiStrategy($config),
            'openai' => new OpenAIStrategy($config),
            default => new GeminiStrategy($providers[config('ai.default')]),
        };
    }
}
