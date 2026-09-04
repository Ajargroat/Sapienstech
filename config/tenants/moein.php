<?php

/*
|--------------------------------------------------------------------------
| Tenant: moein
|--------------------------------------------------------------------------
|
| Only the keys that differ from config/theme.php belong here; anything
| unset inherits the baseline. Add the tenant's own advisor, numbers,
| articles and footer copy under `public` as it becomes available.
|
*/

return [

    'tenant' => [
        'name'       => 'مؤسسه معین',
        'short_name' => 'مؤسسه معین',
    ],

    'theme' => [
        'archetype' => 'editorial_serif',
    ],

    'public' => [
        'landing' => [
            'hero' => [
                'title_line2' => 'مؤسسه معین',
            ],
        ],
    ],

];

