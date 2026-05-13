<?php

return [

    /*
    |--------------------------------------------------------------------------
    | NAS Connection
    |--------------------------------------------------------------------------
    | Protocol: sftp | ftp | ftps | smb
    */
    'connection' => [
        'protocol'   => env('NAS_PROTOCOL',   'sftp'),
        'host'       => env('NAS_HOST',        ''),
        'port'       => (int) env('NAS_PORT',  22),
        'username'   => env('NAS_USERNAME',    ''),
        'password'   => env('NAS_PASSWORD',    ''),
        'path'       => env('NAS_PATH',        '/media'),
        'smb_share'  => env('NAS_SMB_SHARE',   ''),
        'smb_domain' => env('NAS_SMB_DOMAIN',  ''),
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
    | Example: 'edit-nas' (checked with Gate::allows())
    */
    'edit_gate' => null,

    /*
    |--------------------------------------------------------------------------
    | Folder Schema
    |--------------------------------------------------------------------------
    | Static schema shown in the "Schema" tab.
    | Each node: depth (int), label (string), path (string),
    |            parent_path (?string), is_template (bool), can_edit (bool)
    |
    | You can also pass $nodes dynamically to the Blade component:
    |   <x-nas-file-manager::file-manager :nodes="$myNodes" />
    */
    'schema' => [
        // Example — uncomment and adapt:
        // ['depth' => 0, 'label' => 'Media',   'path' => 'Media',          'parent_path' => null,    'is_template' => false, 'can_edit' => false],
        // ['depth' => 1, 'label' => 'Outlets', 'path' => 'Media/Outlets',  'parent_path' => 'Media', 'is_template' => false, 'can_edit' => true],
    ],

];
