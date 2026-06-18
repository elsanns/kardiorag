<?php

namespace App\Console\Commands;

use App\Services\Llm\ProviderFactory;
use App\Services\Rag\RagService;
use Illuminate\Console\Command;

class AskCommand extends Command
{
    protected $signature = 'kardiorag:ask {question* : The question to ask}';

    protected $description = 'Ask the cardiology RAG assistant a question (CLI)';

    public function handle(RagService $rag, ProviderFactory $providers): int
    {
        $question = implode(' ', $this->argument('question'));

        $this->info("Q: {$question}");
        $this->line('Thinking (provider: ' . $providers->activeChatLabel() . ') ...');
        $this->newLine();

        $query = $rag->ask($question);

        if ($query->status === 'failed') {
            $this->error('Generation failed: ' . $query->error);
            return self::FAILURE;
        }

        $this->line($query->answer);
        $this->newLine();

        if (! empty($query->sources)) {
            $this->comment('Sources:');
            foreach ($query->sources as $s) {
                $this->line("  [{$s['n']}] {$s['drug_brand']} — {$s['title']} (dist {$s['distance']})");
            }
        }

        $this->newLine();
        $this->line("provider={$query->chat_provider}  latency={$query->latency_ms}ms  query_id={$query->id}");

        return self::SUCCESS;
    }
}
