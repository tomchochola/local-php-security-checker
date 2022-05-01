<?php

declare(strict_types=1);

namespace Tomchochola\LocalPhpSecurityChecker\Tests;

use Tomchochola\LocalPhpSecurityChecker\LocalPhpSecurityCheckerInstaller;

class LocalPhpSecurityCheckerInstallerTest extends TestCase
{
    public function test_local(): void
    {
        $binDir = $GLOBALS['_composer_bin_dir'];

        $localPath = \implode(\DIRECTORY_SEPARATOR, [$binDir, 'local-php-security-checker']);
        $localPathWithExtension = $localPath.(\PHP_OS_FAMILY === 'Windows' ? '.exe' : '');

        if (\file_exists($localPath)) {
            \unlink($localPath);
        }

        if (\file_exists($localPathWithExtension)) {
            \unlink($localPathWithExtension);
        }

        static::assertFileDoesNotExist($localPath);
        static::assertFileDoesNotExist($localPathWithExtension);

        LocalPhpSecurityCheckerInstaller::download($binDir, true);

        static::assertFileIsReadable($localPath);
        static::assertFileIsReadable($localPathWithExtension);
    }
}
