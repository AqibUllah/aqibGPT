<?php

namespace App\Services\AI\Strategies;

use App\Services\AI\Contracts\ChatModelStrategy;
use Gemini\Laravel\Facades\Gemini;

class GeminiStrategy implements ChatModelStrategy
{
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function respond(string $prompt, array $context = [], array $options = []): array
    {
        $model = $this->config['model'] ?? 'gemini-2.0-flash';
        $result = Gemini::generativeModel(model: $model)->generateContent($prompt);

        return [
            'model' => $model,
            'content' => method_exists($result, 'text') ? $result->text() : (string)($result['output_text'] ?? ''),
        ];
    }
}
