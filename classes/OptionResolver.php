<?php namespace Bonniss26\Imagery\Classes;

/**
 * Coalesces generation options across their sources, at runtime, every time.
 *
 * Precedence, highest first: per-call overrides (a Twig filter argument), then
 * the backend settings and the config file - in whichever order
 * `settings_override_config` says - then the library's own defaults. A null or
 * an empty string means "not set here", so a field an editor blanked falls
 * through to the next source instead of reaching the generator as "".
 *
 * Values that are set but invalid - a mode nobody registered, a driver that is
 * not installed - are dropped rather than passed on, so one bad settings value
 * degrades to the default instead of turning every placeholder off.
 *
 * Pure: takes arrays, returns an array. Winter is the caller's concern.
 */
final class OptionResolver
{
    /** @var array */
    private $settings;

    /** @var array */
    private $config;

    /** @var bool */
    private $settingsOverrideConfig;

    public function __construct(array $settings, array $config, bool $settingsOverrideConfig = true)
    {
        $this->settings = $settings;
        $this->config = $config;
        $this->settingsOverrideConfig = $settingsOverrideConfig;
    }

    /**
     * @param array $overrides Per-call options; anything outside
     *                         Defaults::GENERATION_KEYS is ignored.
     * @return array<string,mixed> Exactly Defaults::GENERATION_KEYS, all set.
     */
    public function resolve(array $overrides = []): array
    {
        $layers = $this->settingsOverrideConfig
            ? [$overrides, $this->settings, $this->config]
            : [$overrides, $this->config, $this->settings];

        $resolved = [];
        foreach (Defaults::generation() as $key => $default) {
            $resolved[$key] = $default;

            foreach ($layers as $layer) {
                $value = self::normalize($key, $layer[$key] ?? null);
                if ($value !== null) {
                    $resolved[$key] = $value;
                    break;
                }
            }
        }

        return $resolved;
    }

    /**
     * The value cast to what the library expects, or null if it is unset or
     * unusable - which makes the caller fall through to the next layer.
     *
     * @param mixed $value
     * @return mixed
     */
    private static function normalize(string $key, $value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        switch ($key) {
            case 'mode':
                return in_array($value, Defaults::modes(), true) ? $value : null;

            case 'format':
                return in_array($value, Defaults::formats(), true) ? $value : null;

            case 'driver':
                return in_array($value, Defaults::drivers(), true) ? $value : null;

            case 'long_edge':
            case 'quality':
                // Range clamping is the library's job and it already does it; only
                // something that is not a number at all is rejected here.
                return is_numeric($value) ? (int) $value : null;

            case 'blur':
                // Settings dropdowns store "1"/"0"; config files use real booleans.
                $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

                return $bool;

            default:
                return $value;
        }
    }
}
