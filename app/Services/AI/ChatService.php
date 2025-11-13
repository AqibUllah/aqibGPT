<?php

namespace App\Services\AI;

use App\Services\AI\Contracts\ChatModelStrategy;

class ChatService
{
    protected ChatModelStrategy $strategy;

    public function __construct(?string $provider = null)
    {
        $provider = $provider ?: session('ai_model', config('ai.default'));
        $this->strategy = ChatModelFactory::make($provider);
    }

    public function respond(string $prompt, array $context = [], array $options = []): array
    {
        return $this->strategy->respond($prompt, $context, $options);
    }
}
