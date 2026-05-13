<?php

namespace P7H\NasFileManager\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
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

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function canEdit(): bool
    {
        $gate = config('nas-file-manager.edit_gate');
        return $gate === null || Gate::allows($gate);
    }

    /**
     * Allows the caller to pass live credentials in the request body
     * (useful when the host UI lets the user edit connection settings before saving).
     * Only non-empty values override the config.
     */
    private function override(Request $request): array
    {
        return array_filter([
            'protocol'   => $request->input('protocol'),
            'host'       => $request->input('host'),
            'port'       => $request->input('port') ? (int) $request->input('port') : null,
            'username'   => $request->input('username'),
            'password'   => $request->input('password'),
            'smb_share'  => $request->input('smb_share'),
            'smb_domain' => $request->input('smb_domain'),
        ], fn($v) => $v !== null && $v !== '');
    }
}
