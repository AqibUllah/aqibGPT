<?php

namespace App\Services\AI\Strategies;

use App\Services\AI\Contracts\ChatModelStrategy;
use Gemini\Laravel\Facades\Gemini;
use Gemini\Data\Blob;
use Gemini\Enums\MimeType;

class GeminiStrategy implements ChatModelStrategy
{
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function respond(string $prompt, array $context = [], array $options = [], array $attachments = []): array
    {
        $model = $this->config['model'] ?? 'gemini-2.0-flash';
        $stream = ($options['stream'] ?? false) === true;
        $onChunk = $options['on_chunk'] ?? null;

        if($attachments){
            $prompt = [$prompt];
            foreach ($attachments as $key => $attachment) {
                $prompt[] = new Blob(
                    mimeType: MimeType::IMAGE_JPEG,
                    data: base64_encode(
                        file_get_contents($attachment)
                    )
                );
            }

        }

        if ($stream) {
            $buffer = '';
            foreach (Gemini::generativeModel(model: $model)->streamGenerateContent($prompt) as $response) {
                $text = $response->text();
                if ($text) {
                    $buffer .= $text;
                    if (is_callable($onChunk)) {
                        $onChunk($text);
                    }
                }
            }
            return [
                'model' => $model,
                'content' => $buffer,
            ];
        }
        $result = Gemini::generativeModel(model: $model)->generateContent($prompt);
        return [
            'model' => $model,
            'content' => method_exists($result, 'text') ? $result->text() : (string)($result['output_text'] ?? ''),
        ];
    }

    /**
     * Stream response from Gemini.
     */
    public function streamRespond(string $prompt, callable $callback): void
    {
        $model = $this->config['model'] ?? 'gemini-2.0-flash';

        Gemini::generativeModel(model: $model)->streamGenerateContent($prompt, function ($chunk) use ($callback) {
            $text = $chunk->text();
            if ($text) {
                $callback($text);
            }
        });
    }
}
