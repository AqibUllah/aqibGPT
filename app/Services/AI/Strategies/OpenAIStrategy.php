<?php

namespace App\Services\AI\Strategies;

use App\Services\AI\Contracts\ChatModelStrategy;
use OpenAI\Laravel\Facades\OpenAI;

class OpenAIStrategy implements ChatModelStrategy
{
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function respond(string $prompt, array $context = [], array $options = []): array
    {
        $model = $this->config['model'] ?? 'gpt-4o-mini';
        $response = OpenAI::responses()->create(array_merge([
            'model' => $model,
            'input' => $prompt,
        ], $options));

        return [
            'model' => $model,
            'content' => $response->outputText,
        ];
    }
}
