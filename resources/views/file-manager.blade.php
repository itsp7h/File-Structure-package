{{--
  NAS File Manager Component
  --------------------------
  Usage:
    @include('nas-file-manager::file-manager', [
        'nodes'   => $treeNodes,
        'canEdit' => true,
        'title'   => 'Folder Structure & File Manager',
    ])
--}}

@php
    $nodes   = $nodes   ?? config('nas-file-manager.schema', []);
    $canEdit = $canEdit ?? (config('nas-file-manager.edit_gate') === null || \Illuminate\Support\Facades\Gate::allows(config('nas-file-manager.edit_gate')));
    $title   = $title   ?? 'Folder Structure & File Manager';

    // Build connections array for JS, supporting both new `connections` and legacy `connection`
    $rawConns = config('nas-file-manager.connections', []);
    if (empty($rawConns)) {
        $lc = config('nas-file-manager.connection', []);
        $rawConns = [[
            'name'         => 'Primary NAS',
            'enabled'      => true,
            'protocol'     => $lc['protocol']   ?? 'sftp',
            'host'         => $lc['host']        ?? '',
            'port'         => (int)($lc['port']  ?? 22),
            'username'     => $lc['username']    ?? '',
            'password'     => $lc['password']    ?? '',
            'share'        => $lc['smb_share']   ?? '',
            'smb_domain'   => $lc['smb_domain']  ?? '',
            'subdirectory' => $lc['path']        ?? '/media',
        ]];
    }

    $connectionsJs = collect($rawConns)->values()->map(fn($c, $i) => [
        '_id'            => $i + 1,
        'name'           => $c['name']                             ?? ('Connection ' . ($i + 1)),
        'enabled'        => (bool)($c['enabled']                   ?? true),
        'expanded'       => $i === 0,
        'protocol'       => $c['protocol']                         ?? 'sftp',
        'host'           => $c['host']                              ?? '',
        'port'           => (int)($c['port']                        ?? 22),
        'username'       => $c['username']                          ?? '',
        'password'       => '',
        'has_password'   => !empty($c['password']),
        'share'          => $c['share'] ?? ($c['smb_share']         ?? ''),
        'smb_domain'     => $c['smb_domain']                        ?? '',
        'subdirectory'   => $c['subdirectory'] ?? ($c['path']       ?? '/media'),
        'testStatus'     => null,
        'testMessage'    => '',
        'pickerOpen'     => false,
        'pickerItems'    => [],
        'pickerPath'     => '',
        'pickerSegments' => [],
        'pickerLoading'  => false,
        'pickerError'    => '',
    ])->all();

    $hasConnection = collect($rawConns)->contains(fn($c) => !empty($c['host']));
@endphp

