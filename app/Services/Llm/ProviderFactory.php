<?php

namespace App\Services\Llm;

use App\Contracts\LlmProvider;
use InvalidArgumentException;

/**
 * Resolves the active chat / embedding providers from config. The chat and embed
 * providers are chosen independently so embeddings can stay local even when chat
 * is routed to a hosted model.
 */
class ProviderFactory
{
    public function make(string $name): LlmProvider
    {
        return match ($name) {
            'ollama' => new OllamaProvider(config('kardiorag.ollama')),
            'claude' => new ClaudeProvider(config('kardiorag.claude')),
            default  => throw new InvalidArgumentException("Unknown LLM provider [$name]"),
        };
    }

    public function chat(): LlmProvider
    {
        return $this->make(config('kardiorag.chat_provider', 'ollama'));
    }

    public function embed(): LlmProvider
    {
        return $this->make(config('kardiorag.embed_provider', 'ollama'));
    }
}
