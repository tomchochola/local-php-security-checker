<?php

declare(strict_types=1);

namespace Tomchochola\LocalPhpSecurityChecker;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use RuntimeException;

class LocalPhpSecurityCheckerInstaller implements PluginInterface, EventSubscriberInterface
{
    /**
     * Supported osses.
     */
    public const OS = [
        'Windows' => 'windows',
        'Linux' => 'linux',
        'Darwin' => 'darwin',
    ];

    /**
     * Supported architectures.
     */
    public const ARCHITECTURE = [
        'i386' => '386',
        'x86_64' => 'amd64',
        'amd64' => 'amd64',
        'arm' => 'arm64',
    ];

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'post-install-cmd' => 'install',
            'post-update-cmd' => 'update',
        ];
    }

    /**
     * Install auditor.
     */
    public static function install(Event $event): void
    {
        $binDir = static::binDir($event);

        static::download($binDir, false);
    }

    /**
     * Update auditor.
     */
    public static function update(Event $event): void
    {
        $binDir = static::binDir($event);

        static::download($binDir, true);
    }

    /**
     * Download to given bin dir.
     */
    public static function download(string $binDir, bool $force): void
    {
        static::ensureVendorBinDirectoryExists($binDir);

        $localPath = \implode(\DIRECTORY_SEPARATOR, [$binDir, 'local-php-security-checker']);
        $localPathWithExtension = $localPath.(\PHP_OS_FAMILY === 'Windows' ? '.exe' : '');

        if ($force === false && \file_exists($localPath) && \file_exists($localPathWithExtension) && \is_executable($localPathWithExtension)) {
            return;
        }

        [$remoteSha, $remotePath] = static::remotePath();

        $ok = \touch($localPath);

        if ($ok === false) {
            throw new RuntimeException('Could not write binary.');
        }

        $ok = \touch($localPathWithExtension);

        if ($ok === false) {
            throw new RuntimeException('Could not write binary.');
        }

        $hash = \hash_file('sha256', $localPathWithExtension);

        if ($hash === false) {
            throw new RuntimeException('Could not compute sha256sum.');
        }

        if (\file_exists($localPathWithExtension) && \is_executable($localPathWithExtension) && \hash_equals($remoteSha, $hash)) {
            return;
        }

        $binary = \file_get_contents($remotePath);

        if ($binary === false) {
            throw new RuntimeException('Could not download binary.');
        }

        $hash = \hash('sha256', $binary);

        if (! \hash_equals($remoteSha, $hash)) {
            throw new RuntimeException('Downloaded binary hash not match.');
        }

        $ok = \file_put_contents($localPathWithExtension, $binary);

        if ($ok === false) {
            throw new RuntimeException('Could not write binary.');
        }

        $ok = \chmod($localPathWithExtension, 0o755);

        if ($ok === false) {
            throw new RuntimeException('Could not make binary executable.');
        }
    }

    /**
     * @inheritDoc
     */
    public function activate(Composer $composer, IOInterface $io): void
    {
    }

    /**
     * @inheritDoc
     */
    public function deactivate(Composer $composer, IOInterface $io): void
    {
    }

    /**
     * @inheritDoc
     */
    public function uninstall(Composer $composer, IOInterface $io): void
    {
    }

    /**
     * Resolve vendor/bin dir.
     */
    protected static function binDir(Event $event): string
    {
        $binDir = $event->getComposer()->getConfig()->get('bin-dir');

        if (\is_string($binDir)) {
            return $binDir;
        }

        return '';
    }

    /**
     * Make vendor/bin directory.
     */
    protected static function ensureVendorBinDirectoryExists(string $binDir): void
    {
        if (! \file_exists($binDir)) {
            $ok = \mkdir($binDir, 0o755);

            if ($ok === false) {
                throw new RuntimeException('Could not create vendor/bin directory.');
            }
        }

        if (! \is_dir($binDir)) {
            throw new RuntimeException('Could not find vendor/bin directory.');
        }

        if (! \is_writable($binDir)) {
            throw new RuntimeException('Could not write to vendor/bin directory.');
        }
    }

    /**
     * Fetch sum and remote bin path.
     *
     * @return array{string, string}
     */
    protected static function remotePath(): array
    {
        $os = \PHP_OS_FAMILY;
        $architecture = \mb_strtolower(\php_uname('m'));

        if (! \array_key_exists($os, static::OS)) {
            throw new RuntimeException("Unsupported os: [{$os}].");
        }

        if (! \array_key_exists($architecture, static::ARCHITECTURE)) {
            throw new RuntimeException("Unsupported architecture: [{$architecture}].");
        }

        $checksums = \file_get_contents('https://github.com/fabpot/local-php-security-checker/releases/latest/download/checksums.txt');

        if ($checksums === false) {
            throw new RuntimeException('Could not download latest release.');
        }

        $lines = \explode("\n", $checksums);

        foreach ($lines as $line) {
            $line = \str_replace('  ', ' ', $line);

            if (! \str_contains($line, static::OS[$os])) {
                continue;
            }

            if (! \str_contains($line, static::ARCHITECTURE[$architecture])) {
                continue;
            }

            $exploded = \explode(' ', $line);

            if (\count($exploded) !== 2) {
                continue;
            }

            return [$exploded[0], 'https://github.com/fabpot/local-php-security-checker/releases/latest/download/'.$exploded[1]];
        }

        throw new RuntimeException("Unsupported os: [{$os}], or unsupported architecture: [{$architecture}].");
    }
}
