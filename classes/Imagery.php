<?php namespace Bonniss26\Imagery\Classes;

use Bonniss26\Lqip\LqipResult;
use Bonniss26\Lqip\PlaceholderGenerator;
use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use Throwable;

/**
 * The plugin's front door: from "whatever a template handed us" to a cached
 * placeholder, or nothing.
 *
 * On a cache miss during a render there are three possible answers, decided
 * here and nowhere else:
 *
 * - a real queue is configured: push a job and return nothing for now;
 * - generating inline is allowed (`lazy`) and this request still has budget:
 *   generate, store, return;
 * - otherwise: return nothing and leave the image to `imagery:warm`.
 *
 * Every public method that runs during a render returns null or "" on any
 * failure. These are called from Twig, where an exception takes down the
 * whole page and a broken image must cost at most its placeholder.
 *
 * Winter-free by construction - the collaborators are injected - so the
 * branching above is covered by the standalone test suite.
 */
class Imagery
{
    /** @var OptionResolver */
    private $options;

    /** @var SourceResolver */
    private $sources;

    /** @var PlaceholderStore */
    private $store;

    /** @var RequestBudget */
    private $budget;

    /** @var RetryPolicy */
    private $retry;

    /** @var bool */
    private $enabled;

    /** @var bool */
    private $lazy;

    /** @var Closure(string, array): void|null Pushes a generation job; null when the queue is sync. */
    private $dispatch;

    /** @var Closure(): DateTimeInterface */
    private $now;

    /** @var array<string,bool> Cache keys already queued during this request. */
    private $dispatched = [];

    public function __construct(
        OptionResolver $options,
        SourceResolver $sources,
        PlaceholderStore $store,
        RequestBudget $budget,
        RetryPolicy $retry,
        bool $enabled = true,
        bool $lazy = true,
        ?Closure $dispatch = null,
        ?Closure $now = null
    ) {
        $this->options = $options;
        $this->sources = $sources;
        $this->store = $store;
        $this->budget = $budget;
        $this->retry = $retry;
        $this->enabled = $enabled;
        $this->lazy = $lazy;
        $this->dispatch = $dispatch;
        $this->now = $now ?: function () {
            return new DateTimeImmutable();
        };
    }

    /**
     * The value for a `background-image` list, or "" - the `lqip` filter.
     *
     * A configured mode that cannot produce CSS (css_only) is swapped for
     * Defaults::FALLBACK_CSS_MODE rather than failing: whoever wrote the
     * template asked for a background-image value, and an editor changing the
     * mode in the backend must not break that template.
     *
     * @param mixed $input
     */
    public function css($input, array $overrides = []): string
    {
        $options = $this->resolveOptions($overrides);
        if ($options === null) {
            return '';
        }

        if (!Defaults::isCssMode((string) $options['mode'])) {
            $overrides['mode'] = Defaults::FALLBACK_CSS_MODE;
        }

        $result = $this->result($input, $overrides);

        return $result ? (string) $result->toCss() : '';
    }

    /**
     * A whole declaration for a `style` attribute, or "" - the `lqipStyle`
     * filter. Works for every mode that has a CSS form, including css_only's
     * custom property; a hash mode (thumbhash) falls back as css() does.
     *
     * @param mixed $input
     */
    public function style($input, array $overrides = []): string
    {
        $options = $this->resolveOptions($overrides);
        if ($options === null) {
            return '';
        }

        if (!Defaults::isStyleMode((string) $options['mode'])) {
            $overrides['mode'] = Defaults::FALLBACK_CSS_MODE;
        }

        $result = $this->result($input, $overrides);

        return $result ? $result->toStyleAttribute() : '';
    }

