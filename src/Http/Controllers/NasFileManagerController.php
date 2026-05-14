<?php

namespace P7H\NasFileManager\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use P7H\NasFileManager\Models\NasConnection;
use P7H\NasFileManager\NasStorageService;

class NasFileManagerController extends Controller
{
    public function __construct(private NasStorageService $nas) {}

    public function test(Request $request): JsonResponse
    {
        return response()->json($this->nas->testConnection($this->override($request)));
    }

    public function shares(Request $request): JsonResponse
    {
        return response()->json($this->nas->listShares($this->override($request)));
    }

    public function listItems(Request $request): JsonResponse
    {
        return response()->json(
            $this->nas->listItems($request->input('path', ''), $this->override($request))
        );
    }

    public function create(Request $request): JsonResponse
    {
        if (! $this->canEdit()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $path = trim($request->input('path', ''));
        $type = $request->input('type', 'dir');

        if ($path === '') {
            return response()->json(['success' => false, 'message' => 'Path is required.'], 422);
        }

        $result = $type === 'dir'
            ? $this->nas->createFolder($path, $this->override($request))
            : ['success' => false, 'message' => 'File creation not supported via this endpoint.'];

        return response()->json($result);
    }

    public function rename(Request $request): JsonResponse
    {
        if (! $this->canEdit()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $path = $request->input('path', '');
        $name = $request->input('name', '');

        if ($path === '' || $name === '') {
            return response()->json(['success' => false, 'message' => 'Path and name are required.'], 422);
        }

        return response()->json(
            $this->nas->rename($path, $name, $this->override($request))
        );
    }

    public function delete(Request $request): JsonResponse
    {
        if (! $this->canEdit()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $path = $request->input('path', '');
        $type = $request->input('type', 'dir');

        if ($path === '') {
            return response()->json(['success' => false, 'message' => 'Path is required.'], 422);
        }

        $result = $type === 'dir'
            ? $this->nas->deleteFolder($path, $this->override($request))
            : $this->nas->deleteFile($path, $this->override($request));

        return response()->json($result);
    }

    // ── Save connection ───────────────────────────────────────────────────────

    public function saveConnection(Request $request): JsonResponse
    {
        if (! $this->canEdit()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $target = $request->input('save_to', 'database');

        return $target === 'env'
            ? $this->saveToEnv($request)
            : $this->saveToDatabase($request);
    }

    private function saveToDatabase(Request $request): JsonResponse
    {
        $dbId = $request->input('db_id');

        $record = $dbId ? (NasConnection::find($dbId) ?? new NasConnection()) : new NasConnection();

        $record->fill([
            'name'         => $request->input('name') ?? 'NAS Connection',
            'enabled'      => (bool) $request->input('enabled', true),
            'protocol'     => $request->input('protocol') ?? 'sftp',
            'host'         => $request->input('host') ?? '',
            'port'         => (int) ($request->input('port') ?? 22),
            'username'     => $request->input('username') ?? '',
            'share'        => $request->input('share') ?? '',
            'smb_domain'   => $request->input('smb_domain') ?? '',
            'subdirectory' => $request->input('subdirectory') ?? '/media',
            'sort_order'   => (int) ($request->input('sort_order') ?? 0),
        ]);

        // Only overwrite password if a new one was provided
        if ($request->filled('password')) {
            $record->password = $request->input('password');
        }

        $record->save();

        return response()->json([
            'success' => true,
            'id'      => $record->id,
            'message' => 'Connection saved to database.',
        ]);
    }

    private function saveToEnv(Request $request): JsonResponse
    {
        $envPath = base_path('.env');

        if (! file_exists($envPath)) {
            return response()->json(['success' => false, 'message' => '.env file not found.']);
        }

        if (! is_writable($envPath)) {
            return response()->json(['success' => false, 'message' => '.env file is not writable by the web server.']);
        }

        $content = file_get_contents($envPath);

        $vars = [
            'NAS_ENABLED'    => $request->input('enabled', true) ? 'true' : 'false',
            'NAS_PROTOCOL'   => $request->input('protocol', 'sftp'),
            'NAS_HOST'       => $request->input('host', ''),
            'NAS_PORT'       => (int) $request->input('port', 22),
            'NAS_USERNAME'   => $request->input('username', ''),
            'NAS_PATH'       => $request->input('subdirectory', '/media'),
            'NAS_SMB_SHARE'  => $request->input('share', ''),
            'NAS_SMB_DOMAIN' => $request->input('smb_domain', ''),
        ];

        // Only overwrite password if a new one was provided
        if ($request->filled('password')) {
            $vars['NAS_PASSWORD'] = $request->input('password');
        }

        foreach ($vars as $key => $raw) {
            $value  = (string) $raw;
            $quoted = preg_match('/\s/', $value) ? '"' . addslashes($value) . '"' : $value;

            if (preg_match('/^' . $key . '=/m', $content)) {
                $content = preg_replace('/^' . $key . '=.*/m', $key . '=' . $quoted, $content);
            } else {
                $content .= PHP_EOL . $key . '=' . $quoted;
            }
        }

        file_put_contents($envPath, $content);

        try {
            Artisan::call('config:clear');
        } catch (\Throwable) {
            // Non-fatal — user can clear cache manually
        }

        return response()->json([
            'success' => true,
            'message' => 'Saved to .env. Reload the page to apply changes.',
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function canEdit(): bool
    {
        $gate = config('nas-file-manager.edit_gate');
        return $gate === null || Gate::allows($gate);
    }

    private function override(Request $request): array
    {
        $overrides = array_filter([
            'protocol'   => $request->input('protocol'),
            'host'       => $request->input('host'),
            'port'       => $request->input('port') ? (int) $request->input('port') : null,
            'username'   => $request->input('username'),
            'password'   => $request->input('password'),
            'smb_share'  => $request->input('smb_share'),
            'smb_domain' => $request->input('smb_domain'),
        ], fn($v) => $v !== null && $v !== '');

        // base_path overrides the configured subdirectory.
        // Kept separate from 'path' (browse path) to avoid conflict.
        if ($request->has('base_path')) {
            $overrides['path'] = $request->input('base_path') ?? '';
        }

        return $overrides;
    }
}
