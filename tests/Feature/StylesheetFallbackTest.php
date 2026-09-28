<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every page renders, and every asset it references exists.
 *
 * THE FAILURE THIS EXISTS FOR
 * ---------------------------
 * `@vite()` throws when `public/build/manifest.json` is missing or names files
 * that were never emitted. That turns a missing frontend build into a 500 on
 * every page, including the login form — so on a fresh clone the application
 * is unopenable, and the error the developer sees ("manifest not found") says
 * nothing about which URL broke.
 *
 * This repository once had exactly that: a `manifest.json` committed from an
 * earlier build, pointing at `build/assets/app-BXyz.js`, which was not in the
 * repository. Every page 500'd and the asset 404'd, and the cause was only
 * visible in the log.
 *
 * So the rule enforced here is deliberately blunt: no page may reference a
 * local file that is not there. That catches a stale manifest, a renamed
 * asset, a typo in `asset()`, and a fallback path pointing at a file nobody
 * wrote.
 *
 * NOTE ON `withoutVite()`
 * ----------------------
 * The rest of the suite calls it, which makes `@vite` a no-op and would hide
 * exactly this bug. These tests deliberately do NOT, so the real rendering
 * path is what is under test.
 */
class StylesheetFallbackTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The public pages, all of which must work on a fresh clone.
     *
     * Chosen because they are reachable with no account, no database seed and
     * no tenant — a failure here is unambiguously a rendering problem rather
     * than a fixture problem.
     *
     * @return list<array{string, string}>
     */
    public static function publicPages(): array
    {
        return [
            'landing' => ['/'],
            'login' => ['/login'],
            'register' => ['/register'],
            'forgot password' => ['/forgot-password'],
        ];
    }

    /**
     * @dataProvider publicPages
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('publicPages')]
    public function test_a_public_page_renders_without_a_frontend_build(string $path): void
    {
        // No `withoutVite()` here on purpose. See the class docblock.
        $this->get($path)->assertOk();
    }

    /**
     * @dataProvider publicPages
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('publicPages')]
    public function test_every_local_asset_a_page_references_actually_exists(string $path): void
    {
        $html = $this->get($path)->assertOk()->getContent();

        $this->assertIsString($html);

        $referenced = $this->localAssetPaths($html);

        $this->assertNotEmpty($referenced, 'The page is expected to reference at least one stylesheet.');

        foreach ($referenced as $asset) {
            $this->assertFileExists(
                public_path(ltrim((string) parse_url($asset, PHP_URL_PATH), '/')),
                sprintf('%s references %s, which does not exist.', $path, $asset),
            );
        }
    }

    public function test_a_stale_vite_manifest_is_not_committed(): void
    {
        /*
         * A manifest that exists but is wrong is worse than no manifest: the
         * guard in `x-stylesheets` trusts it, so `@vite()` runs and emits
         * references to files that are not there, and the fallback never
         * engages. The manifest belongs in `.gitignore` and only in a build
         * output directory.
         */
        $manifest = public_path('build/manifest.json');

        if (! file_exists($manifest)) {
            $this->assertTrue(true, 'No manifest committed, which is the expected state.');

            return;
        }

        // A manifest may legitimately exist locally after `npm run build`.
        // What must hold is that everything it points at also exists.
        $decoded = json_decode((string) file_get_contents($manifest), true);

        $this->assertIsArray($decoded, 'A committed manifest must be valid JSON.');

        foreach ($decoded as $entry) {
            $file = is_array($entry) ? ($entry['file'] ?? null) : null;

            if ($file !== null) {
                $this->assertFileExists(public_path('build/'.$file));
            }
        }
    }

    /**
     * Local asset paths referenced by a rendered page, as paths under `public/`.
     *
     * Only `<link>`, `<script>` and `<img>` are considered. A blanket
     * `href="..."` sweep also catches every `<a href="/login">` navigation
     * link, which are ROUTES, not files, and asserting `public/login` exists
     * would be nonsense.
     *
     * `asset()` emits ABSOLUTE urls (`http://localhost/css/prebuild.css`), so
     * "local" cannot mean "starts with a slash" — it means "on this app's own
     * origin". A same-origin url is reduced to its path; a genuinely external
     * one (a CDN, a font host) is skipped, because this repository is not
     * responsible for it.
     *
     * @return list<string>
     */
    private function localAssetPaths(string $html): array
    {
        preg_match_all('/<(?:link|script|img)\b[^>]*>/i', $html, $tags);

        $base = rtrim((string) config('app.url'), '/');

        $paths = [];

        foreach ($tags[0] ?? [] as $tag) {
            if (preg_match('/\s(?:href|src)="([^"]+)"/i', $tag, $attr) !== 1) {
                continue;
            }

            $value = $attr[1];

            if ($value === '' || $base === '') {
                continue;
            }

            foreach (['//', '#', 'data:', 'mailto:', 'tel:'] as $skip) {
                if (str_starts_with($value, $skip)) {
                    continue 2;
                }
            }

            if (str_starts_with($value, $base.'/')) {
                $path = substr($value, strlen($base) + 1);
            } elseif (str_starts_with($value, '/')) {
                $path = ltrim($value, '/');
            } else {
                continue;
            }

            if ($path !== '' && parse_url($path, PHP_URL_PATH)) {
                $paths[] = $path;
            }
        }

        return array_values(array_unique($paths));
    }
}
