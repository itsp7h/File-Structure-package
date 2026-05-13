<?php

namespace P7H\NasFileManager;

class NasStorageService
{
    public function cfg(): array
    {
        $c = config('nas-file-manager.connection', []);

        return [
            'protocol'   => $c['protocol']   ?? 'sftp',
            'host'       => $c['host']        ?? '',
            'port'       => (int) ($c['port'] ?? 22),
            'username'   => $c['username']    ?? '',
            'password'   => $c['password']    ?? '',
            'path'       => rtrim($c['path']  ?? '/media', '/'),
            'smb_share'  => $c['smb_share']   ?? '',
            'smb_domain' => $c['smb_domain']  ?? '',
        ];
    }

    // ── Connection test ──────────────────────────────────────────────────────

    public function testConnection(array $override = []): array
    {
        $cfg = array_merge($this->cfg(), array_filter($override, fn($v) => $v !== null && $v !== ''));

        if (! $cfg['host']) {
            return ['success' => false, 'message' => 'No NAS host configured.'];
        }

        try {
            return $cfg['protocol'] === 'smb'
                ? $this->smbTest($cfg)
                : $this->curlTest($cfg);
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ── Share discovery ──────────────────────────────────────────────────────

    public function listShares(array $override = []): array
    {
        $cfg = array_merge($this->cfg(), array_filter($override, fn($v) => $v !== null && $v !== ''));

        if (! $cfg['host']) {
            return ['success' => false, 'message' => 'No server address provided.', 'shares' => []];
        }

        if (! $this->smbClientAvailable()) {
            return ['success' => false, 'message' => 'smbclient not installed (apt install smbclient).', 'shares' => []];
        }

        $host = escapeshellarg($cfg['host']);
        $cred = escapeshellarg($this->smbCredential($cfg));
        exec("smbclient -L {$host} -U {$cred} -g 2>&1", $output, $code);

        $shares = [];
        foreach ($output as $line) {
            $parts = explode('|', $line);
            if (count($parts) >= 2 && strtolower($parts[0]) === 'disk') {
                $name = trim($parts[1]);
                if ($name !== '') $shares[] = $name;
            }
        }

        if ($code !== 0 && empty($shares)) {
            return ['success' => false, 'message' => $this->smbFriendlyError(implode(' ', $output)), 'shares' => []];
        }

        return ['success' => true, 'message' => 'Connected.', 'shares' => $shares];
    }

    // ── List items (files + folders) ─────────────────────────────────────────

    public function listItems(?string $path, array $override = []): array
    {
        $cfg = array_merge($this->cfg(), array_filter($override, fn($v) => $v !== null && $v !== ''));

        return $cfg['protocol'] === 'smb'
            ? $this->listItemsSmb($path, $cfg)
            : $this->listItemsCurl($path, $cfg);
    }

    private function listItemsSmb(?string $path, array $cfg): array
    {
        $path = $path ?? '';
        if (! $this->smbClientAvailable()) {
            return ['success' => false, 'message' => 'smbclient not installed.', 'items' => []];
        }
        if (empty($cfg['smb_share'])) {
            return ['success' => false, 'message' => 'No SMB share configured.', 'items' => []];
        }

        $target = escapeshellarg($this->smbTarget($cfg));
        $cred   = escapeshellarg($this->smbCredential($cfg));
        $clean  = $this->sanitizeSmbPath($path);
        $cmdStr = $clean ? "cd \"{$clean}\"; ls" : 'ls';
        exec("smbclient {$target} -U {$cred} -c " . escapeshellarg($cmdStr) . ' 2>&1', $output, $code);

        if ($code !== 0) {
            return ['success' => false, 'message' => $this->smbFriendlyError(implode(' ', $output)), 'items' => []];
        }

        $items = [];
        foreach ($output as $line) {
            if (! preg_match('/^  (.+?)\s{2,}([DAHRS]*)\s+(\d+)\s+(.+)$/', $line, $m)) continue;
            $name  = trim($m[1]);
            $flags = strtoupper($m[2]);
            if ($name === '.' || $name === '..') continue;
            $isDir   = str_contains($flags, 'D');
            $items[] = [
                'name' => $name,
                'type' => $isDir ? 'dir' : 'file',
                'size' => (int) $m[3],
                'path' => $clean ? "{$clean}/{$name}" : $name,
            ];
        }

        usort($items, fn($a, $b) =>
            $a['type'] !== $b['type'] ? ($a['type'] === 'dir' ? -1 : 1) : strcasecmp($a['name'], $b['name'])
        );

        return ['success' => true, 'items' => $items, 'path' => $clean];
    }

    private function listItemsCurl(?string $path, array $cfg): array
    {
        $path    = $path ?? '';
        $base    = rtrim('/' . ltrim($cfg['path'] ?? '', '/'), '/');
        $subPath = $path !== '' ? '/' . ltrim($path, '/') : '';
        $url     = $this->buildUrl($cfg, $base . $subPath . '/');

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_USERPWD        => "{$cfg['username']}:{$cfg['password']}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        $this->applyCurlProtocol($ch, $cfg['protocol']);

        $output = curl_exec($ch);
        $errno  = curl_errno($ch);
        $errmsg = curl_error($ch);
        curl_close($ch);

        if ($errno !== 0) {
            return ['success' => false, 'message' => "Connection failed: {$errmsg}", 'items' => []];
        }

        $items = [];
        foreach (explode("\n", trim((string) $output)) as $line) {
            $line = trim($line);
            if ($line === '') continue;
            if (preg_match('/^([d-])(?:\S+\s+){7}(.+)$/', $line, $m)) {
                $isDir = $m[1] === 'd';
                $name  = trim($m[2]);
                if ($name === '.' || $name === '..') continue;
                $items[] = ['name' => $name, 'type' => $isDir ? 'dir' : 'file', 'size' => 0, 'path' => $path !== '' ? "{$path}/{$name}" : $name];
            } elseif (preg_match('/^(\d{2}-\d{2}-\d{2})\s+\S+\s+(<DIR>|\d+)\s+(.+)$/', $line, $m)) {
                $isDir = $m[2] === '<DIR>';
                $name  = trim($m[3]);
                if ($name === '.' || $name === '..') continue;
                $items[] = ['name' => $name, 'type' => $isDir ? 'dir' : 'file', 'size' => $isDir ? 0 : (int) $m[2], 'path' => $path !== '' ? "{$path}/{$name}" : $name];
            }
        }

        usort($items, fn($a, $b) =>
            $a['type'] !== $b['type'] ? ($a['type'] === 'dir' ? -1 : 1) : strcasecmp($a['name'], $b['name'])
        );

        return ['success' => true, 'items' => $items, 'path' => $path];
    }

    // ── Create folder ────────────────────────────────────────────────────────

    public function createFolder(string $path, array $override = []): array
    {
        $cfg = array_merge($this->cfg(), array_filter($override, fn($v) => $v !== null && $v !== ''));

        return $cfg['protocol'] === 'smb'
            ? $this->createSmbFolder($path, $cfg)
            : $this->createCurlFolder($path, $cfg);
    }

    public function createSmbFolder(string $path, array $cfg): array
    {
        if (! $this->smbClientAvailable()) {
            return ['success' => false, 'message' => 'smbclient not installed.'];
        }
        $clean = $this->sanitizeSmbPath($path);
        if (! $clean) return ['success' => false, 'message' => 'Invalid folder name.'];

        $target = escapeshellarg($this->smbTarget($cfg));
        $cred   = escapeshellarg($this->smbCredential($cfg));
        exec("smbclient {$target} -U {$cred} -c " . escapeshellarg("mkdir \"{$clean}\"") . ' 2>&1', $output, $code);

        return $code === 0
            ? ['success' => true]
            : ['success' => false, 'message' => $this->smbFriendlyError(implode(' ', $output))];
    }

    private function createCurlFolder(string $path, array $cfg): array
    {
        $base    = rtrim('/' . ltrim($cfg['path'] ?? '', '/'), '/');
        $fullUrl = $this->buildUrl($cfg, $base . '/' . ltrim($path, '/') . '/');
        $ch      = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $fullUrl,
            CURLOPT_USERPWD        => "{$cfg['username']}:{$cfg['password']}",
            CURLOPT_NOBODY         => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        $this->applyCurlProtocol($ch, $cfg['protocol']);
        if (in_array($cfg['protocol'], ['ftp', 'ftps'])) {
            curl_setopt($ch, CURLOPT_FTP_CREATE_MISSING_DIRS, 2);
        }
        curl_exec($ch);
        $errno  = curl_errno($ch);
        $errmsg = curl_error($ch);
        curl_close($ch);

        return $errno === 0
            ? ['success' => true]
            : ['success' => false, 'message' => "Create failed: {$errmsg}"];
    }

    // ── Rename ───────────────────────────────────────────────────────────────

    public function rename(string $oldPath, string $newName, array $override = []): array
    {
        $cfg      = array_merge($this->cfg(), array_filter($override, fn($v) => $v !== null && $v !== ''));
        $safeName = preg_replace('/[`$;|&<>*?\\/\\\\]/', '', trim($newName));
        if (! $safeName) return ['success' => false, 'message' => 'Invalid name.'];

        return $cfg['protocol'] === 'smb'
            ? $this->renameSmb($oldPath, $safeName, $cfg)
            : $this->renameViaSsh($oldPath, $safeName, $cfg);
    }

    private function renameSmb(string $oldPath, string $safeName, array $cfg): array
    {
        if (! $this->smbClientAvailable()) return ['success' => false, 'message' => 'smbclient not installed.'];
        $oldClean = $this->sanitizeSmbPath($oldPath);
        $parts    = explode('/', $oldClean);
        array_pop($parts);
        $newClean = ltrim(implode('/', $parts) . '/' . $safeName, '/');

        $target = escapeshellarg($this->smbTarget($cfg));
        $cred   = escapeshellarg($this->smbCredential($cfg));
        exec("smbclient {$target} -U {$cred} -c " . escapeshellarg("rename \"{$oldClean}\" \"{$newClean}\"") . ' 2>&1', $output, $code);

        return $code === 0
            ? ['success' => true, 'newPath' => $newClean]
            : ['success' => false, 'message' => $this->smbFriendlyError(implode(' ', $output))];
    }

    private function renameViaSsh(string $oldPath, string $safeName, array $cfg): array
    {
        if (! $this->sshpassAvailable()) return ['success' => false, 'message' => 'sshpass not available for SFTP rename.'];
        $base    = rtrim('/' . ltrim($cfg['path'] ?? '', '/'), '/');
        $oldFull = escapeshellarg($base . '/' . ltrim($oldPath, '/'));
        $parts   = explode('/', ltrim($oldPath, '/'));
        array_pop($parts);
        $newFull = escapeshellarg($base . '/' . ltrim(implode('/', $parts) . '/' . $safeName, '/'));
        $pass    = escapeshellarg($cfg['password']);
        $user    = escapeshellarg("{$cfg['username']}@{$cfg['host']}");
        exec("sshpass -p {$pass} ssh -o StrictHostKeyChecking=no -p {$cfg['port']} {$user} \"mv {$oldFull} {$newFull}\" 2>&1", $output, $code);

        return $code === 0
            ? ['success' => true]
            : ['success' => false, 'message' => implode(' ', $output) ?: 'Rename failed.'];
    }

    // ── Delete folder ────────────────────────────────────────────────────────

    public function deleteFolder(string $path, array $override = []): array
    {
        $cfg   = array_merge($this->cfg(), array_filter($override, fn($v) => $v !== null && $v !== ''));
        $clean = $this->sanitizeSmbPath($path);
        if (! $clean) return ['success' => false, 'message' => 'Invalid folder path.'];

        if ($cfg['protocol'] === 'smb') {
            if (! $this->smbClientAvailable()) return ['success' => false, 'message' => 'smbclient not installed.'];
            $target = escapeshellarg($this->smbTarget($cfg));
            $cred   = escapeshellarg($this->smbCredential($cfg));
            exec("smbclient {$target} -U {$cred} -c " . escapeshellarg("rmdir \"{$clean}\"") . ' 2>&1', $output, $code);
            return $code === 0 ? ['success' => true] : ['success' => false, 'message' => $this->smbFriendlyError(implode(' ', $output))];
        }

        // SFTP/FTP: issue DELE or rm via postquote
        $base    = rtrim('/' . ltrim($cfg['path'] ?? '', '/'), '/');
        $rmPath  = $base . '/' . ltrim($path, '/');
        $baseUrl = $this->buildUrl($cfg, '/');
        $ch      = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $baseUrl,
            CURLOPT_USERPWD        => "{$cfg['username']}:{$cfg['password']}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOBODY         => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        $this->applyCurlProtocol($ch, $cfg['protocol']);
        $rmCmd = $cfg['protocol'] === 'sftp' ? "rmdir {$rmPath}" : "RMD {$rmPath}";
        curl_setopt($ch, CURLOPT_POSTQUOTE, [$rmCmd]);
        curl_exec($ch);
        $errno  = curl_errno($ch);
        $errmsg = curl_error($ch);
        curl_close($ch);

        return $errno === 0 ? ['success' => true] : ['success' => false, 'message' => "Delete failed: {$errmsg}"];
    }

    // ── Delete file ──────────────────────────────────────────────────────────

    public function deleteFile(string $path, array $override = []): array
    {
        $cfg = array_merge($this->cfg(), array_filter($override, fn($v) => $v !== null && $v !== ''));

        if ($cfg['protocol'] === 'smb') {
            $clean = $this->sanitizeSmbPath($path);
            if (! $clean) return ['success' => false, 'message' => 'Invalid file path.'];
            if (! $this->smbClientAvailable()) return ['success' => false, 'message' => 'smbclient not installed.'];
            $target = escapeshellarg($this->smbTarget($cfg));
            $cred   = escapeshellarg($this->smbCredential($cfg));
            exec("smbclient {$target} -U {$cred} -c " . escapeshellarg("rm \"{$clean}\"") . ' 2>&1', $output, $code);
            return $code === 0 ? ['success' => true] : ['success' => false, 'message' => $this->smbFriendlyError(implode(' ', $output))];
        }

        $base    = rtrim('/' . ltrim($cfg['path'] ?? '', '/'), '/');
        $rmPath  = $base . '/' . ltrim($path, '/');
        $baseUrl = $this->buildUrl($cfg, '/');
        $ch      = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $baseUrl,
            CURLOPT_USERPWD        => "{$cfg['username']}:{$cfg['password']}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOBODY         => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        $this->applyCurlProtocol($ch, $cfg['protocol']);
        $rmCmd = $cfg['protocol'] === 'sftp' ? "rm {$rmPath}" : "DELE {$rmPath}";
        curl_setopt($ch, CURLOPT_POSTQUOTE, [$rmCmd]);
        curl_exec($ch);
        $errno  = curl_errno($ch);
        $errmsg = curl_error($ch);
        curl_close($ch);

        return $errno === 0 ? ['success' => true] : ['success' => false, 'message' => "Delete failed: {$errmsg}"];
    }

    // ── SMB helpers ──────────────────────────────────────────────────────────

    private function smbCredential(array $cfg): string
    {
        $domain = $cfg['smb_domain'] ? "{$cfg['smb_domain']}/" : '';
        return "{$domain}{$cfg['username']}%{$cfg['password']}";
    }

    private function smbTarget(array $cfg): string
    {
        return "//{$cfg['host']}/" . trim($cfg['smb_share'], '/');
    }

    public function sanitizeSmbPath(string $path): string
    {
        $parts = array_filter(
            explode('/', str_replace('\\', '/', $path)),
            fn($p) => $p !== '' && $p !== '.' && $p !== '..'
        );
        return implode('/', array_map(
            fn($p) => preg_replace('/[`$;|&<>*?]/', '', $p),
            array_values($parts)
        ));
    }

    private function smbTest(array $cfg): array
    {
        if (! $cfg['smb_share']) return ['success' => false, 'message' => 'No SMB share name configured.'];
        if (! $this->smbClientAvailable()) return ['success' => false, 'message' => 'smbclient is not installed (apt install smbclient).'];

        $dir    = trim($cfg['path'], '/');
        $target = escapeshellarg($this->smbTarget($cfg));
        $cred   = escapeshellarg($this->smbCredential($cfg));
        $smbCmd = escapeshellarg($dir ? "cd {$dir}; ls" : 'ls');
        exec("smbclient {$target} -U {$cred} -c {$smbCmd} 2>&1", $output, $code);

        return $code === 0
            ? ['success' => true, 'message' => "Connected to SMB share \\\\{$cfg['host']}\\{$cfg['smb_share']} successfully."]
            : ['success' => false, 'message' => $this->smbFriendlyError(implode(' ', $output))];
    }

    private function smbFriendlyError(string $raw): string
    {
        $r = strtolower($raw);

        if (str_contains($r, 'nt_status_logon_failure') || str_contains($r, 'nt_status_wrong_password')) {
            return 'Incorrect username or password.';
        }
        if (str_contains($r, 'nt_status_access_denied')) {
            return 'Access denied. The user does not have permission to access this share.';
        }
        if (str_contains($r, 'nt_status_host_unreachable') || str_contains($r, 'connection refused') || str_contains($r, 'no route to host')) {
            return 'Cannot reach the server. Check the IP address and make sure SMB port 445 is open.';
        }
        if (str_contains($r, 'nt_status_connection_timed_out') || str_contains($r, 'timed out')) {
            return 'Connection timed out. The server is unreachable or SMB is blocked by a firewall.';
        }
        if (str_contains($r, 'nt_status_bad_network_name') || str_contains($r, 'nt_status_network_name_deleted')) {
            return 'Server not found on the network. Double-check the hostname or IP address.';
        }

        $clean = preg_replace('/NT_STATUS_[A-Z_]+/i', '', $raw);
        $clean = trim(preg_replace('/\s+/', ' ', $clean), " \t\n\r:.-");
        return $clean ?: 'Could not connect to the SMB server.';
    }

    private function smbClientAvailable(): bool
    {
        exec('which smbclient 2>/dev/null', $out, $code);
        return $code === 0;
    }

    private function sshpassAvailable(): bool
    {
        exec('which sshpass 2>/dev/null', $out);
        return ! empty($out);
    }

    // ── SFTP / FTP / FTPS ────────────────────────────────────────────────────

    private function curlTest(array $cfg): array
    {
        $url = $this->buildUrl($cfg, $cfg['path'] . '/');
        $ch  = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_USERPWD        => "{$cfg['username']}:{$cfg['password']}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_NOBODY         => true,
        ]);
        $this->applyCurlProtocol($ch, $cfg['protocol']);
        curl_exec($ch);
        $errno  = curl_errno($ch);
        $errmsg = curl_error($ch);
        curl_close($ch);

        return $errno === 0
            ? ['success' => true, 'message' => "Connected to {$cfg['protocol']}://{$cfg['host']} successfully."]
            : ['success' => false, 'message' => "Connection failed: {$errmsg}"];
    }

    private function buildUrl(array $cfg, string $path): string
    {
        return "{$cfg['protocol']}://{$cfg['host']}:{$cfg['port']}{$path}";
    }

    private function applyCurlProtocol(\CurlHandle $ch, string $protocol): void
    {
        match ($protocol) {
            'sftp'  => curl_setopt_array($ch, [CURLOPT_PROTOCOLS => CURLPROTO_SFTP, CURLOPT_SSH_AUTH_TYPES => CURLSSH_AUTH_PASSWORD]),
            'ftps'  => curl_setopt_array($ch, [CURLOPT_PROTOCOLS => CURLPROTO_FTPS, CURLOPT_FTP_SSL => CURLFTPSSL_ALL]),
            default => curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_FTP),
        };
    }
}
