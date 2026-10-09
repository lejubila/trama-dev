<?php

declare(strict_types=1);

use Illuminate\Support\Arr;
use Symfony\Component\Finder\Finder;

/**
 * Guards the IT/EN translation files against drift: both locales must expose
 * exactly the same keys, and every `__('file.key')` used in the code must
 * resolve to an existing key (a typo would otherwise render the raw key).
 */

/** @return array<string, list<string>> file => flattened keys */
function langKeys(string $locale): array
{
    $out = [];
    foreach (glob(lang_path($locale.'/*.php')) as $file) {
        $out[basename($file, '.php')] = array_keys(Arr::dot(require $file));
    }
    ksort($out);

    return $out;
}

it('has the same translation files and keys in Italian and English', function (): void {
    $it = langKeys('it');
    $en = langKeys('en');

    expect(array_keys($en))->toBe(array_keys($it));

    foreach ($it as $file => $keys) {
        $missingEn = array_values(array_diff($keys, $en[$file]));
        $missingIt = array_values(array_diff($en[$file], $keys));
        expect($missingEn)->toBe([], "lang/en/{$file}.php is missing keys")
            ->and($missingIt)->toBe([], "lang/it/{$file}.php is missing keys");
    }
});

it('only references translation keys that exist', function (): void {
    $known = [];
    foreach (langKeys('it') as $file => $keys) {
        foreach ($keys as $key) {
            $known["{$file}.{$key}"] = true;
            // Allow referencing a whole group (e.g. __('enums.equipment_type')).
            $parts = explode('.', $key);
            while (count($parts) > 1) {
                array_pop($parts);
                $known[$file.'.'.implode('.', $parts)] = true;
            }
        }
    }
    $files = array_keys(langKeys('it'));

    $missing = [];
    $finder = (new Finder)->files()->in([app_path(), resource_path('views')])->name(['*.php']);
    foreach ($finder as $file) {
        preg_match_all("/(?:__|trans|trans_choice)\\(\\s*'([a-z_]+)\\.([A-Za-z0-9_.]+)'\\s*[,)]/", $file->getContents(), $m, PREG_SET_ORDER);
        foreach ($m as [, $group, $key]) {
            if (in_array($group, $files, true) && ! isset($known["{$group}.{$key}"])) {
                $missing[] = "{$group}.{$key} ({$file->getRelativePathname()})";
            }
        }
    }

    expect(array_values(array_unique($missing)))->toBe([]);
});
