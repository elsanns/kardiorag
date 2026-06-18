<?php

namespace App\Services\Llm;

use App\Contracts\LlmProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Hosted OpenAI (GPT) chat provider — the default cloud option. Chat-only: embeddings
 * stay on the local provider. Fails loud at construction if the key is missing, so an
 * opt-in to cloud can never silently fall back to local.
 */
class OpenAiProvider implements LlmProvider
{
    public function __construct(private array $config)
    {
        if (empty($this->config['api_key'])) {
            throw new RuntimeException('OPENAI_API_KEY is not set but cloud provider "openai" was selected.');
        }
    }

    public function name(): string
    {
        return 'openai';
    }

    public function embed(array $texts): array
    {
        throw new RuntimeException('Cloud tier is chat-only; embeddings stay on the local provider.');
    }

    public function chat(string $system, string $user, array $options = []): array
    {
        $res = Http::timeout($this->config['timeout'])
            ->withToken($this->config['api_key'])
            ->acceptJson()
            ->post($this->config['base_url'] . '/v1/chat/completions', [
                'model'       => $this->config['chat_model'],
                'temperature' => $options['temperature'] ?? 0.1,
                'max_tokens'  => $options['max_tokens'] ?? 400,
                'messages'    => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user',   'content' => $user],
                ],
            ]);

        if (! $res->successful()) {
            throw new RuntimeException('OpenAI generation failed: ' . $res->body());
        }

        return [
            'text'              => trim((string) $res->json('choices.0.message.content')),
            'prompt_tokens'     => $res->json('usage.prompt_tokens'),
            'completion_tokens' => $res->json('usage.completion_tokens'),
        ];
    }
}
