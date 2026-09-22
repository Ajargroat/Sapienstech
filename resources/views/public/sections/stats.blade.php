{{-- resources/views/public/sections/stats.blade.php — variant dispatcher --}}
@php
    $cfg = $L['stats'] ?? [];

    // roster = live tenant metrics (see App\Support\TenantRoster): the same
    // config rows, with `value` read from the tenant's own data. Rows whose
    // metric resolves to zero drop out, so a tenant never shows an empty "۰".
    if (($cfg['source'] ?? 'manual') === 'roster') {
        $visible = array_values(array_filter($cfg['items'] ?? [], static fn ($i) => $i['visible'] ?? true));
        $rows    = \App\Support\TenantRoster::resolveMetrics($visible, tenant());

        $cfg['items'] = array_map(static fn (array $r): array => [
            'value'    => $r['value'],
            'label'    => $r['label'],
            'suffix'   => $visible[$r['path']]['suffix'] ?? '',
            'gradient' => $visible[$r['path']]['gradient'] ?? false,
            'visible'  => true,
        ], $rows);
    }
@endphp

@include('public.sections._dispatch', [
    'name'    => 'stats',
    'variant' => $cfg['variant'] ?? 'default',
    'cfg'     => $cfg,
    'L'       => $L,
    'anim'    => $anim,
])
