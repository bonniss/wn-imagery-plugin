<?php namespace Bonniss26\Imagery;

use System\Classes\PluginBase;

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
            'homepage' => 'https://github.com/bonniss26/wn-imagery-plugin',
        ];
    }
}
