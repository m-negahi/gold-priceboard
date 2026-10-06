<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 🔴 Har class e `app/` bayad ghabl e LOAD bashe.
 *
 * ── Chera in test vojud dare ────────────────────────────────────────
 * `TableRequestDto` (va 3 DTO ye digar) `final readonly` budan vali az
 * `Dto` e NON-readonly ers mibordan — PHP in ro ejaze nemide va avvalin
 * autoload FATAL midad. Chun hich test-i un class ha ro lams nemikard،
 * bug ta avvalin estefade ye vaghei (safhe ye Media tu P3) penhan mimund.
 *
 * In test hame ro yek-ja load mikone، pas har kodum az in ha ro FORI
 * migire: ers e readonly e kharab، parent/interface e namojud، method e
 * abstract e implement-nashode، typo tu esm e namespace.
 *
 * ⚠️ `use` e class e namojud ya type-hint e constructor be class e
 * namojud ro NEMIGIRE — un ha faghat moghe'e instantiate miterkan.
 */
final class ClassLoadabilityTest extends TestCase
{
    public function test_every_app_class_is_loadable(): void
    {
        $base = dirname(__DIR__, 2).'/app';
        $checked = 0;

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if (! $file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getPathname();

            // Config file ha va view ha class nistan.
            if (str_contains($path, '/Config/') || str_contains($path, '/Resources/')) {
                continue;
            }

            $class = 'App\\'.str_replace('/', '\\', substr($path, strlen($base) + 1, -4));

            // `class_exists` autoload mikone؛ parent/interface e kharab ya
            // ers e readonly e ghalat haman lahze fatal mide.
            self::assertTrue(
                class_exists($class) || interface_exists($class) || trait_exists($class) || enum_exists($class),
                "class e `{$class}` load nashod (esm e file ba namespace nemikhune?).",
            );

            $checked++;
        }

        // Negahban e khod e test: agar masir avaz she va hichi scan nashe،
        // test e sabz e bi-ma'ni nabashim.
        self::assertGreaterThan(100, $checked, 'kamtar az 100 class scan shod — masir e scan kharab e.');
    }
}
