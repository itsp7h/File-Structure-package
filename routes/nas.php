<?php

use Illuminate\Support\Facades\Route;
use P7H\NasFileManager\Http\Controllers\NasFileManagerController;

Route::prefix(config('nas-file-manager.route_prefix', 'nas-file-manager'))
    ->middleware(config('nas-file-manager.middleware', ['web', 'auth']))
    ->name('nas-fm.')
    ->group(function () {
        Route::post('test',               [NasFileManagerController::class, 'test'])->name('test');
        Route::post('shares',             [NasFileManagerController::class, 'shares'])->name('shares');
        Route::post('list-items',         [NasFileManagerController::class, 'listItems'])->name('list-items');
        Route::post('create',             [NasFileManagerController::class, 'create'])->name('create');
        Route::post('rename',             [NasFileManagerController::class, 'rename'])->name('rename');
        Route::post('delete',             [NasFileManagerController::class, 'delete'])->name('delete');
        Route::post('connections/save',   [NasFileManagerController::class, 'saveConnection'])->name('connections.save');
    });
