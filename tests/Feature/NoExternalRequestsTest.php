<?php

namespace Tests\Feature;

use Tests\TestCase;

class NoExternalRequestsTest extends TestCase
{
    /**
     * Ensure no Blade templates reference external CDNs or font servers.
     * All CSS, JS, and fonts must be served locally.
     */
    public function test_no_external_resource_requests_in_templates(): void
    {
        $viewsPath = resource_path('views');
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($viewsPath)
        );

        $violations = [];

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') continue;

            $content = file_get_contents($file->getPathname());
            $relativePath = str_replace(base_path() . '/', '', $file->getPathname());

            // Match <link href="https://..."> and <script src="https://...">
            if (preg_match_all('/<(?:link|script)[^>]+(?:href|src)=["\']https?:\/\/[^"\']+["\']/', $content, $matches)) {
                foreach ($matches[0] as $match) {
                    $violations[] = "{$relativePath}: {$match}";
                }
            }
        }

        $this->assertEmpty(
            $violations,
            "Found external resource requests in templates:\n" . implode("\n", $violations)
        );
    }
}
