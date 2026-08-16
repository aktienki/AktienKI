<?php

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class LocalizationCoverageTest extends TestCase
{
    public function test_every_literal_interface_translation_has_an_english_value(): void
    {
        $translations = json_decode(
            file_get_contents(lang_path('en.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $missing = [];
        $placeholderErrors = [];
        $roots = [resource_path('views'), app_path()];

        foreach ($roots as $root) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
            foreach ($files as $file) {
                if (! $file->isFile() || ! in_array($file->getExtension(), ['php'], true)) {
                    continue;
                }

                $contents = file_get_contents($file->getPathname());
                preg_match_all('/__\(\s*([\'\"])(.*?)\1/s', $contents, $matches);
                foreach ($matches[2] as $key) {
                    $key = stripcslashes($key);
                    if (! array_key_exists($key, $translations)) {
                        $missing[] = $file->getPathname().': '.$key;
                        continue;
                    }

                    preg_match_all('/:[A-Za-z_][A-Za-z0-9_]*/', $key, $source);
                    preg_match_all('/:[A-Za-z_][A-Za-z0-9_]*/', $translations[$key], $target);
                    sort($source[0]);
                    sort($target[0]);
                    if ($source[0] !== $target[0]) {
                        $placeholderErrors[] = $key;
                    }
                }
            }
        }

        $this->assertSame([], array_values(array_unique($missing)));
        $this->assertSame([], array_values(array_unique($placeholderErrors)));
    }
}
