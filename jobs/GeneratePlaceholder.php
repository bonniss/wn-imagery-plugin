<?php namespace Bonniss26\Imagery\Jobs;

use Bonniss26\Imagery\Classes\Imagery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Generates one placeholder off the request.
 *
 * Carries only strings and scalars - a Source key and the resolved options -
 * so it serializes cleanly and re-resolves the image in the worker. The
 * options are the ones in force when the page rendered, so the job writes the
 * entry that page will look up next time even if settings change meanwhile.
 */
class GeneratePlaceholder implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

    /** Failures are recorded and backed off by Imagery itself; a queue retry would only double up. */
    public $tries = 1;

    /** @var string */
    public $sourceKey;

    /** @var array */
    public $options;

    public function __construct(string $sourceKey, array $options)
    {
        $this->sourceKey = $sourceKey;
        $this->options = $options;
    }

    public function handle(Imagery $imagery): void
    {
        $imagery->warm($this->sourceKey, $this->options, false, true);
    }
}
