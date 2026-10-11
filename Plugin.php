<?php namespace Bonniss26\Imagery;

use App;
use Backend\Models\UserRole;
use Bonniss26\Imagery\Classes\DatabaseStore;
use Bonniss26\Imagery\Classes\Imagery;
use Bonniss26\Imagery\Classes\OptionResolver;
use Bonniss26\Imagery\Classes\RequestBudget;
use Bonniss26\Imagery\Classes\RetryPolicy;
use Bonniss26\Imagery\Classes\SourceResolver;
use Bonniss26\Imagery\Classes\WinterMediaStorage;
use Bonniss26\Imagery\Jobs\GeneratePlaceholder;
use Bonniss26\Imagery\Models\Settings;
use Config;
use Illuminate\Foundation\AliasLoader;
use Queue;
use System\Classes\PluginBase;
use System\Models\File;

/**
 * Imagery - LQIP image placeholders for Winter CMS.
 *
 * Wraps bonniss26/lqip, which does the actual pixel work and knows nothing
 * about Winter. Everything Winter-shaped lives here: resolving whatever a
 * template hands us into an image, caching the result, and exposing it to
 * Twig and to PHP.
 */
class Plugin extends PluginBase
{
    public function pluginDetails(): array
    {
        return [
            'name' => 'bonniss26.imagery::lang.plugin.name',
            'description' => 'bonniss26.imagery::lang.plugin.description',
            'author' => 'bonniss26',
            'icon' => 'icon-picture-o',
            'homepage' => 'https://github.com/bonniss/wn-imagery-plugin',
        ];
    }

    public function register(): void
    {
        // One instance per request: the request budget and the set of keys
        // already queued must span every filter call on the page.
        App::singleton(Imagery::class, function () {
            return self::makeImagery();
        });

        $this->registerConsoleCommand('imagery.warm', Console\Warm::class);
        $this->registerConsoleCommand('imagery.purge', Console\Purge::class);
    }

    public function boot(): void
    {
        if (Config::get('bonniss26.imagery::register_alias', true)) {
            AliasLoader::getInstance()->alias('Imagery', Facades\Imagery::class);
        }
    }

    public function registerMarkupTags(): array
    {
        return [
            'filters' => [
                // {{ photo|lqip }} - a value for a background-image list, or "".
                'lqip' => function ($image, array $options = []) {
                    return App::make(Imagery::class)->css($image, $options);
                },
                // {{ photo|lqipStyle }} - a whole declaration for a style attribute.
                'lqipStyle' => function ($image, array $options = []) {
                    return App::make(Imagery::class)->style($image, $options);
                },
                // {{ (photo|lqipSize).width }} - the displayed size, or null.
                'lqipSize' => function ($image, array $options = []) {
                    return App::make(Imagery::class)->dimensions($image, $options);
                },
            ],
        ];
    }

    public function registerPermissions(): array
    {
        return [
            'bonniss26.imagery.manage_settings' => [
                'tab' => 'bonniss26.imagery::lang.plugin.name',
                'label' => 'bonniss26.imagery::lang.permissions.manage_settings',
                'roles' => [UserRole::CODE_DEVELOPER, UserRole::CODE_PUBLISHER],
            ],
        ];
    }

    public function registerSettings(): array
    {
        return [
            'settings' => [
                'label' => 'bonniss26.imagery::lang.settings.label',
                'description' => 'bonniss26.imagery::lang.settings.description',
                'category' => 'system::lang.system.categories.cms',
                'icon' => 'icon-picture-o',
                'class' => Settings::class,
                'keywords' => 'lqip placeholder blur image imagery',
                'permissions' => ['bonniss26.imagery.manage_settings'],
            ],
        ];
    }

    /**
     * Builds the service from config and settings. Public so anything running
     * outside a request - a queued job, a console command - assembles exactly
     * the same thing.
     */
    public static function makeImagery(): Imagery
    {
        $get = function (string $key, $default = null) {
            return Config::get("bonniss26.imagery::{$key}", $default);
        };

        // null means "follow app.debug": generate inline while developing so
        // things just work, and rely on imagery:warm in production.
        $lazy = $get('lazy');
        if ($lazy === null) {
            $lazy = (bool) Config::get('app.debug', false);
        }

        return new Imagery(
            new OptionResolver(
                Settings::generationOptions(),
                (array) $get('generation', []),
                (bool) $get('settings_override_config', true)
            ),
            new SourceResolver(
                new WinterMediaStorage(),
                function (int $id) {
                    return File::find($id);
                },
                (array) $get('sources', []),
                $get('cache.remote_identity', 'full') !== 'path_only'
            ),
            new DatabaseStore(),
            new RequestBudget((int) $get('request_budget', 5)),
            RetryPolicy::fromConfig((array) $get('cache.retry', [])),
            (bool) $get('enabled', true),
            (bool) $lazy,
            self::queueDispatcher($get('queue.connection'))
        );
    }

    /**
     * A dispatcher only when the queue really is asynchronous. On a `sync`
     * connection - the Winter default, and what most shared hosting ends up
     * with - "queueing" would just generate inline with no budget at all, so
     * return null and let the budgeted inline path handle it.
     */
    private static function queueDispatcher(?string $connection): ?\Closure
    {
        $connection = $connection ?: Config::get('queue.default');
        $driver = Config::get("queue.connections.{$connection}.driver");

        if (!$driver || $driver === 'sync') {
            return null;
        }

        return function (string $sourceKey, array $options) use ($connection) {
            try {
                Queue::connection($connection)->push(new GeneratePlaceholder($sourceKey, $options));
            } catch (\Throwable $e) {
                // An unreachable queue costs this image its placeholder for now,
                // and nothing more.
            }
        };
    }
}
