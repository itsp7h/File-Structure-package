<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nas_fm_connections', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('NAS Connection');
            $table->boolean('enabled')->default(true);
            $table->string('protocol', 10)->default('sftp');
            $table->string('host')->default('');
            $table->unsignedSmallInteger('port')->default(22);
            $table->string('username')->default('');
            $table->text('password')->nullable();
            $table->string('share')->default('');
            $table->string('smb_domain')->default('');
            $table->string('subdirectory')->default('/media');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nas_fm_connections');
    }
};
