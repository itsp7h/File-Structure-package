<?php

namespace P7H\NasFileManager\Models;

use Illuminate\Database\Eloquent\Model;

class NasConnection extends Model
{
    protected $table = 'nas_fm_connections';

    protected $fillable = [
        'name', 'enabled', 'protocol', 'host', 'port',
        'username', 'share', 'smb_domain', 'subdirectory', 'sort_order',
    ];

    protected $casts = [
        'enabled'    => 'boolean',
        'port'       => 'integer',
        'sort_order' => 'integer',
    ];

    // Never expose the raw encrypted password in serialization
    protected $hidden = ['password'];

    public function setPasswordAttribute(?string $value): void
    {
        $this->attributes['password'] = $value ? encrypt($value) : null;
    }

    public function getDecryptedPassword(): ?string
    {
        try {
            return $this->attributes['password'] ? decrypt($this->attributes['password']) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function hasPassword(): bool
    {
        return ! empty($this->attributes['password']);
    }

    /**
     * Return all DB connections mapped to the same shape the config uses,
     * or null if the table does not exist yet.
     */
    public static function allAsConfig(): ?array
    {
        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('nas_fm_connections')) {
                return null;
            }

            $rows = static::orderBy('sort_order')->orderBy('id')->get();

            if ($rows->isEmpty()) {
                return null;
            }

            return $rows->map(fn(self $row) => [
                'db_id'        => $row->id,
                'name'         => $row->name,
                'enabled'      => $row->enabled,
                'protocol'     => $row->protocol,
                'host'         => $row->host,
                'port'         => $row->port,
                'username'     => $row->username,
                'password'     => $row->getDecryptedPassword(),
                'share'        => $row->share,
                'smb_domain'   => $row->smb_domain,
                'subdirectory' => $row->subdirectory,
            ])->all();
        } catch (\Throwable) {
            return null;
        }
    }
}
