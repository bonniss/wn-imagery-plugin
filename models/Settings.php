<?php namespace Bonniss26\Imagery\Models;

use Bonniss26\Imagery\Classes\Defaults;
use Lang;
use Winter\Storm\Database\Model;

/**
 * Backend settings for generation options.
 *
 * Every field may be left blank, meaning "inherit": from the config file, and
 * from there the library. No field declares a `default:` - see
 * Classes\Defaults for why that mechanism silently goes stale - so each
 * dropdown's blank option instead names the value it currently inherits.
 *
 * Changing a value needs no purge: options are part of the cache key, so the
 * next render simply misses and regenerates. The superseded rows stay until
 * `imagery:purge --stale`, which is also what makes switching back free.
 */
class Settings extends Model
{
    public $implement = [\System\Behaviors\SettingsModel::class];

    public $settingsCode = 'bonniss26_imagery_settings';

    public $settingsFields = 'fields.yaml';

    /**
     * The generation options as stored, blanks as null. Read defensively: on a
     * fresh install, before migrations have run, there is no settings table.
     *
     * @return array<string,mixed>
     */
    public static function generationOptions(): array
    {
        try {
            $instance = static::instance();
        } catch (\Throwable $e) {
            return [];
        }

        $values = [];
        foreach (Defaults::GENERATION_KEYS as $key) {
            $value = $instance->{$key} ?? null;
            $values[$key] = ($value === '' ? null : $value);
        }

        return $values;
    }

    public function getModeOptions(): array
    {
        $options = $this->inheritOption('mode');
        foreach (Defaults::modes() as $mode) {
            // A mode registered by a third party has no label here; show its name.
            $key = "bonniss26.imagery::lang.modes.{$mode}";
            $options[$mode] = Lang::has($key) ? Lang::get($key) : $mode;
        }

        return $options;
    }

    public function getDriverOptions(): array
    {
        $options = $this->inheritOption('driver');
        foreach (Defaults::drivers() as $driver) {
            $options[$driver] = Lang::get("bonniss26.imagery::lang.drivers.{$driver}");
        }

        return $options;
    }

    public function getFormatOptions(): array
    {
        return $this->inheritOption('format') + ['webp' => 'WebP', 'jpg' => 'JPEG'];
    }

    public function getBlurOptions(): array
    {
        return $this->inheritOption('blur') + [
            '1' => Lang::get('bonniss26.imagery::lang.settings.yes'),
            '0' => Lang::get('bonniss26.imagery::lang.settings.no'),
        ];
    }

    /**
     * The blank choice, labelled with what it currently resolves to, so an
     * editor can see the effective value without leaving the form.
     */
    private function inheritOption(string $key): array
    {
        $config = (array) \Config::get('bonniss26.imagery::generation', []);
        $value = $config[$key] ?? Defaults::generation()[$key] ?? null;

        if (is_bool($value)) {
            $value = Lang::get('bonniss26.imagery::lang.settings.' . ($value ? 'yes' : 'no'));
        }

        return ['' => Lang::get('bonniss26.imagery::lang.settings.inherit', ['value' => (string) $value])];
    }
}
