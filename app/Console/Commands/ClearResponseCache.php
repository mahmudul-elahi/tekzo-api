<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Cache\ResponseCache;
use Illuminate\Console\Command;

class ClearResponseCache extends Command
{
    protected $signature = 'app:clear-response-cache';

    protected $description = 'Flush cached public API responses (homepage, settings, categories, brands)';

    public function handle(ResponseCache $responseCache): void
    {
        $responseCache->flush();

        $this->info('Response cache flushed.');
    }
}
