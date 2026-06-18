<?php

namespace App\Contracts;

/**
 * A pluggable LLM backend. Implementations keep the data-egress boundary explicit:
 * OllamaProvider runs fully on-prem; ClaudeProvider calls an approved hosted model.
 * The rest of the app depends only on this interface.
 */
interface LlmProvider
{
    /** Short identifier, e.g. "ollama" or "claude". */
    public function name(): string;

    /**
     * Embed one or more texts.
     *
     * @param  list<string>  $texts
     * @return list<list<float>>  one vector per input text
     */
    public function embed(array $texts): array;

    /**
     * Generate a chat completion.
     *
     * @return array{text: string, prompt_tokens: ?int, completion_tokens: ?int}
     */
    public function chat(string $system, string $user, array $options = []): array;
}
