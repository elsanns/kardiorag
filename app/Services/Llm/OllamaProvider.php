<?php

namespace App\Services\Llm;

use App\Contracts\LlmProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Local, on-premise provider. No data leaves the server — the default for sensitive
 * workloads in a regulated (NIS2/KSC) deployment.
 */
class OllamaProvider implements LlmProvider
{
    public function __construct(private array $config) {}

    public function name(): string
    {
        return 'ollama';
    }

    public function embed(array $texts): array
    {
        $vectors = [];
        foreach ($texts as $text) {
            $res = Http::timeout($this->config['timeout'])
                ->acceptJson()
                ->post($this->config['base_url'] . '/api/embeddings', [
                    'model'  => $this->config['embed_model'],
                    'prompt' => $text,
                ]);

            if (! $res->successful() || ! is_array($res->json('embedding'))) {
                throw new RuntimeException('Ollama embedding failed: ' . $res->body());
            }
            $vectors[] = $res->json('embedding');
        }

        return $vectors;
    }

    public function chat(string $system, string $user, array $options = []): array
    {
        $res = Http::timeout($this->config['timeout'])
            ->acceptJson()
            ->post($this->config['base_url'] . '/api/generate', [
                'model'   => $this->config['chat_model'],
                'system'  => $system,
                'prompt'  => $user,
                'stream'  => false,
                'options' => [
                    'temperature' => $options['temperature'] ?? 0.1,
                    'num_predict' => $options['max_tokens'] ?? 400,
                ],
            ]);

        if (! $res->successful()) {
            throw new RuntimeException('Ollama generation failed: ' . $res->body());
        }

        return [
            'text'              => trim((string) $res->json('response')),
            'prompt_tokens'     => $res->json('prompt_eval_count'),
            'completion_tokens' => $res->json('eval_count'),
        ];
    }
}
