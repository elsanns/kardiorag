<?php

namespace App\Services\Llm;

use App\Contracts\LlmProvider;
use InvalidArgumentException;

/**
 * Resolves the active chat / embedding providers from config.
 *
 * Chat is two-tier: mode=local always uses Ollama (the safe on-prem default); mode=cloud
 * is an explicit opt-in routed to one configurable cloud provider (default openai/gpt).
 * Embeddings are a separate axis and stay local. Misconfigured cloud providers fail loud
 * (their constructor throws) rather than silently downgrading to local.
 */
class ProviderFactory
{
    public function make(string $name): LlmProvider
    {
        return match ($name) {
            'ollama'    => new OllamaProvider(config('kardiorag.ollama')),
            'openai'    => new OpenAiProvider(config('kardiorag.providers.openai')),
            'gemini'    => new GeminiProvider(config('kardiorag.providers.gemini')),
            'anthropic' => new ClaudeProvider(config('kardiorag.providers.anthropic')),
            default     => throw new InvalidArgumentException("Unknown LLM provider [$name]"),
        };
    }

    /** Resolved chat provider name from the two-tier config. */
    public function activeChatName(): string
    {
        return config('kardiorag.chat.mode', 'local') === 'cloud'
            ? config('kardiorag.chat.cloud_provider', 'openai')
            : 'ollama';
    }

    public function chat(): LlmProvider
    {
        return $this->make($this->activeChatName());
    }

    public function embed(): LlmProvider
    {
        return $this->make(config('kardiorag.embed_provider', 'ollama'));
    }

    /** Human label for the active chat provider, e.g. "local · ollama (llama3.2:3b)". */
    public function activeChatLabel(): string
    {
        $name  = $this->activeChatName();
        $tier  = config('kardiorag.chat.mode', 'local') === 'cloud' ? 'cloud' : 'local';
        $model = $name === 'ollama'
            ? config('kardiorag.ollama.chat_model')
            : config("kardiorag.providers.$name.chat_model");

        return "{$tier} · {$name} ({$model})";
    }
}
