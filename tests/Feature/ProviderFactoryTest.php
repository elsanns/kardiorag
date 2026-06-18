<?php

namespace Tests\Feature;

use App\Services\Llm\OllamaProvider;
use App\Services\Llm\ProviderFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ProviderFactoryTest extends TestCase
{
    public function test_local_mode_resolves_to_ollama(): void
    {
        config(['kardiorag.chat.mode' => 'local']);
        $f = new ProviderFactory();

        $this->assertSame('ollama', $f->activeChatName());
        $this->assertInstanceOf(OllamaProvider::class, $f->chat());
        $this->assertStringContainsString('local · ollama', $f->activeChatLabel());
    }

    public function test_embeddings_always_resolve_to_local(): void
    {
        $this->assertInstanceOf(OllamaProvider::class, (new ProviderFactory())->embed());
    }

    public static function cloudProviders(): array
    {
        return [['openai'], ['gemini'], ['anthropic']];
    }

    #[DataProvider('cloudProviders')]
    public function test_cloud_provider_fails_loud_without_a_key(string $provider): void
    {
        config([
            'kardiorag.chat.mode' => 'cloud',
            'kardiorag.chat.cloud_provider' => $provider,
            "kardiorag.providers.$provider.api_key" => '',
        ]);
        $f = new ProviderFactory();

        $this->assertSame($provider, $f->activeChatName());
        $this->expectException(RuntimeException::class);
        $f->chat(); // constructing the cloud provider must throw, never silently downgrade
    }
}