<div x-data="{ accordionOpen: {{ $hasConnection ? 'false' : 'true' }}, ...nasFmComponent(@js($nodes), @js($connectionsJs)) }"
     class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">

    {{-- ── Accordion header ── --}}
    <button type="button" @click="accordionOpen = !accordionOpen"
            class="w-full flex items-center justify-between gap-3 px-6 py-4 text-left hover:bg-slate-50 transition-colors">
        <div class="flex items-center gap-2.5">
            <div class="w-7 h-7 rounded-lg bg-amber-50 flex items-center justify-center flex-shrink-0">
                <svg class="w-3.5 h-3.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                </svg>
            </div>
            <div>
                <p class="text-sm font-semibold text-slate-800">{{ $title }}</p>
                <p class="text-xs text-slate-400 mt-0.5">
                    @if($hasConnection)
                        View schema or browse and manage files live on your NAS
                    @else
                        <span class="text-amber-500 font-medium">Connection not configured</span> — expand to set up your NAS connection
                    @endif
                </p>
            </div>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0">
            @if(!$hasConnection)
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-100 text-amber-700">
                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                </svg>
                Setup required
            </span>
            @endif
            <svg class="w-4 h-4 text-slate-400 transition-transform duration-200"
                 :class="accordionOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </div>
    </button>

    <div x-show="accordionOpen"
         x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="border-t border-slate-100" style="{{ $hasConnection ? 'display:none' : '' }}">

        {{-- ── Tab switcher ── --}}
        <div class="flex items-center gap-1 px-6 pt-5 pb-0">
            <button type="button" @click="tab = 'schema'"
                    :class="tab === 'schema' ? 'bg-slate-800 text-white' : 'text-slate-500 hover:bg-slate-100 hover:text-slate-700'"
                    class="inline-flex items-center gap-1.5 text-xs font-medium px-3.5 py-2 rounded-xl transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                </svg>
                Schema
            </button>
            <button type="button" @click="tab = 'browser'; if(items.length === 0 && !loading && !error) load('')"
                    :class="tab === 'browser' ? 'bg-brand-600 text-white' : 'text-slate-500 hover:bg-slate-100 hover:text-slate-700'"
                    class="inline-flex items-center gap-1.5 text-xs font-medium px-3.5 py-2 rounded-xl transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                </svg>
                Live Browser
            </button>
            <button type="button" @click="tab = 'connection'"
                    :class="tab === 'connection' ? 'bg-sky-600 text-white' : 'text-slate-500 hover:bg-slate-100 hover:text-slate-700'"
                    class="inline-flex items-center gap-1.5 text-xs font-medium px-3.5 py-2 rounded-xl transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/>
                </svg>
                Connection
                @if(!$hasConnection)
                <span class="w-1.5 h-1.5 rounded-full bg-amber-400 flex-shrink-0"></span>
                @endif
            </button>
        </div>

        {{-- ══════════════════════════════════════════════════════════════ --}}
        {{-- SCHEMA TAB                                                     --}}
        {{-- ══════════════════════════════════════════════════════════════ --}}
        <div x-show="tab === 'schema'" class="px-6 py-5" style="display:none">
            <p class="text-xs text-slate-500 mb-4">
                All paths are relative to each connection's subdirectory.
                Placeholders in <span class="text-brand-600 font-mono">{curly braces}</span> are filled in at runtime.
            </p>
            <div class="font-mono text-xs text-slate-700 bg-slate-50 rounded-xl p-4 border border-slate-100 overflow-x-auto">
                <template x-for="(node, i) in nodes" :key="node.id ?? i">
                    <div class="flex items-center gap-1.5 leading-relaxed"
                         :style="{ paddingLeft: (node.depth * 16) + 'px' }">
                        <svg class="w-3.5 h-3.5 flex-shrink-0"
                             :class="node.is_template ? 'text-slate-300' : (node.depth <= 1 ? 'text-amber-500' : (node.depth <= 3 ? 'text-amber-400' : 'text-amber-300'))"
                             fill="currentColor" viewBox="0 0 20 20">
                            <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/>
                        </svg>
                        <span :class="node.is_template ? 'text-brand-500 italic' : (node.depth === 0 ? 'text-slate-800 font-bold' : (node.depth === 1 ? 'text-slate-800 font-semibold' : 'text-slate-600'))"
                              x-text="node.label + (node.is_template ? '' : '/')"></span>
                    </div>
                </template>
                <template x-if="nodes.length === 0">
                    <p class="text-slate-400 italic">No schema defined.</p>
                </template>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════════ --}}
        {{-- LIVE BROWSER TAB                                               --}}
        {{-- ══════════════════════════════════════════════════════════════ --}}
        <div x-show="tab === 'browser'" class="px-6 py-5 space-y-4" style="display:none">

            {{-- Toast --}}
            <div x-show="toast" x-transition style="display:none"
                 class="fixed bottom-6 right-6 z-50 flex items-center gap-2.5 px-4 py-3 rounded-2xl shadow-lg text-sm font-medium border"
                 :class="toast?.type === 'success' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-red-50 text-red-800 border-red-200'">
                <svg x-show="toast?.type === 'success'" class="w-4 h-4 text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
                <svg x-show="toast?.type !== 'success'" class="w-4 h-4 text-red-500 flex-shrink-0" style="display:none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                </svg>
                <span x-text="toast?.msg"></span>
            </div>

            {{-- Breadcrumb + reload --}}
            <div class="flex items-center gap-2 min-w-0">
                <div class="flex items-center gap-1 flex-1 min-w-0 overflow-x-auto">
                    <button type="button" @click="load('')"
                            class="text-xs text-brand-600 hover:underline font-medium flex-shrink-0">Root</button>
                    <template x-for="(seg, i) in segments" :key="i">
                        <span class="flex items-center gap-1 flex-shrink-0">
                            <svg class="w-3 h-3 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                            <button type="button"
                                    @click="load(segments.slice(0, i + 1).join('/'))"
                                    class="text-xs text-brand-600 hover:underline font-medium" x-text="seg"></button>
                        </span>
                    </template>
                </div>
                <button type="button" @click="load(currentPath)" :disabled="loading"
                        class="flex-shrink-0 p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors disabled:opacity-40">
                    <svg class="w-3.5 h-3.5" :class="loading ? 'animate-spin' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                </button>
            </div>

            <div x-show="error && !loading" class="p-3 bg-red-50 border border-red-100 rounded-xl text-xs text-red-700" x-text="error" style="display:none"></div>

            {{-- File / folder list --}}
            <div class="rounded-xl border border-slate-200 overflow-hidden bg-white select-none">
                <div x-show="loading" class="flex items-center justify-center py-10">
                    <svg class="w-5 h-5 animate-spin text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                </div>
                <div x-show="!loading && !error && items.length === 0" class="text-center py-10 text-xs text-slate-400" style="display:none">Empty folder</div>
                <template x-for="item in items" :key="item.path">
                    <div class="group flex items-center gap-2.5 px-4 py-2.5 border-b border-slate-100 last:border-0 hover:bg-sky-50/60 transition-colors">
                        <svg x-show="item.type === 'dir'" class="w-4 h-4 text-amber-400 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/>
                        </svg>
                        <svg x-show="item.type !== 'dir'" class="w-4 h-4 text-slate-300 flex-shrink-0" style="display:none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                        </svg>
                        <button type="button"
                                @click="item.type === 'dir' ? load(item.path) : null"
                                :class="item.type === 'dir' ? 'hover:text-brand-600 cursor-pointer' : 'cursor-default text-slate-500'"
                                class="flex-1 text-left text-xs font-mono text-slate-700 transition-colors truncate"
                                x-text="item.name"></button>
                        <span x-show="item.type !== 'dir' && item.size > 0"
                              class="text-[10px] text-slate-400 flex-shrink-0 font-mono"
                              x-text="formatSize(item.size)" style="display:none"></span>
                        @if($canEdit)
                        <div class="hidden group-hover:flex items-center gap-1 flex-shrink-0">
                            <button type="button" @click.stop="startRename(item)" title="Rename"
                                    class="p-1.5 rounded-md text-slate-400 hover:text-brand-600 hover:bg-brand-50 transition-colors">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                            </button>
                            <button type="button" @click.stop="confirmDelete = item" title="Delete"
                                    class="p-1.5 rounded-md text-slate-400 hover:text-red-600 hover:bg-red-50 transition-colors">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </div>
                        @endif
                    </div>
                </template>
            </div>

            @if($canEdit)
            <div class="flex items-center gap-2">
                <input type="text" x-model="newFolderName" placeholder="New folder name…"
                       @keydown.enter.prevent="if(newFolderName.trim()) createFolder()"
                       class="flex-1 border border-slate-200 rounded-xl px-3 py-2 text-xs font-mono focus:ring-2 focus:ring-brand-500 focus:border-transparent outline-none bg-white min-h-[38px]">
                <button type="button" @click="createFolder()" :disabled="!newFolderName.trim() || creating"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-semibold bg-amber-600 hover:bg-amber-700 text-white disabled:opacity-50 min-h-[38px] transition-colors">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                    </svg>
                    New Folder
                </button>
            </div>

            {{-- Rename modal --}}
            <div x-show="renaming" x-transition class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display:none" @keydown.escape.window="renaming = null">
                <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" @click="renaming = null"></div>
                <div x-show="renaming" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                     class="relative w-full max-w-sm bg-white rounded-2xl shadow-2xl border border-slate-200 p-6" style="display:none">
                    <h3 class="text-sm font-semibold text-slate-900 mb-4">Rename</h3>
                    <input type="text" x-model="renameValue" @keydown.enter="doRename()" @keydown.escape="renaming = null"
                           x-init="$nextTick(() => { if(renaming) { $el.focus(); $el.select(); } })"
                           class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-mono focus:ring-2 focus:ring-brand-500 focus:border-transparent outline-none mb-4">
                    <div class="flex gap-2 justify-end">
                        <button type="button" @click="renaming = null" class="text-sm font-medium px-4 py-2 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-600 transition-colors">Cancel</button>
                        <button type="button" @click="doRename()" :disabled="!renameValue.trim()" class="text-sm font-medium px-4 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white transition-colors disabled:opacity-50">Save</button>
                    </div>
                </div>
            </div>

            {{-- Delete modal --}}
            <div x-show="confirmDelete" x-transition class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display:none" @keydown.escape.window="confirmDelete = null">
                <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" @click="confirmDelete = null"></div>
                <div x-show="confirmDelete" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                     class="relative w-full max-w-sm bg-white rounded-2xl shadow-2xl border border-slate-200 p-6" style="display:none">
                    <div class="flex items-start gap-4 mb-5">
                        <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-slate-900">Delete from NAS</h3>
                            <p class="text-xs text-slate-500 mt-1">Delete <span class="font-mono font-semibold text-slate-700" x-text="confirmDelete?.name"></span>? This cannot be undone.</p>
                        </div>
                    </div>
                    <div class="flex gap-2 justify-end">
                        <button type="button" @click="confirmDelete = null" class="text-sm font-medium px-4 py-2 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-600 transition-colors">Cancel</button>
                        <button type="button" @click="doDelete()" :disabled="deleteLoading"
                                class="inline-flex items-center gap-2 text-sm font-medium px-4 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-white transition-colors disabled:opacity-60">
                            <svg x-show="deleteLoading" class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                            <span x-text="deleteLoading ? 'Deleting…' : 'Delete'"></span>
                        </button>
                    </div>
                </div>
            </div>
            @endif

        </div>{{-- /browser tab --}}

        {{-- ══════════════════════════════════════════════════════════════ --}}
        {{-- CONNECTION TAB                                                  --}}
        {{-- ══════════════════════════════════════════════════════════════ --}}
        <div x-show="tab === 'connection'" class="px-6 py-5 space-y-3" style="display:none">

            {{-- No-config notice --}}
            @if(!$hasConnection)
            <div class="flex items-start gap-3 p-4 bg-amber-50 border border-amber-200 rounded-xl">
                <svg class="w-4 h-4 text-amber-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div>
                    <p class="text-xs font-semibold text-amber-800">NAS connection not configured</p>
                    <p class="text-xs text-amber-700 mt-0.5 leading-relaxed">
                        Fill in the fields below and click <strong>Test Connection</strong>.
                        Once working, persist the values in your <code class="font-mono bg-amber-100 px-1 rounded">.env</code> file.
                    </p>
                </div>
            </div>
            @endif

            {{-- ── Connection cards ── --}}
            <template x-for="(conn, idx) in connections" :key="conn._id">
                <div class="border border-slate-200 rounded-2xl overflow-hidden"
                     :class="conn.enabled ? 'border-slate-200' : 'border-slate-100 opacity-60'">

                    {{-- Card header --}}
                    <div class="flex items-center gap-3 px-4 py-3 bg-slate-50 cursor-pointer select-none"
                         @click="conn.expanded = !conn.expanded">

                        {{-- Chevron --}}
                        <svg class="w-3.5 h-3.5 text-slate-400 transition-transform duration-150 flex-shrink-0"
                             :class="conn.expanded ? 'rotate-90' : ''"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>

                        {{-- Name (editable inline) --}}
                        <input type="text" x-model="conn.name" @click.stop
                               class="flex-1 min-w-0 bg-transparent text-xs font-semibold text-slate-700 outline-none focus:bg-white focus:ring-1 focus:ring-brand-400 rounded px-1 py-0.5 -ml-1 transition-all truncate">

                        {{-- Meta badges --}}
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <span class="font-mono text-[10px] font-bold uppercase tracking-wide px-1.5 py-0.5 rounded bg-slate-200 text-slate-600"
                                  x-text="conn.protocol"></span>
                            <span class="text-[10px] text-slate-400 font-mono hidden sm:block truncate max-w-[120px]"
                                  x-text="conn.host || 'no host'" :class="conn.host ? '' : 'italic'"></span>

                            {{-- Test status dot --}}
                            <span x-show="conn.testStatus === 'ok'" class="w-2 h-2 rounded-full bg-emerald-400 flex-shrink-0" style="display:none"></span>
                            <span x-show="conn.testStatus === 'fail'" class="w-2 h-2 rounded-full bg-red-400 flex-shrink-0" style="display:none"></span>
                            <span x-show="conn.testStatus === 'testing'" class="w-2 h-2 rounded-full bg-amber-400 animate-pulse flex-shrink-0" style="display:none"></span>

                            {{-- Enable / disable toggle --}}
                            <button type="button" @click.stop="conn.enabled = !conn.enabled"
                                    :class="conn.enabled ? 'bg-emerald-500' : 'bg-slate-300'"
                                    class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors flex-shrink-0"
                                    :title="conn.enabled ? 'Disable connection' : 'Enable connection'">
                                <span :class="conn.enabled ? 'translate-x-4.5' : 'translate-x-0.5'"
                                      class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"></span>
                            </button>
                        </div>
                    </div>

                    {{-- Card body --}}
                    <div x-show="conn.expanded"
                         x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                         class="px-4 py-4 space-y-4 bg-white" style="display:none">

                        {{-- Protocol chips --}}
                        <div>
                            <label class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-2">Protocol</label>
                            <div class="flex gap-1.5 flex-wrap">
                                <template x-for="proto in ['sftp', 'ftp', 'ftps', 'smb']" :key="proto">
                                    <button type="button"
                                            @click="conn.protocol = proto;
                                                    if (proto === 'sftp')             conn.port = (conn.port === 21 || conn.port === 445 ? 22  : conn.port);
                                                    if (proto === 'ftp' || proto === 'ftps') conn.port = (conn.port === 22 || conn.port === 445 ? 21  : conn.port);
                                                    if (proto === 'smb')              conn.port = (conn.port === 22 || conn.port === 21  ? 445 : conn.port);"
                                            :class="conn.protocol === proto
                                                ? 'bg-slate-800 text-white border-slate-800'
                                                : 'bg-white text-slate-500 border-slate-200 hover:border-slate-300 hover:text-slate-700'"
                                            class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition-colors font-mono uppercase tracking-wide"
                                            x-text="proto"></button>
                                </template>
                            </div>
                        </div>

                        {{-- Host / Port / Username / Password --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">

                            <div class="sm:col-span-2">
                                <label class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Host <span class="text-red-400 normal-case font-normal">*</span></label>
                                <input type="text" x-model="conn.host" placeholder="192.168.1.100 or nas.example.com"
                                       class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-xs font-mono focus:ring-2 focus:ring-brand-500 focus:border-transparent outline-none bg-white min-h-[38px]">
                            </div>

                            <div>
                                <label class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Port</label>
                                <input type="number" x-model.number="conn.port" min="1" max="65535"
                                       class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-xs font-mono focus:ring-2 focus:ring-brand-500 focus:border-transparent outline-none bg-white min-h-[38px]">
                            </div>

                            <div>
                                <label class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Username</label>
                                <input type="text" x-model="conn.username" placeholder="admin"
                                       class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-xs font-mono focus:ring-2 focus:ring-brand-500 focus:border-transparent outline-none bg-white min-h-[38px]">
                            </div>

                            <div class="sm:col-span-2" x-data="{ showPw: false }">
                                <label class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Password</label>
                                <div class="relative">
                                    <input :type="showPw ? 'text' : 'password'" x-model="conn.password"
                                           :placeholder="conn.has_password ? 'Leave blank to use saved password' : 'Enter password'"
                                           class="w-full border border-slate-200 rounded-xl px-3 py-2.5 pr-9 text-xs font-mono focus:ring-2 focus:ring-brand-500 focus:border-transparent outline-none bg-white min-h-[38px]">
                                    <button type="button" @click="showPw = !showPw" tabindex="-1"
                                            class="absolute inset-y-0 right-0 flex items-center px-2.5 text-slate-400 hover:text-slate-600">
                                        <svg x-show="!showPw" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                        <svg x-show="showPw" class="w-3.5 h-3.5" style="display:none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 4.411m0 0L21 21"/>
                                        </svg>
                                    </button>
                                </div>
                                <p x-show="conn.has_password && !conn.password" class="text-[10px] text-slate-400 mt-1 flex items-center gap-1" style="display:none">
                                    <svg class="w-3 h-3 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Saved password will be used
                                </p>
                            </div>

                            {{-- SMB: Share + Domain --}}
                            <div x-show="conn.protocol === 'smb'" style="display:none">
                                <label class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Share <span class="text-red-400 normal-case font-normal">*</span></label>
                                <input type="text" x-model="conn.share" placeholder="media"
                                       class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-xs font-mono focus:ring-2 focus:ring-brand-500 focus:border-transparent outline-none bg-white min-h-[38px]">
                            </div>

                            <div x-show="conn.protocol === 'smb'" style="display:none">
                                <label class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Domain</label>
                                <input type="text" x-model="conn.smb_domain" placeholder="WORKGROUP"
                                       class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-xs font-mono focus:ring-2 focus:ring-brand-500 focus:border-transparent outline-none bg-white min-h-[38px]">
                            </div>

                        </div>{{-- /grid --}}

                        {{-- Subdirectory + Browser --}}
                        <div>
                            <label class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Subdirectory</label>
                            <div class="flex gap-2">
                                <input type="text" x-model="conn.subdirectory" placeholder="/media"
                                       class="flex-1 min-w-0 border border-slate-200 rounded-xl px-3 py-2.5 text-xs font-mono focus:ring-2 focus:ring-brand-500 focus:border-transparent outline-none bg-white min-h-[38px]">
                                <button type="button"
                                        @click="conn.pickerOpen ? (conn.pickerOpen = false) : pickerLoad(conn, '')"
                                        :disabled="!conn.host.trim()"
                                        :class="conn.pickerOpen ? 'bg-slate-700 text-white border-slate-700' : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300'"
                                        class="flex-shrink-0 inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-medium border transition-colors disabled:opacity-40 disabled:cursor-not-allowed min-h-[38px]">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                                    </svg>
                                    <span x-text="conn.pickerOpen ? 'Close' : 'Browse'"></span>
                                    <svg class="w-3 h-3 transition-transform duration-150" :class="conn.pickerOpen ? 'rotate-180' : ''"
                                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </button>
                            </div>

                            {{-- Inline subdirectory picker --}}
                            <div x-show="conn.pickerOpen" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                                 class="mt-2 border border-slate-200 rounded-xl overflow-hidden bg-white" style="display:none">

                                {{-- Picker toolbar --}}
                                <div class="flex items-center gap-2 px-3 py-2.5 bg-slate-50 border-b border-slate-100">
                                    {{-- Breadcrumb --}}
                                    <div class="flex items-center gap-1 flex-1 min-w-0 overflow-x-auto text-xs">
                                        <button type="button" @click="pickerLoad(conn, '')"
                                                class="text-brand-600 hover:underline font-medium flex-shrink-0">Root</button>
                                        <template x-for="(seg, si) in conn.pickerSegments" :key="si">
                                            <span class="flex items-center gap-1 flex-shrink-0">
                                                <svg class="w-3 h-3 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                                </svg>
                                                <button type="button"
                                                        @click="pickerLoad(conn, conn.pickerSegments.slice(0, si + 1).join('/'))"
                                                        class="text-brand-600 hover:underline font-medium" x-text="seg"></button>
                                            </span>
                                        </template>
                                    </div>
                                    {{-- Select current folder button --}}
                                    <button type="button"
                                            @click="pickerSelect(conn, conn.pickerPath)"
                                            class="flex-shrink-0 inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[10px] font-semibold bg-emerald-600 hover:bg-emerald-700 text-white transition-colors">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                        </svg>
                                        <span x-text="conn.pickerPath ? 'Select /' + conn.pickerPath : 'Select Root'"></span>
                                    </button>
                                    {{-- Reload --}}
                                    <button type="button" @click="pickerLoad(conn, conn.pickerPath)" :disabled="conn.pickerLoading"
                                            class="flex-shrink-0 p-1 rounded text-slate-400 hover:text-slate-600 disabled:opacity-40">
                                        <svg class="w-3.5 h-3.5" :class="conn.pickerLoading ? 'animate-spin' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                        </svg>
                                    </button>
                                </div>

                                {{-- Picker items --}}
                                <div class="max-h-48 overflow-y-auto">
                                    <div x-show="conn.pickerLoading" class="flex items-center justify-center py-8">
                                        <svg class="w-4 h-4 animate-spin text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                        </svg>
                                    </div>
                                    <div x-show="conn.pickerError && !conn.pickerLoading"
                                         class="px-3 py-2 text-xs text-red-600 bg-red-50" x-text="conn.pickerError" style="display:none"></div>
                                    <div x-show="!conn.pickerLoading && !conn.pickerError && conn.pickerItems.length === 0"
                                         class="text-center py-6 text-xs text-slate-400" style="display:none">No subdirectories found</div>
                                    <template x-for="pitem in conn.pickerItems" :key="pitem.path">
                                        <div class="group flex items-center gap-2 px-3 py-2 border-b border-slate-100 last:border-0 hover:bg-sky-50 cursor-pointer transition-colors"
                                             @click="pickerLoad(conn, pitem.path)">
                                            <svg class="w-4 h-4 text-amber-400 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/>
                                            </svg>
                                            <span class="flex-1 text-xs font-mono text-slate-700 group-hover:text-brand-600 transition-colors" x-text="pitem.name"></span>
                                            <div class="flex items-center gap-2 flex-shrink-0">
                                                <button type="button" @click.stop="pickerSelect(conn, pitem.path)"
                                                        class="opacity-0 group-hover:opacity-100 inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-700 hover:bg-emerald-200 transition-all">
                                                    Select
                                                </button>
                                                <svg class="w-3 h-3 text-slate-300 group-hover:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                                </svg>
                                            </div>
                                        </div>
                                    </template>
                                </div>

                            </div>{{-- /picker --}}
                            <p class="text-[10px] text-slate-400 mt-1.5">Starting directory on the NAS. Leave <code class="font-mono bg-slate-100 px-0.5 rounded">/</code> to use the root.</p>
                        </div>

                        {{-- Test result --}}
                        <div x-show="conn.testStatus !== null" x-transition style="display:none"
                             class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-xs font-medium border"
                             :class="{
                                 'bg-emerald-50 border-emerald-200 text-emerald-800': conn.testStatus === 'ok',
                                 'bg-red-50 border-red-200 text-red-800':             conn.testStatus === 'fail',
                                 'bg-slate-50 border-slate-200 text-slate-600':       conn.testStatus === 'testing',
                             }">
                            <svg x-show="conn.testStatus === 'testing'" class="w-3.5 h-3.5 animate-spin flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                            <svg x-show="conn.testStatus === 'ok'" class="w-3.5 h-3.5 text-emerald-500 flex-shrink-0" style="display:none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                            </svg>
                            <svg x-show="conn.testStatus === 'fail'" class="w-3.5 h-3.5 text-red-500 flex-shrink-0" style="display:none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                            <span x-text="conn.testStatus === 'testing' ? 'Testing connection…' : conn.testMessage"></span>
                        </div>

                        {{-- Card actions --}}
                        <div class="flex items-center justify-between gap-3 pt-1 border-t border-slate-100">
                            <button type="button" x-show="connections.length > 1" @click="removeConnection(conn._id)"
                                    class="inline-flex items-center gap-1.5 text-xs text-slate-400 hover:text-red-600 transition-colors" style="display:none">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                                Remove
                            </button>
                            <div x-show="connections.length <= 1"></div>{{-- spacer --}}
                            <div class="flex items-center gap-2">
                                <p class="text-[10px] text-slate-400 hidden sm:block">
                                    Persist in <code class="font-mono bg-slate-100 px-0.5 rounded">.env</code>:
                                    <code class="font-mono bg-slate-100 px-0.5 rounded">NAS_HOST</code>
                                    <code class="font-mono bg-slate-100 px-0.5 rounded">NAS_USERNAME</code>
                                    <code class="font-mono bg-slate-100 px-0.5 rounded">NAS_PASSWORD</code>
                                </p>
                                <button type="button" @click="testConn(conn)"
                                        :disabled="!conn.host.trim() || conn.testStatus === 'testing'"
                                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-semibold bg-sky-600 hover:bg-sky-700 text-white disabled:opacity-50 min-h-[36px] transition-colors">
                                    <svg class="w-3.5 h-3.5" :class="conn.testStatus === 'testing' ? 'animate-spin' : ''"
                                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                    </svg>
                                    <span x-text="conn.testStatus === 'testing' ? 'Testing…' : 'Test Connection'"></span>
                                </button>
                            </div>
                        </div>

                    </div>{{-- /card body --}}
                </div>{{-- /card --}}
            </template>

            {{-- Add connection --}}
            <button type="button" @click="addConnection()"
                    class="w-full flex items-center justify-center gap-2 px-4 py-3 rounded-2xl border border-dashed border-slate-300 text-xs font-medium text-slate-500 hover:border-brand-400 hover:text-brand-600 hover:bg-brand-50 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Add Connection
            </button>

        </div>{{-- /connection tab --}}

    </div>{{-- /accordion body --}}
</div>

<script>
function nasFmComponent(nodes, connections) {
    return {
        tab:   (connections || []).some(c => c.host) ? 'schema' : 'connection',
        nodes: nodes || [],

        // Connections
        connections: connections || [],
        nextId:      (connections || []).length + 1,

        // Live browser
        items:         [],
        currentPath:   '',
        segments:      [],
        loading:       false,
        error:         '',
        toast:         null,
        toastTimer:    null,
        renaming:      null,
        renameValue:   '',
        confirmDelete: null,
        deleteLoading: false,
        newFolderName: '',
        creating:      false,

        // ── Connection management ────────────────────────────────────────

        addConnection() {
            this.connections.push({
                _id:            this.nextId++,
                name:           'New Connection',
                enabled:        true,
                expanded:       true,
                protocol:       'sftp',
                host:           '',
                port:           22,
                username:       '',
                password:       '',
                has_password:   false,
                share:          '',
                smb_domain:     '',
                subdirectory:   '/media',
                testStatus:     null,
                testMessage:    '',
                pickerOpen:     false,
                pickerItems:    [],
                pickerPath:     '',
                pickerSegments: [],
                pickerLoading:  false,
                pickerError:    '',
            });
        },

        removeConnection(id) {
            this.connections = this.connections.filter(c => c._id !== id);
        },

        async testConn(conn) {
            if (!conn.host.trim()) return;
            conn.testStatus  = 'testing';
            conn.testMessage = '';
            const body = {
                protocol:  conn.protocol,
                host:      conn.host,
                port:      conn.port,
                username:  conn.username,
                base_path: conn.subdirectory || '/',
            };
            if (conn.password)   body.password   = conn.password;
            if (conn.share)      body.smb_share   = conn.share;
            if (conn.smb_domain) body.smb_domain  = conn.smb_domain;
            try {
                const r = await fetch('{{ route("nas-fm.test") }}', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrfToken() },
                    body:    JSON.stringify(body),
                });
                const d = await r.json();
                conn.testStatus  = d.success ? 'ok' : 'fail';
                conn.testMessage = d.message || (d.success ? 'Connection successful.' : 'Connection failed.');
            } catch (e) {
                conn.testStatus  = 'fail';
                conn.testMessage = 'Request error: ' + e.message;
            }
        },

        // ── Subdirectory picker ──────────────────────────────────────────

        async pickerLoad(conn, path) {
            conn.pickerOpen    = true;
            conn.pickerLoading = true;
            conn.pickerError   = '';
            conn.pickerPath    = path;
            conn.pickerSegments = path ? path.split('/').filter(Boolean) : [];

            const body = {
                path:      path,
                base_path: '/',     // browse from NAS root, not the configured subdirectory
                protocol:  conn.protocol,
                host:      conn.host,
                port:      conn.port,
                username:  conn.username,
            };
            if (conn.password)   body.password   = conn.password;
            if (conn.share)      body.smb_share   = conn.share;
            if (conn.smb_domain) body.smb_domain  = conn.smb_domain;

            try {
                const r = await fetch('{{ route("nas-fm.list-items") }}', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrfToken() },
                    body:    JSON.stringify(body),
                });
                const d = await r.json();
                conn.pickerLoading = false;
                if (d.success) {
                    conn.pickerItems = (d.items || []).filter(i => i.type === 'dir');
                } else {
                    conn.pickerError = d.message || 'Failed to load.';
                    conn.pickerItems = [];
                }
            } catch (e) {
                conn.pickerLoading = false;
                conn.pickerError   = 'Request error: ' + e.message;
            }
        },

        pickerSelect(conn, path) {
            conn.subdirectory   = path ? ('/' + path.replace(/^\/+/, '')) : '/';
            conn.pickerOpen     = false;
            conn.pickerItems    = [];
            conn.pickerPath     = '';
            conn.pickerSegments = [];
        },

        // ── Live browser ─────────────────────────────────────────────────

        async load(path) {
            this.loading     = true;
            this.error       = '';
            this.currentPath = path;
            this.segments    = path ? path.split('/').filter(Boolean) : [];
            const r = await fetch('{{ route("nas-fm.list-items") }}', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrfToken() },
                body:    JSON.stringify({ path }),
            });
            const d = await r.json();
            this.loading = false;
            if (d.success) {
                this.items = d.items || [];
            } else {
                this.error = d.message || 'Failed to load directory.';
                this.items = [];
            }
        },

        startRename(item) {
            this.renaming    = item;
            this.renameValue = item.name;
        },

        async doRename() {
            if (!this.renaming || !this.renameValue.trim()) return;
            const r = await fetch('{{ route("nas-fm.rename") }}', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrfToken() },
                body:    JSON.stringify({ path: this.renaming.path, name: this.renameValue.trim() }),
            });
            const d = await r.json();
            if (d.success) {
                this.notify('Renamed to ' + this.renameValue.trim(), 'success');
                this.renaming = null;
                this.load(this.currentPath);
            } else {
                this.notify(d.message, 'error');
            }
        },

        async doDelete() {
            if (!this.confirmDelete) return;
            this.deleteLoading = true;
            const r = await fetch('{{ route("nas-fm.delete") }}', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrfToken() },
                body:    JSON.stringify({ path: this.confirmDelete.path, type: this.confirmDelete.type }),
            });
            const d = await r.json();
            this.deleteLoading = false;
            if (d.success) {
                this.notify('Deleted from NAS', 'success');
                this.confirmDelete = null;
                this.load(this.currentPath);
            } else {
                this.notify(d.message, 'error');
            }
        },

        async createFolder() {
            const name = this.newFolderName.trim();
            if (!name) return;
            this.creating = true;
            const path = this.currentPath ? this.currentPath + '/' + name : name;
            const r = await fetch('{{ route("nas-fm.create") }}', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrfToken() },
                body:    JSON.stringify({ path, type: 'dir' }),
            });
            const d = await r.json();
            this.creating = false;
            if (d.success) {
                this.newFolderName = '';
                this.notify('Folder created', 'success');
                this.load(this.currentPath);
            } else {
                this.notify(d.message, 'error');
            }
        },

        // ── Helpers ──────────────────────────────────────────────────────

        formatSize(bytes) {
            if (bytes < 1024)       return bytes + ' B';
            if (bytes < 1048576)    return (bytes / 1024).toFixed(1) + ' KB';
            if (bytes < 1073741824) return (bytes / 1048576).toFixed(1) + ' MB';
            return (bytes / 1073741824).toFixed(2) + ' GB';
        },

        notify(msg, type) {
            clearTimeout(this.toastTimer);
            this.toast      = { msg, type };
            this.toastTimer = setTimeout(() => this.toast = null, 4000);
        },

        csrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
        },
    };
}
</script>
