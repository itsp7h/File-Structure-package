<?php

namespace P7H\NasFileManager;

use Composer\Script\Event;

class Installer
{
    private const BINARIES = [
        'smbclient' => [
            'apt-get' => 'smbclient',
            'dnf'     => 'samba-client',
            'yum'     => 'samba-client',
            'apk'     => 'samba-client',
            'pacman'  => 'smbclient',
        ],
        'sshpass' => [
            'apt-get' => 'sshpass',
            'dnf'     => 'sshpass',
            'yum'     => 'sshpass',
            'apk'     => 'sshpass',
            'pacman'  => 'sshpass',
        ],
    ];

    public static function install(Event $event = null): void
    {
        $io = $event?->getIO();

        if (PHP_OS_FAMILY !== 'Linux') {
            self::write($io, "<info>[nas-file-manager]</info> Skipping system dependency install on " . PHP_OS_FAMILY . " — please install <comment>smbclient</comment> and <comment>sshpass</comment> manually if you need SMB or SFTP rename support.");
            return;
        }

        $pm = self::detectPackageManager();

        if ($pm === null) {
            self::write($io, "<warning>[nas-file-manager]</warning> Could not detect a supported package manager. Please install <comment>smbclient</comment> and <comment>sshpass</comment> manually.");
            return;
        }

        $isRoot = self::isRoot();

        foreach (self::BINARIES as $binary => $packages) {
            if (self::commandExists($binary)) {
                self::write($io, "<info>[nas-file-manager]</info> $binary ✓ already installed.");
                continue;
            }

            $package = $packages[$pm] ?? $binary;
            $sudo    = $isRoot ? '' : 'sudo ';
            $cmd     = self::buildInstallCommand($pm, $package, $sudo);

            self::write($io, "<info>[nas-file-manager]</info> Installing <comment>$binary</comment> via $pm...");

            self::runCommand($cmd, $output, $exitCode);

            if ($exitCode === 0) {
                self::write($io, "<info>[nas-file-manager]</info> $binary installed successfully. ✓");
            } else {
                self::write($io, "<warning>[nas-file-manager]</warning> Could not install $binary automatically (exit $exitCode). Run manually: <comment>$cmd</comment>");
            }
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private static function detectPackageManager(): ?string
    {
        foreach (['apt-get', 'dnf', 'yum', 'apk', 'pacman'] as $pm) {
            if (self::commandExists($pm)) {
                return $pm;
            }
        }
        return null;
    }

    private static function buildInstallCommand(string $pm, string $package, string $sudo): string
    {
        return match ($pm) {
            'apt-get' => "{$sudo}apt-get install -y $package",
            'dnf'     => "{$sudo}dnf install -y $package",
            'yum'     => "{$sudo}yum install -y $package",
            'apk'     => "{$sudo}apk add --no-cache $package",
            'pacman'  => "{$sudo}pacman -S --noconfirm $package",
            default   => "{$sudo}$pm install -y $package",
        };
    }

    private static function commandExists(string $cmd): bool
    {
        exec("which $cmd 2>/dev/null", $out, $code);
        return $code === 0 && !empty($out);
    }

    private static function isRoot(): bool
    {
        if (function_exists('posix_geteuid')) {
            return posix_geteuid() === 0;
        }
        exec('id -u 2>/dev/null', $out, $code);
        return $code === 0 && trim($out[0] ?? '1') === '0';
    }

    private static function runCommand(string $cmd, ?array &$output, ?int &$exitCode): void
    {
        $output   = [];
        $exitCode = 0;

        if (function_exists('passthru')) {
            passthru($cmd, $exitCode);
            return;
        }

        exec($cmd, $output, $exitCode);
        foreach ($output as $line) {
            echo $line . PHP_EOL;
        }
    }

    private static function write(mixed $io, string $message): void
    {
        if ($io !== null) {
            $io->write($message);
        } else {
            // Strip basic XML-style tags when no Composer IO is available
            echo preg_replace('/<[^>]+>/', '', $message) . PHP_EOL;
        }
    }
}
