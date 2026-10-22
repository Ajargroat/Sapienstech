<?php

/*
|--------------------------------------------------------------------------
| Tenant: agentation-scratch
|--------------------------------------------------------------------------
|
| Only the keys that differ from config/theme.php belong here; anything
| unset inherits the baseline. Add the tenant's own advisor, numbers,
| articles and footer copy under `public` as it becomes available.
|
*/

return [

    'tenant' => [
        'name'       => 'SP Agentation Scratch',
        'short_name' => 'SP Agentation Scratch',
    ],

    'theme' => [
        'archetype' => 'aurora_glass',
    ],

    'public' => [
        'landing' => [
            'hero' => [
                'title_line2' => 'SP Agentation Scratch',
            ],
        ],
    ],

];

