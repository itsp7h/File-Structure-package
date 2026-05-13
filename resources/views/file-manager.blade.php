{{--
  NAS File Manager Component
  --------------------------
  Usage:
    @include('nas-file-manager::file-manager', [
        'nodes'   => $treeNodes,   // array of node arrays (see below)
        'canEdit' => true,         // show create/rename/delete actions
        'title'   => 'Folder Structure & File Manager',  // optional
    ])

  Node array shape:
    ['id' => int, 'depth' => int, 'label' => string, 'path' => string,
     'parent_path' => string|null, 'is_template' => bool, 'can_edit' => bool]

  Routes used (registered by NasFileManagerServiceProvider):
    nas-fm.list-items  POST
    nas-fm.create      POST
    nas-fm.rename      POST
    nas-fm.delete      POST
    nas-fm.test        POST
--}}

@php
    $nodes   = $nodes   ?? config('nas-file-manager.schema', []);
    $canEdit = $canEdit ?? (config('nas-file-manager.edit_gate') === null || \Illuminate\Support\Facades\Gate::allows(config('nas-file-manager.edit_gate')));
    $title   = $title   ?? 'Folder Structure & File Manager';

    $conn    = config('nas-file-manager.connection', []);
    $hasConnection = !empty($conn['host']);
    $connConfig = [
        'protocol'     => $conn['protocol']   ?? 'sftp',
        'host'         => $conn['host']        ?? '',
        'port'         => (int) ($conn['port'] ?? 22),
        'username'     => $conn['username']    ?? '',
        'path'         => $conn['path']        ?? '/media',
        'smb_share'    => $conn['smb_share']   ?? '',
        'smb_domain'   => $conn['smb_domain']  ?? '',
        'has_password' => !empty($conn['password']),
    ];
@endphp

