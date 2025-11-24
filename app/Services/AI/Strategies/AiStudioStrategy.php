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
        $stream = ($options['stream'] ?? false) === true;
        $onChunk = $options['on_chunk'] ?? null;

        if ($stream) {
            $buffer = '';
            $resp = Ollama::agent('You are a helpful assistant.')
                ->prompt($prompt)
                ->model($model)
                ->options($options)
                ->stream(true)
                ->ask();

            if ($resp instanceof \GuzzleHttp\Psr7\Response) {
                \Cloudstudio\Ollama\Ollama::processStream($resp->getBody(), function ($data) use (&$buffer, $onChunk) {
                    if (isset($data['response'])) {
                        $text = (string) $data['response'];
                        if ($text !== '') {
                            $buffer .= $text;
                            if (is_callable($onChunk)) {
                                $onChunk($text);
                            }
                        }
                    }
                });
                return [
                    'model' => $model,
                    'content' => $buffer,
                ];
            }
        }

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
