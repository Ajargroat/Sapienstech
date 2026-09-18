import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

export const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
export const CUSTOM_SETS = ['lucide', 'tabler', 'bi', 'la'];
export const SOURCE_SCOPES = ['resources/views', 'resources/js', 'config', 'app'];
// These are brand marks in the source, intentionally rendered by Font Awesome.
// Add new brands explicitly; do not exempt unknown names just because they use .fab.
const BRANDS = new Set(['instagram', 'telegram', 'x-twitter']);
// Persian date-formatting hooks, not Font Awesome glyphs.
const NON_ICONS = new Set(['date', 'weekday']);
const UTILITY = /^(?:solid|regular|brands|light|thin|duotone|sharp|classic|fw|fixed-width|xs|sm|lg|xl|2xs|2xl|[1-9]x|10x|w-\d+|ul|li|border|inverse|stack|stack-1x|stack-2x|pull-left|pull-right|rotate-(?:90|180|270|by)|flip(?:-horizontal|-vertical|-both)?|beat|bounce|fade|beat-fade|shake|spin(?:-pulse|-reverse)?|pulse|sr-only|sr-only-focusable)$/;
const has = (object, key) => Object.hasOwn(object ?? {}, key);

export function extractIconUsage(source, file = '<source>') {
    const used = new Map();
    const excluded = new Set();
    const add = (name, line) => {
        if (!used.has(name)) used.set(name, []);
        used.get(name).push(`${file}:${line}`);
    };
    // No required style prefix: classList toggles and isolated config strings count.
    // The boundary excludes data-fa-date/data-fa-weekday and other non-FA attributes.
    for (const hit of source.matchAll(/(?<![\w-])fa-([a-z0-9][a-z0-9-]*)/g)) {
        const name = hit[1];
        const line = source.slice(0, hit.index).split('\n').length;
        if (NON_ICONS.has(name)) excluded.add(`date-hook:fa-${name}`);
                else if (UTILITY.test(name)) excluded.add(`utility:fa-${name}`);
        else if (BRANDS.has(name)) excluded.add(`brand:fa-${name}`);
        else if (name === 'arrow-' && /^\{\{\s*\$rtl\s*\?\s*'right'\s*:\s*'left'\s*\}\}/.test(source.slice(hit.index + hit[0].length))) {
            // The only current partial class; both concrete runtime outcomes are guarded.
            excluded.add('dynamic:fa-arrow-');
            add('arrow-left', line);
            add('arrow-right', line);
        } else add(name, line);
    }
    return { used, excluded };
}

export function scanIconUsage(sourceRoot = root) {
    const used = new Map();
    const excluded = new Set();
    let files = 0;
    const walk = (directory) => {
        for (const entry of fs.readdirSync(directory, { withFileTypes: true }).sort((a, b) => a.name.localeCompare(b.name))) {
            const full = path.join(directory, entry.name);
            if (entry.isDirectory()) walk(full);
            else if (/\.(?:php|[cm]?js|[jt]sx?|html|vue|svelte|json)$/i.test(entry.name)) {
                files++;
                const result = extractIconUsage(fs.readFileSync(full, 'utf8'), path.relative(sourceRoot, full).replaceAll('\\', '/'));
                for (const [name, locations] of result.used) used.set(name, [...(used.get(name) ?? []), ...locations]);
                for (const reason of result.excluded) excluded.add(reason);
            }
        }
    };
    // A missing scope is an error, not an apparently successful empty audit.
    for (const scope of SOURCE_SCOPES) walk(path.join(sourceRoot, scope));
    return { used: new Map([...used].sort(([a], [b]) => a.localeCompare(b))), excluded, files };
}

export function loadIconData() {
    return Object.fromEntries(CUSTOM_SETS.map((set) => [set,
        JSON.parse(fs.readFileSync(path.join(root, 'node_modules/@iconify-json', set, 'icons.json'), 'utf8')),
    ]));
}

