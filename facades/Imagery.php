<?php namespace Bonniss26\Imagery\Facades;

use Winter\Storm\Support\Facade;

/**
 * Static access to Classes\Imagery from PHP: Imagery::css($file),
 * Imagery::style($file), Imagery::result($file).
 *
 * @method static string css(mixed $image, array $options = [])
 * @method static string style(mixed $image, array $options = [])
 * @method static \Bonniss26\Lqip\LqipResult|null result(mixed $image, array $options = [])
 * @method static string warm(mixed $image, array $options = [], bool $force = false, bool $isKey = false)
 *
 * @see \Bonniss26\Imagery\Classes\Imagery
 */
class Imagery extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Bonniss26\Imagery\Classes\Imagery::class;
    }
}
