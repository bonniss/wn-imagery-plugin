<?php namespace Bonniss26\Imagery\Console;

use Bonniss26\Imagery\Classes\Imagery;
use Bonniss26\Imagery\Classes\WinterMediaStorage;
use Illuminate\Console\Command;
use System\Models\File;

/**
 * Generates placeholders ahead of time, so pages never have to.
 *
 * The production counterpart of `lazy`: with lazy generation off, a cache miss
 * during a render returns nothing, and this is what fills the cache instead.
 * Safe to re-run - anything already cached and unchanged is skipped - so it
 * can sit in a scheduler or a deploy hook.
 */
class Warm extends Command
{
    protected $signature = 'imagery:warm
        {--limit=0 : Stop after generating this many placeholders (0 = no limit)}
        {--force : Regenerate even placeholders that are already cached}
        {--files : Also warm images uploaded as file attachments, not just the Media Library}';

    protected $description = 'Generate LQIP placeholders for Media Library images (and optionally file attachments).';

    public function handle(Imagery $imagery): int
    {
        $limit = max(0, (int) $this->option('limit'));
        $force = (bool) $this->option('force');
        $counts = ['generated' => 0, 'cached' => 0, 'failed' => 0, 'missing' => 0];

        $variants = $imagery->variants();

        foreach ($this->inputs() as $label => $input) {
            foreach ($variants as $overrides) {
                $outcome = $imagery->warm($input, $overrides, $force);
                $counts[$outcome]++;

                if ($outcome === 'failed') {
                    $this->warn("failed: {$label}");
                } elseif ($outcome === 'generated' && $this->getOutput()->isVerbose()) {
                    $this->line("generated: {$label}");
                }
            }

            if ($limit > 0 && $counts['generated'] >= $limit) {
                $this->info("Limit of {$limit} reached.");
                break;
            }
        }

        $this->info(sprintf(
            '%d generated, %d already cached, %d failed, %d unreadable.',
            $counts['generated'],
            $counts['cached'],
            $counts['failed'],
            $counts['missing']
        ));

        return 0;
    }

    /**
     * @return iterable<string,mixed> label => input for Imagery::warm()
     */
    private function inputs(): iterable
    {
        foreach ((new WinterMediaStorage())->images() as $path) {
            yield "media:{$path}" => $path;
        }

        if ($this->option('files')) {
            foreach (File::where('content_type', 'like', 'image/%')->cursor() as $file) {
                yield "file:{$file->id} ({$file->file_name})" => $file;
            }
        }
    }
}