export function validateCatalog(catalog, data, usage) {
    const errors = [];
    if (Object.keys(catalog.sets ?? {}).sort().join(',') !== [...CUSTOM_SETS].sort().join(',')) {
        errors.push(`INVALID_ICON_SETS: expected exactly ${CUSTOM_SETS.join(', ')}`);
    }
    const icons = catalog.icons ?? {};
    const aliases = catalog.aliases ?? {};
    const resolved = Object.fromEntries(CUSTOM_SETS.map((set) => [set, {}]));
    for (const [concept, mappings] of Object.entries(icons)) {
        if (!/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(concept) || BRANDS.has(concept) || UTILITY.test(concept)) {
            errors.push(`INVALID_ICON_CONCEPT: ${concept}`);
        }
        for (const set of CUSTOM_SETS) {
            const name = mappings?.[set];
            if (typeof name !== 'string' || !name) errors.push(`MISSING_ICON_MAPPING: fa-${concept} [${set}]`);
            else if (!has(data[set]?.icons, name)) errors.push(`INVALID_CONCRETE_ICON: fa-${concept} [${set}] -> ${name} (Iconify aliases are not supported)`);
            else resolved[set][concept] = name;
        }
    }
    for (const [alias, concept] of Object.entries(aliases)) {
        if (has(icons, alias) || !has(icons, concept) || !/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(alias) || BRANDS.has(alias) || UTILITY.test(alias)) {
            errors.push(`INVALID_ICON_ALIAS: fa-${alias} -> ${concept} (must target a canonical concept without shadowing one)`);
        } else {
            for (const set of CUSTOM_SETS) resolved[set][alias] = resolved[set][concept];
        }
    }
    for (const [name, locations] of usage.used) {
        if (!has(icons, name) && !has(aliases, name)) errors.push(`MISSING_USED_ICON: fa-${name} at ${locations.join(', ')}`);
    }
    if (errors.length) throw new Error(`Icon coverage failed (${errors.length} errors):\n${errors.join('\n')}`);
    return resolved;
}

export function runRegressionTests(catalog, data) {
    const fixture = extractIconUsage(`'fa-user' classList.add('fa-circle-question'); <i class="fa fa-check fa-fw fa-2x fa-spin"></i>
        'fas fa-brand-new-concept' 'fab fa-instagram' 'fa-brands fa-telegram' 'fa-x-twitter'
        <time class="fa-date"></time> <span class="fa-weekday"></span>
                data-fa-date="today" data-fa-weekday="mon" 'fa-arrow-{{ $rtl ? 'right' : 'left' }}'`);
    assert.deepEqual([...fixture.used.keys()].sort(), ['arrow-left', 'arrow-right', 'brand-new-concept', 'check', 'circle-question', 'user']);
    assert.throws(() => validateCatalog(catalog, data, fixture), /MISSING_USED_ICON: fa-brand-new-concept/);
    const emptyUsage = { used: new Map() };
    const missing = structuredClone(catalog);
    missing.icons['brand-new-concept'] = {};
    assert.throws(() => validateCatalog(missing, data, emptyUsage), (error) => {
        for (const set of CUSTOM_SETS) assert.ok(error.message.includes(`MISSING_ICON_MAPPING: fa-brand-new-concept [${set}]`));
        return true;
    });
    for (const set of CUSTOM_SETS) {
        const partial = structuredClone(catalog);
        delete partial.icons.user[set];
        assert.throws(() => validateCatalog(partial, data, emptyUsage), new RegExp(`MISSING_ICON_MAPPING: fa-user \\[${set}\\]`));
        partial.icons.user[set] = 'not-a-concrete-icon';
        assert.throws(() => validateCatalog(partial, data, emptyUsage), /INVALID_CONCRETE_ICON: fa-user/);
        // Even an upstream alias that exists must not silently lose its transforms.
        const aliasOnlyData = structuredClone(data);
        aliasOnlyData[set].aliases ??= {};
        aliasOnlyData[set].aliases['test-transformed-alias'] = { parent: catalog.icons.user[set], hFlip: true };
        partial.icons.user[set] = 'test-transformed-alias';
        assert.throws(() => validateCatalog(partial, aliasOnlyData, emptyUsage), /INVALID_CONCRETE_ICON: fa-user/);
    }
    const invalidAlias = structuredClone(catalog);
    invalidAlias.aliases['test-alias'] = 'missing-target';
    assert.throws(() => validateCatalog(invalidAlias, data, emptyUsage), /INVALID_ICON_ALIAS: fa-test-alias/);
    assert.ok(extractIconUsage("'fa-arrow-{{ something_else }}'").used.has('arrow-'));
    console.log('Icon guard regression tests passed (source tokens, new concepts, all four mappings, concrete-only icons, aliases).');
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
    try {
        const catalog = JSON.parse(fs.readFileSync(path.join(root, 'resources/icons/catalog.json'), 'utf8'));
        const data = loadIconData();
        if (process.argv.includes('--self-test')) runRegressionTests(catalog, data);
        const usage = scanIconUsage();
        const resolved = validateCatalog(catalog, data, usage);
        console.log(`Icon audit passed: ${usage.used.size} used names across ${usage.files} source files; ${Object.keys(resolved.lucide).length} mappings in each of ${CUSTOM_SETS.length} sets.`);
        console.log(`Explicit exclusions: ${[...usage.excluded].sort().join(', ')}`);
    } catch (error) {
        console.error(error.message);
        process.exitCode = 1;
    }
}
