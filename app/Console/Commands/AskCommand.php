<?php

namespace App\Console\Commands;

use App\Services\Rag\RagService;
use Illuminate\Console\Command;

class AskCommand extends Command
{
    protected $signature = 'kardiorag:ask {question* : The question to ask}';

    protected $description = 'Ask the cardiology RAG assistant a question (CLI)';

    public function handle(RagService $rag): int
    {
        $question = implode(' ', $this->argument('question'));

        $this->info("Q: {$question}");
        $this->line('Thinking (provider: ' . config('kardiorag.chat_provider') . ') ...');
        $this->newLine();

        $result = $rag->ask($question);

        $this->line($result['answer']);
        $this->newLine();

        if (! empty($result['sources'])) {
            $this->comment('Sources:');
            foreach ($result['sources'] as $s) {
                $this->line("  [{$s['n']}] {$s['drug_brand']} — {$s['title']} (dist {$s['distance']})");
            }
        }

        $this->newLine();
        $this->line("provider={$result['provider']}  latency={$result['latency_ms']}ms  query_id={$result['query_id']}");

        return self::SUCCESS;
    }
}