<div x-data="{ accordionOpen: {{ $hasConnection ? 'false' : 'true' }}, ...nasFmComponent(@js($nodes), @js($connConfig)) }"
     class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">

    {{-- Accordion header --}}
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

        {{-- Tab switcher --}}
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
                    :class="tab === 'connection'
                        ? 'bg-sky-600 text-white'
                        : 'text-slate-500 hover:bg-slate-100 hover:text-slate-700'"
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

        {{-- ── SCHEMA TAB ── --}}
        <div x-show="tab === 'schema'" class="px-6 py-5" style="display:none">
            <p class="text-xs text-slate-500 mb-4">
                All paths are relative to your configured remote path.
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
                    <p class="text-slate-400 italic">No schema defined. Set nodes in config or pass them to the component.</p>
                </template>
            </div>
        </div>

        {{-- ── LIVE BROWSER TAB ── --}}
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
                <button type="button" @click="load(currentPath)"
                        :disabled="loading"
                        class="flex-shrink-0 p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors disabled:opacity-40">
                    <svg class="w-3.5 h-3.5" :class="loading ? 'animate-spin' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                </button>
            </div>

            {{-- Error --}}
            <div x-show="error && !loading" class="p-3 bg-red-50 border border-red-100 rounded-xl text-xs text-red-700" x-text="error" style="display:none"></div>

            {{-- File / folder list --}}
            <div class="rounded-xl border border-slate-200 overflow-hidden bg-white select-none">
                <div x-show="loading" class="flex items-center justify-center py-10">
                    <svg class="w-5 h-5 animate-spin text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                </div>
                <div x-show="!loading && !error && items.length === 0" class="text-center py-10 text-xs text-slate-400" style="display:none">
                    Empty folder
                </div>
                <template x-for="item in items" :key="item.path">
                    <div class="group flex items-center gap-2.5 px-4 py-2.5 border-b border-slate-100 last:border-0 hover:bg-sky-50/60 transition-colors">

                        {{-- Icon --}}
                        <svg x-show="item.type === 'dir'" class="w-4 h-4 text-amber-400 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/>
                        </svg>
                        <svg x-show="item.type !== 'dir'" class="w-4 h-4 text-slate-300 flex-shrink-0" style="display:none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                        </svg>

                        {{-- Name --}}
                        <button type="button"
                                @click="item.type === 'dir' ? load(item.path) : null"
                                :class="item.type === 'dir' ? 'hover:text-brand-600 cursor-pointer' : 'cursor-default text-slate-500'"
                                class="flex-1 text-left text-xs font-mono text-slate-700 transition-colors truncate"
                                x-text="item.name"></button>

                        {{-- Size (files only) --}}
                        <span x-show="item.type !== 'dir' && item.size > 0"
                              class="text-[10px] text-slate-400 flex-shrink-0 font-mono"
                              x-text="formatSize(item.size)" style="display:none"></span>

                        {{-- Actions (canEdit only) --}}
                        @if($canEdit)
                        <div class="hidden group-hover:flex items-center gap-1 flex-shrink-0">
                            {{-- Rename --}}
                            <button type="button" @click.stop="startRename(item)"
                                    title="Rename"
                                    class="p-1.5 rounded-md text-slate-400 hover:text-brand-600 hover:bg-brand-50 transition-colors">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                            </button>
                            {{-- Delete --}}
                            <button type="button" @click.stop="confirmDelete = item"
                                    title="Delete"
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
            {{-- New folder row --}}
            <div class="flex items-center gap-2">
                <input type="text" x-model="newFolderName"
                       placeholder="New folder name…"
                       @keydown.enter.prevent="if(newFolderName.trim()) createFolder()"
                       class="flex-1 border border-slate-200 rounded-xl px-3 py-2 text-xs font-mono focus:ring-2 focus:ring-brand-500 focus:border-transparent outline-none bg-white min-h-[38px]">
                <button type="button" @click="createFolder()"
                        :disabled="!newFolderName.trim() || creating"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-semibold bg-amber-600 hover:bg-amber-700 text-white disabled:opacity-50 min-h-[38px] transition-colors">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                    </svg>
                    New Folder
                </button>
            </div>

            {{-- Rename modal --}}
            <div x-show="renaming" x-transition
                 class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display:none"
                 @keydown.escape.window="renaming = null">
                <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" @click="renaming = null"></div>
                <div x-show="renaming"
                     x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                     class="relative w-full max-w-sm bg-white rounded-2xl shadow-2xl border border-slate-200 p-6" style="display:none">
                    <h3 class="text-sm font-semibold text-slate-900 mb-4">Rename</h3>
                    <input type="text" x-model="renameValue"
                           @keydown.enter="doRename()" @keydown.escape="renaming = null"
                           x-init="$nextTick(() => { if(renaming) { $el.focus(); $el.select(); } })"
                           class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-mono focus:ring-2 focus:ring-brand-500 focus:border-transparent outline-none mb-4">
                    <div class="flex gap-2 justify-end">
                        <button type="button" @click="renaming = null"
                                class="text-sm font-medium px-4 py-2 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-600 transition-colors">Cancel</button>
                        <button type="button" @click="doRename()" :disabled="!renameValue.trim()"
                                class="text-sm font-medium px-4 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white transition-colors disabled:opacity-50">Save</button>
                    </div>
                </div>
            </div>

            {{-- Delete confirmation modal --}}
            <div x-show="confirmDelete" x-transition
                 class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display:none"
                 @keydown.escape.window="confirmDelete = null">
                <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" @click="confirmDelete = null"></div>
                <div x-show="confirmDelete"
                     x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                     class="relative w-full max-w-sm bg-white rounded-2xl shadow-2xl border border-slate-200 p-6" style="display:none">
                    <div class="flex items-start gap-4 mb-5">
                        <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-slate-900">Delete from NAS</h3>
                            <p class="text-xs text-slate-500 mt-1">
                                Delete <span class="font-mono font-semibold text-slate-700" x-text="confirmDelete?.name"></span>?
                                This cannot be undone.
                            </p>
                        </div>
                    </div>
                    <div class="flex gap-2 justify-end">
                        <button type="button" @click="confirmDelete = null"
                                class="text-sm font-medium px-4 py-2 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-600 transition-colors">Cancel</button>
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

        {{-- ── CONNECTION TAB ── --}}
        <div x-show="tab === 'connection'" class="px-6 py-5 space-y-5" style="display:none">

            {{-- No-config notice (shown only when NAS_HOST is blank) --}}
            @if(!$hasConnection)
            <div class="flex items-start gap-3 p-4 bg-amber-50 border border-amber-200 rounded-xl">
                <svg class="w-4 h-4 text-amber-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div>
                    <p class="text-xs font-semibold text-amber-800">NAS connection not configured</p>
                    <p class="text-xs text-amber-700 mt-0.5 leading-relaxed">
                        Fill in the fields below and click <strong>Test Connection</strong> to verify.
                        Once working, add the values to your <code class="font-mono bg-amber-100 px-1 rounded">.env</code> file to persist them.
                    </p>
                </div>
            </div>
            @endif

            {{-- Connection status result --}}
            <div x-show="connStatus !== null" x-transition style="display:none"
                 class="flex items-center gap-2.5 px-4 py-3 rounded-xl text-xs font-medium border"
                 :class="{
                     'bg-emerald-50 border-emerald-200 text-emerald-800': connStatus === 'ok',
                     'bg-red-50 border-red-200 text-red-800':             connStatus === 'fail',
                     'bg-slate-50 border-slate-200 text-slate-600':       connStatus === 'testing',
                 }">
                <svg x-show="connStatus === 'testing'" class="w-4 h-4 animate-spin flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                <svg x-show="connStatus === 'ok'" class="w-4 h-4 text-emerald-500 flex-shrink-0" style="display:none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
                <svg x-show="connStatus === 'fail'" class="w-4 h-4 text-red-500 flex-shrink-0" style="display:none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                </svg>
                <span x-text="connStatus === 'testing' ? 'Testing connection…' : connMessage"></span>
            </div>

            {{-- Protocol selector --}}
            <div>
                <label class="block text-xs font-medium text-slate-700 mb-2">Protocol</label>
                <div class="flex gap-1.5 flex-wrap">
                    <template x-for="proto in ['sftp', 'ftp', 'ftps', 'smb']" :key="proto">
                        <button type="button" @click="connProtocol = proto; if(proto === 'sftp') connPort = (connPort === 21 || connPort === 445 ? 22 : connPort); if(proto === 'ftp' || proto === 'ftps') connPort = (connPort === 22 || connPort === 445 ? 21 : connPort); if(proto === 'smb') connPort = (connPort === 22 || connPort === 21 ? 445 : connPort);"
                                :class="connProtocol === proto
                                    ? 'bg-slate-800 text-white border-slate-800'
                                    : 'bg-white text-slate-500 border-slate-200 hover:border-slate-300 hover:text-slate-700'"
                                class="px-3.5 py-1.5 rounded-lg text-xs font-semibold border transition-colors font-mono uppercase tracking-wide min-h-[32px]"
                                x-text="proto"></button>
                    </template>
                </div>
            </div>

            {{-- Fields --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">

                {{-- Host --}}
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-slate-700 mb-1.5">
                        Host <span class="text-red-400">*</span>
                    </label>
                    <input type="text" x-model="connHost"
                           placeholder="192.168.1.100 or nas.example.com"
                           class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-xs font-mono focus:ring-2 focus:ring-brand-500 focus:border-transparent outline-none bg-white min-h-[38px] transition-shadow"
                           :class="connHost.trim() === '' && connStatus !== null ? 'border-red-300 bg-red-50' : ''">
                    <p x-show="connHost.trim() === '' && connStatus !== null" class="text-[10px] text-red-500 mt-1">Host is required.</p>
                </div>

                {{-- Port --}}
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1.5">Port</label>
                    <input type="number" x-model.number="connPort" min="1" max="65535"
                           class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-xs font-mono focus:ring-2 focus:ring-brand-500 focus:border-transparent outline-none bg-white min-h-[38px] transition-shadow">
                </div>

                {{-- Username --}}
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1.5">Username</label>
                    <input type="text" x-model="connUsername" placeholder="admin"
                           class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-xs font-mono focus:ring-2 focus:ring-brand-500 focus:border-transparent outline-none bg-white min-h-[38px] transition-shadow">
                </div>

                {{-- Password --}}
                <div class="sm:col-span-2" x-data="{ showPw: false }">
                    <label class="block text-xs font-medium text-slate-700 mb-1.5">Password</label>
                    <div class="relative">
                        <input :type="showPw ? 'text' : 'password'"
                               x-model="connPassword"
                               :placeholder="connHasSavedPassword ? 'Leave blank to use saved password' : 'Enter password'"
                               class="w-full border border-slate-200 rounded-xl px-3 py-2.5 pr-9 text-xs font-mono focus:ring-2 focus:ring-brand-500 focus:border-transparent outline-none bg-white min-h-[38px] transition-shadow">
                        <button type="button" @click="showPw = !showPw" tabindex="-1"
                                class="absolute inset-y-0 right-0 flex items-center px-2.5 text-slate-400 hover:text-slate-600 transition-colors">
                            <svg x-show="!showPw" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <svg x-show="showPw" class="w-3.5 h-3.5" style="display:none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 4.411m0 0L21 21"/>
                            </svg>
                        </button>
                    </div>
                    <p x-show="connHasSavedPassword && !connPassword" class="text-[10px] text-slate-400 mt-1" style="display:none">
                        <svg class="w-3 h-3 inline-block mr-0.5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:inline">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg>
                        Saved password will be used
                    </p>
                </div>

                {{-- Base path --}}
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-slate-700 mb-1.5">Base Path</label>
                    <input type="text" x-model="connPath" placeholder="/media"
                           class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-xs font-mono focus:ring-2 focus:ring-brand-500 focus:border-transparent outline-none bg-white min-h-[38px] transition-shadow">
                    <p class="text-[10px] text-slate-400 mt-1">Remote directory the file browser starts from.</p>
                </div>

                {{-- SMB Share (smb only) --}}
                <div x-show="connProtocol === 'smb'" style="display:none">
                    <label class="block text-xs font-medium text-slate-700 mb-1.5">SMB Share</label>
                    <input type="text" x-model="connSmbShare" placeholder="media"
                           class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-xs font-mono focus:ring-2 focus:ring-brand-500 focus:border-transparent outline-none bg-white min-h-[38px] transition-shadow">
                </div>

                {{-- SMB Domain (smb only) --}}
                <div x-show="connProtocol === 'smb'" style="display:none">
                    <label class="block text-xs font-medium text-slate-700 mb-1.5">SMB Domain</label>
                    <input type="text" x-model="connSmbDomain" placeholder="WORKGROUP"
                           class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-xs font-mono focus:ring-2 focus:ring-brand-500 focus:border-transparent outline-none bg-white min-h-[38px] transition-shadow">
                </div>

            </div>{{-- /fields grid --}}

            {{-- Actions row --}}
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-1 border-t border-slate-100">
                {{-- Env hint --}}
                <p class="text-xs text-slate-400 leading-relaxed">
                    Persist settings in <code class="font-mono bg-slate-100 px-1 py-0.5 rounded text-slate-600 text-[10px]">.env</code>:
                    <code class="font-mono bg-slate-100 px-1 py-0.5 rounded text-slate-600 text-[10px]">NAS_HOST</code>
                    <code class="font-mono bg-slate-100 px-1 py-0.5 rounded text-slate-600 text-[10px]">NAS_USERNAME</code>
                    <code class="font-mono bg-slate-100 px-1 py-0.5 rounded text-slate-600 text-[10px]">NAS_PASSWORD</code>
                </p>
                {{-- Test button --}}
                <button type="button" @click="testConn()"
                        :disabled="!connHost.trim() || connStatus === 'testing'"
                        class="flex-shrink-0 inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-xs font-semibold bg-sky-600 hover:bg-sky-700 text-white disabled:opacity-50 min-h-[38px] transition-colors">
                    <svg class="w-3.5 h-3.5" :class="connStatus === 'testing' ? 'animate-spin' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path x-show="connStatus !== 'testing'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        <path x-show="connStatus === 'testing'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" style="display:none"/>
                    </svg>
                    <span x-text="connStatus === 'testing' ? 'Testing…' : 'Test Connection'"></span>
                </button>
            </div>

        </div>{{-- /connection tab --}}

    </div>{{-- /accordion body --}}
</div>

<script>
function nasFmComponent(nodes, connConfig) {
    return {
        tab: connConfig?.host ? 'schema' : 'connection',
        nodes: nodes || [],

        // Live browser state
        items:         [],
        currentPath:   '',
        segments:      [],
        loading:       false,
        error:         '',

        // Toast
        toast:         null,
        toastTimer:    null,

        // Actions
        renaming:      null,
        renameValue:   '',
        confirmDelete: null,
        deleteLoading: false,
        newFolderName: '',
        creating:      false,

        // Connection form
        connProtocol:         connConfig?.protocol     || 'sftp',
        connHost:             connConfig?.host          || '',
        connPort:             connConfig?.port          || 22,
        connUsername:         connConfig?.username      || '',
        connPassword:         '',
        connPath:             connConfig?.path          || '/media',
        connSmbShare:         connConfig?.smb_share     || '',
        connSmbDomain:        connConfig?.smb_domain    || '',
        connHasSavedPassword: connConfig?.has_password  || false,
        connStatus:           null,
        connMessage:          '',

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

        async testConn() {
            if (!this.connHost.trim()) return;
            this.connStatus  = 'testing';
            this.connMessage = '';
            const body = {
                protocol: this.connProtocol,
                host:     this.connHost,
                port:     this.connPort,
                username: this.connUsername,
                path:     this.connPath,
            };
            if (this.connPassword)  body.password   = this.connPassword;
            if (this.connSmbShare)  body.smb_share   = this.connSmbShare;
            if (this.connSmbDomain) body.smb_domain  = this.connSmbDomain;
            try {
                const r = await fetch('{{ route("nas-fm.test") }}', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrfToken() },
                    body:    JSON.stringify(body),
                });
                const d = await r.json();
                this.connStatus  = d.success ? 'ok' : 'fail';
                this.connMessage = d.message || (d.success ? 'Connection successful.' : 'Connection failed.');
            } catch (e) {
                this.connStatus  = 'fail';
                this.connMessage = 'Request error: ' + e.message;
            }
        },

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
