<?php

namespace App\Services\Llm;

use App\Contracts\LlmProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Hosted provider (Anthropic Claude) for heavier reasoning, behind the same interface
 * and the same audit/guardrail wrapper as the local provider. Routing here is an explicit,
 * logged decision — it crosses the on-prem boundary, so it is opt-in via config.
 *
 * Note: Claude has no embeddings endpoint, so embeddings always stay on the local provider.
 */
class ClaudeProvider implements LlmProvider
{
    public function __construct(private array $config)
    {
        if (empty($this->config['api_key'])) {
            throw new RuntimeException('ANTHROPIC_API_KEY is not set but cloud provider "anthropic" was selected.');
        }
    }

    public function name(): string
    {
        return 'anthropic';
    }

    public function embed(array $texts): array
    {
        throw new RuntimeException('Cloud tier is chat-only; embeddings stay on the local provider.');
    }

    public function chat(string $system, string $user, array $options = []): array
    {
        $res = Http::timeout($this->config['timeout'])
            ->withHeaders([
                'x-api-key'         => $this->config['api_key'],
                'anthropic-version' => $this->config['version'],
            ])
            ->acceptJson()
            ->post($this->config['base_url'] . '/v1/messages', [
                'model'      => $this->config['chat_model'],
                'max_tokens' => $options['max_tokens'] ?? 400,
                'temperature'=> $options['temperature'] ?? 0.1,
                'system'     => $system,
                'messages'   => [
                    ['role' => 'user', 'content' => $user],
                ],
            ]);

        if (! $res->successful()) {
            throw new RuntimeException('Claude generation failed: ' . $res->body());
        }

        $text = collect($res->json('content', []))
            ->where('type', 'text')
            ->pluck('text')
            ->implode('');

        return [
            'text'              => trim($text),
            'prompt_tokens'     => $res->json('usage.input_tokens'),
            'completion_tokens' => $res->json('usage.output_tokens'),
        ];
    }
}
