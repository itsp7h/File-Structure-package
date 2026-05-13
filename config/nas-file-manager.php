<?php

return [

    /*
    |--------------------------------------------------------------------------
    | NAS Connections
    |--------------------------------------------------------------------------
    | Define one or more NAS connections. The first enabled connection is used
    | by the Live Browser tab. Additional connections can be added by
    | publishing this config and appending entries to the array.
    |
    | Supported protocols: sftp | ftp | ftps | smb
    */
    'connections' => [
        [
            'name'         => 'Primary NAS',
            'enabled'      => (bool) env('NAS_ENABLED',   true),
            'protocol'     => env('NAS_PROTOCOL',          'sftp'),
            'host'         => env('NAS_HOST',               ''),
            'port'         => (int) env('NAS_PORT',         22),
            'username'     => env('NAS_USERNAME',           ''),
            'password'     => env('NAS_PASSWORD',           ''),
            'share'        => env('NAS_SMB_SHARE',          ''),
            'smb_domain'   => env('NAS_SMB_DOMAIN',         ''),
            'subdirectory' => env('NAS_PATH',               '/media'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Routing
    |--------------------------------------------------------------------------
    */
    'route_prefix' => env('NAS_FM_ROUTE_PREFIX', 'nas-file-manager'),
    'middleware'   => ['web', 'auth'],

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    | Gate or permission name that controls create / rename / delete actions.
    | Set to null to allow any authenticated user.
    */
    'edit_gate' => null,

    /*
    |--------------------------------------------------------------------------
    | Folder Schema
    |--------------------------------------------------------------------------
    | Static schema shown in the "Schema" tab.
    | Each node: depth (int), label (string), path (string),
    |            parent_path (?string), is_template (bool), can_edit (bool)
    */
    'schema' => [
        // ['depth' => 0, 'label' => 'Media',   'path' => 'Media',          'parent_path' => null,    'is_template' => false, 'can_edit' => false],
        // ['depth' => 1, 'label' => 'Outlets', 'path' => 'Media/Outlets',  'parent_path' => 'Media', 'is_template' => false, 'can_edit' => true],
    ],

];
