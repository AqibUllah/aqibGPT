<?php

namespace App\Services\AI\Strategies;

use App\Services\AI\Contracts\ChatModelStrategy;
use Cloudstudio\Ollama\Facades\Ollama;
use Cloudstudio\Ollama\Ollama as OllamaClient;
use GuzzleHttp\Psr7\Response;

class OllamaStrategy implements ChatModelStrategy
{
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function respond(string $prompt, array $context = [], array $options = [], array $attachments = []): array
    {
        $model = $this->config['model'] ?? config('ollama-laravel.model', 'deepseek-r1:1.5b');
        $systemPrompt = $options['system_prompt']
            ?? $this->config['system_prompt']
            ?? config('ollama-laravel.default_prompt', 'You are a helpful assistant.');

        $generationOptions = $this->resolveGenerationOptions($options);
        $finalPrompt = $this->buildPromptWithContext($prompt, $context);
        $stream = ($options['stream'] ?? false) === true;
        $onChunk = $options['on_chunk'] ?? null;

        $agent = Ollama::agent($systemPrompt)
            ->prompt($finalPrompt)
            ->images($attachments)
            ->model($model);

        if (!empty($generationOptions)) {
            $agent->options($generationOptions);
        }

        if ($stream) {
            $response = $agent->stream(true)->ask();
            return $this->handleStreamResponse($response, $model, $onChunk);
        }

        $response = $agent->ask();

        return [
            'model' => $model,
            'content' => is_array($response) ? ($response['response'] ?? '') : (string) $response,
        ];
    }

    protected function handleStreamResponse($response, string $model, ?callable $onChunk): array
    {
        $buffer = '';

        if (!$response instanceof Response) {
            return [
                'model' => $model,
                'content' => is_array($response) ? ($response['response'] ?? '') : (string) $response,
            ];
        }

        OllamaClient::processStream($response->getBody(), function ($data) use (&$buffer, $onChunk) {
            if (!isset($data['response'])) {
                return;
            }

            $text = (string) $data['response'];
            if ($text === '') {
                return;
            }

            $buffer .= $text;
            if (is_callable($onChunk)) {
                $onChunk($text);
            }
        });

        return [
            'model' => $model,
            'content' => $buffer,
        ];
    }

    protected function resolveGenerationOptions(array $options): array
    {
        $overrides = $options['options'] ?? [];
        $defaults = $this->config['options'] ?? [];

        return array_filter(array_merge($defaults, $overrides), fn ($value) => $value !== null);
    }

    protected function buildPromptWithContext(string $prompt, array $context): string
    {
        $segments = [];

        foreach ($context as $message) {
            $role = ucfirst((string) ($message['role'] ?? 'user'));
            $content = trim((string) ($message['content'] ?? ''));

            if ($content !== '') {
                $segments[] = "[{$role}] {$content}";
            }
        }

        $segments[] = $prompt;

        return trim(implode(PHP_EOL . PHP_EOL, $segments));
    }
}

