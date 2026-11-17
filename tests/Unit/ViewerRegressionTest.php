<?php

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ViewerRegressionTest extends TestCase
{
    public function testViewerDoesNotImportThreeWithDefault(): void
    {
        $viewerJsPath = dirname(__DIR__, 2) . '/assets/viewer/viewer.js';
        self::assertFileExists($viewerJsPath);

        $content = file_get_contents($viewerJsPath);
        self::assertNotFalse($content);

        // Pattern 1: import('three') followed by .default
        $hasImportDotDefault = (bool) preg_match('/import\([\'"]three[\'"]\)\s*\.\s*default/', $content);
        self::assertFalse($hasImportDotDefault, 'assets/viewer/viewer.js must not access .default on import("three")');

        // Pattern 2: { default: ... } = ... import('three')
        $hasDestructuredDefault = (bool) preg_match('/\{\s*default\s*:[^}]*\}\s*=\s*.*import\([\'"]three[\'"]\)/s', $content);
        self::assertFalse($hasDestructuredDefault, 'assets/viewer/viewer.js must not destructure default from import("three")');

        // Positive check: loads Three as a namespace
        self::assertMatchesRegularExpression(
            '/const\s+THREE\s*=\s*await\s+import\([\'"]three[\'"]\);/',
            $content,
            'assets/viewer/viewer.js must load Three.js as namespace (const THREE = await import("three");)'
        );
    }
}
