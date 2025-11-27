<?php

namespace App\Services\AI\Contracts;

interface ChatModelStrategy
{
    public function respond(string $prompt, array $context = [], array $options = [], array $attachments = []): array;
}
