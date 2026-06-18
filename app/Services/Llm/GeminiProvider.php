<?php

namespace App\Services\Llm;

use App\Contracts\LlmProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Hosted Google Gemini chat provider (opt-in cloud option). Chat-only: embeddings stay
 * on the local provider. Authenticates via the x-goog-api-key header (keeps the key out
 * of the URL). Fails loud at construction if the key is missing.
 */
class GeminiProvider implements LlmProvider
{
    public function __construct(private array $config)
    {
        if (empty($this->config['api_key'])) {
            throw new RuntimeException('GEMINI_API_KEY is not set but cloud provider "gemini" was selected.');
        }
    }

    public function name(): string
    {
        return 'gemini';
    }

    public function embed(array $texts): array
    {
        throw new RuntimeException('Cloud tier is chat-only; embeddings stay on the local provider.');
    }

    public function chat(string $system, string $user, array $options = []): array
    {
        $model = $this->config['chat_model'];

        $res = Http::timeout($this->config['timeout'])
            ->withHeaders(['x-goog-api-key' => $this->config['api_key']])
            ->acceptJson()
            ->post($this->config['base_url'] . "/v1beta/models/{$model}:generateContent", [
                'systemInstruction' => ['parts' => [['text' => $system]]],
                'contents'          => [['role' => 'user', 'parts' => [['text' => $user]]]],
                'generationConfig'  => [
                    'temperature'     => $options['temperature'] ?? 0.1,
                    'maxOutputTokens' => $options['max_tokens'] ?? 400,
                ],
            ]);

        if (! $res->successful()) {
            throw new RuntimeException('Gemini generation failed: ' . $res->body());
        }

        $text = collect($res->json('candidates.0.content.parts', []))
            ->pluck('text')
            ->implode('');

        return [
            'text'              => trim($text),
            'prompt_tokens'     => $res->json('usageMetadata.promptTokenCount'),
            'completion_tokens' => $res->json('usageMetadata.candidatesTokenCount'),
        ];
    }
}
