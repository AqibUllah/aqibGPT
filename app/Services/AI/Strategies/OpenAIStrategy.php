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
        $stream = ($options['stream'] ?? false) === true;
        $onChunk = $options['on_chunk'] ?? null;

        if ($stream) {
            $buffer = '';
            $streamResponse = OpenAI::responses()->createStreamed([
                'model' => $model,
                'input' => $prompt,
            ]);

            foreach ($streamResponse as $event) {
                if ($event->event === 'response.output_text.delta') {
                    $text = $event->response->delta;
                    if ($text) {
                        $buffer .= $text;
                        if (is_callable($onChunk)) {
                            $onChunk($text);
                        }
                    }
                }
            }

            return [
                'model' => $model,
                'content' => $buffer,
            ];
        }

        $response = OpenAI::responses()->create([
            'model' => $model,
            'input' => $prompt,
        ]);

        return [
            'model' => $model,
            'content' => $response->outputText,
        ];
    }
}
