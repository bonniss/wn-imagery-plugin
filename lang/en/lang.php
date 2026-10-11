<?php

return [
    'plugin' => [
        'name' => 'Imagery',
        'description' => 'Low-quality image placeholders that render while the real photo loads.',
    ],
    'permissions' => [
        'manage_settings' => 'Manage image placeholder settings',
    ],
    'modes' => [
        'dominant_color' => 'Dominant colour - one flat tone, cheapest',
        'gradient' => 'Gradient - four colours, no image data',
        'blur' => 'Blurred miniature - most realistic, most bytes',
        'css_only' => 'CSS only (experimental) - needs a decode stylesheet',
        'thumbhash' => 'ThumbHash - a hash for APIs and apps that decode it, not CSS',
    ],
    'drivers' => [
        'auto' => 'Automatic (Imagick if installed, otherwise GD)',
        'gd' => 'GD',
        'imagick' => 'Imagick - faster on large JPEGs',
    ],
    'settings' => [
        'label' => 'Image placeholders',
        'description' => 'How the placeholder shown while an image loads is generated.',
        'inherit' => 'Inherit (currently: :value)',
        'yes' => 'Yes',
        'no' => 'No',
        'mode' => [
            'label' => 'Placeholder style',
            'comment' => 'The |lqip filter needs a value for background-image, so with "CSS only" it falls back to the dominant colour. |lqipStyle works with every style.',
        ],
        'driver' => [
            'label' => 'Image engine',
            'comment' => 'Only engines installed on this server are listed. Switching engine keeps existing placeholders.',
        ],
        'format' => [
            'label' => 'Image format (blur only)',
            'comment' => 'WebP is roughly 9x smaller than JPEG at this size. If the server cannot write WebP, JPEG is used automatically.',
        ],
        'blur' => [
            'label' => 'Blur the miniature (blur only)',
            'comment' => 'Softens it so it reads as a blur rather than a tiny mosaic. Also makes it smaller.',
        ],
        'long_edge' => [
            'label' => 'Miniature size in px (blur only)',
            'comment' => 'Length of the longer edge, 1-64. The default is already about as small as still looks good.',
        ],
        'quality' => [
            'label' => 'Encode quality (blur only)',
            'comment' => '0-100. After blurring there is little detail left to spend it on; raising it mostly adds bytes.',
        ],
        'smooth' => [
            'label' => 'Smooth when enlarged (blur only)',
            'comment' => 'Blurs once more at display size, removing the soft blocks. About 4x the size (~530 characters instead of ~130).',
        ],
        'background' => [
            'label' => 'Background for transparent images',
            'comment' => 'Leave blank to keep transparency: a logo gets a placeholder in its own colour and shape. Enter the page colour (e.g. #ffffff) to put images on it first.',
        ],
    ],
];
