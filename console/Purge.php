<?php namespace Bonniss26\Imagery\Console;

use Bonniss26\Imagery\Classes\CachedPlaceholder;
use Bonniss26\Imagery\Classes\Imagery;
use Bonniss26\Imagery\Models\Placeholder;
use Illuminate\Console\Command;

/**
 * Removes cache entries. Nothing here is ever needed for correctness - options
 * are part of the cache key, so a settings change takes effect on the next
 * render regardless - it only reclaims space and resets failures.
 */
class Purge extends Command
{
    protected $signature = 'imagery:purge
        {--failed : Entries recorded as failed, so they are retried on the next request}
        {--stale : Entries written under options no longer in force}
        {--orphaned : Entries whose image no longer exists}
        {--all : Every entry}';

    protected $description = 'Delete cached LQIP placeholders.';

    public function handle(Imagery $imagery): int
    {
        if ($this->option('all')) {
            $deleted = Placeholder::query()->delete();
            $this->info("Deleted {$deleted} entries.");

            return 0;
        }

        if (!$this->option('failed') && !$this->option('stale') && !$this->option('orphaned')) {
            $this->error('Choose what to purge: --failed, --stale, --orphaned or --all.');

            return 1;
        }

        $deleted = 0;

        if ($this->option('failed')) {
            $deleted += Placeholder::where('status', CachedPlaceholder::STATUS_FAILED)->delete();
        }

        if ($this->option('stale') || $this->option('orphaned')) {
            $doomed = [];

            foreach (Placeholder::query()->select(['id', 'cache_key', 'source'])->cursor() as $row) {
                if ($this->option('orphaned') && !$imagery->sourceExists($row->source)) {
                    $doomed[] = $row->id;
                } elseif ($this->option('stale') && !in_array($row->cache_key, $imagery->currentKeysFor($row->source), true)) {
                    $doomed[] = $row->id;
                }
            }

            foreach (array_chunk($doomed, 500) as $ids) {
                $deleted += Placeholder::whereIn('id', $ids)->delete();
            }
        }

        $this->info("Deleted {$deleted} entries.");

        return 0;
    }
}
