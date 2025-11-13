<?php

namespace App\Services\AI\Strategies;

use App\Services\AI\Contracts\ChatModelStrategy;
use Cloudstudio\Ollama\Facades\Ollama;

class AiStudioStrategy implements ChatModelStrategy
{
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function respond(string $prompt, array $context = [], array $options = []): array
    {
        $model = $this->config['model'] ?? config('ollama-laravel.model', 'llama3.1');
        $response = Ollama::agent('You are a helpful assistant.')
            ->prompt($prompt)
            ->model($model)
            ->options($options)
            ->ask();

        return [
            'model' => $model,
            'content' => is_array($response) ? ($response['response'] ?? '') : (string)$response,
        ];
    }
}