    /**
     * The cached result for an image, generating or queueing it on a miss as
     * the class docblock describes. Null whenever there is nothing to show yet.
     *
     * @param mixed $input
     */
    public function result($input, array $overrides = []): ?LqipResult
    {
        if (!$this->enabled) {
            return null;
        }

        try {
            $source = $this->sources->resolve($input);
            $options = $this->resolveOptions($overrides);
            if ($source === null || $options === null) {
                return null;
            }

            $key = $this->cacheKey($source->key(), $options);
            $row = $this->store->find($key);

            if ($row !== null && $row->identity === $source->identity()) {
                if ($row->isOk()) {
                    return $row->toResult();
                }
                if ($row->isBackingOff(($this->now)())) {
                    return null;
                }
            }

            if ($this->dispatch !== null) {
                if (!isset($this->dispatched[$key])) {
                    $this->dispatched[$key] = true;
                    ($this->dispatch)($source->key(), $options);
                }

                return null;
            }

            if (!$this->lazy || !$this->budget->take()) {
                return null;
            }

            return $this->generate($source, $options, $row);
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Generates now and stores the outcome, ignoring `lazy`, the request budget
     * and the queue - for the warm command and for queued jobs.
     *
     * @param mixed $input A template value, or a Source key with $isKey.
     * @param bool $force Regenerate even if a valid entry exists.
     * @return string "generated", "cached", "failed" or "missing".
     */
    public function warm($input, array $overrides = [], bool $force = false, bool $isKey = false): string
    {
        try {
            $source = $isKey ? $this->sources->fromKey((string) $input) : $this->sources->resolve($input);
            $options = $this->resolveOptions($overrides);
            if ($source === null || $options === null) {
                return 'missing';
            }

            $key = $this->cacheKey($source->key(), $options);
            $row = $this->store->find($key);

            if (!$force && $row !== null && $row->identity === $source->identity() && $row->isOk()) {
                return 'cached';
            }

            return $this->generate($source, $options, $row) ? 'generated' : 'failed';
        } catch (Throwable $e) {
            return 'failed';
        }
    }

    /**
     * Every cache key the current configuration would look up for a source:
     * the configured mode, and - when that mode cannot produce CSS - the
     * fallback the `lqip` filter uses instead. A row under any other key was
     * written under settings no longer in force.
     *
     * @return string[]
     */
    public function currentKeysFor(string $sourceKey): array
    {
        $options = $this->resolveOptions();
        if ($options === null) {
            return [];
        }

        $keys = [$this->cacheKey($sourceKey, $options)];
        if (!Defaults::isCssMode((string) $options['mode'])) {
            $fallback = $this->resolveOptions(['mode' => Defaults::FALLBACK_CSS_MODE]);
            if ($fallback !== null) {
                $keys[] = $this->cacheKey($sourceKey, $fallback);
            }
        }

        return $keys;
    }

    /** Whether a stored Source key still points at a readable image. */
    public function sourceExists(string $sourceKey): bool
    {
        return $this->sources->fromKey($sourceKey) !== null;
    }

    /**
     * Options as the generator will receive them. Null only if resolving them
     * failed outright, which should not happen but must not throw either.
     */
    public function resolveOptions(array $overrides = []): ?array
    {
        try {
            return $this->options->resolve($overrides);
        } catch (Throwable $e) {
            return null;
        }
    }

    private function generate(Source $source, array $options, ?CachedPlaceholder $previous): ?LqipResult
    {
        $key = $this->cacheKey($source->key(), $options);
        $identity = $source->identity();
        $mode = (string) $options['mode'];

        $bytes = $source->bytes();
        $result = $bytes === null ? null : PlaceholderGenerator::resultForBytes($bytes, $options);

        if ($result !== null) {
            $this->store->saveResult($key, $source->key(), $identity, $mode, $result);

            return $result;
        }

        // A failure on a file that has since changed starts counting afresh.
        $attempts = ($previous !== null && $previous->identity === $identity && !$previous->isOk())
            ? $previous->attempts + 1
            : 1;

        $this->store->saveFailure(
            $key,
            $source->key(),
            $identity,
            $mode,
            $attempts,
            $this->retry->retryAt($attempts, ($this->now)())
        );

        return null;
    }

    private function cacheKey(string $sourceKey, array $options): string
    {
        $driver = PlaceholderGenerator::resolveDriver($options['driver'] ?? 'auto');
        $format = ($options['format'] ?? null) === 'webp' && $driver !== null && $driver->supportsFormat('webp')
            ? 'webp'
            : 'jpg';

        return CacheKey::make($sourceKey, $options, $format);
    }
}
