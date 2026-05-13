# p7h/nas-file-manager

A Laravel package that embeds a NAS file manager directly into your Blade views. Supports SFTP, FTP, FTPS, and SMB protocols. Includes a built-in Connection tab for first-time setup with no configuration required to get started.

---

## Requirements

- PHP 8.1+
- Laravel 10, 11, or 12
- For SMB: `smbclient` installed on the server
- For SFTP rename: `sshpass` installed on the server

---

## Installation

```bash
composer require p7h/nas-file-manager
```

Laravel auto-discovers the service provider. No manual registration needed.

---

## Quick Start

Include the component anywhere inside an authenticated Blade view:

```blade
@include('nas-file-manager::file-manager')
```

That's it. If `NAS_HOST` is not set in your `.env`, the component opens automatically to the **Connection tab** where you can enter and test your credentials before saving them.

---

## Configuration

### 1. Publish the config (optional)

```bash
php artisan vendor:publish --tag=nas-file-manager-config
```

This copies `config/nas-file-manager.php` into your project so you can customise it.

### 2. Add to `.env`

```env
NAS_PROTOCOL=sftp          # sftp | ftp | ftps | smb  (default: sftp)
NAS_HOST=192.168.1.100     # IP address or hostname
NAS_PORT=22                # 22=sftp, 21=ftp/ftps, 445=smb
NAS_USERNAME=admin
NAS_PASSWORD=secret
NAS_PATH=/media            # Base remote directory (default: /media)

# SMB only
NAS_SMB_SHARE=media
NAS_SMB_DOMAIN=WORKGROUP
```

---

## Connection Tab

The component includes a **Connection** tab alongside Schema and Live Browser. It is designed to guide first-time setup without requiring any documentation.

### First install — no `.env` set

When `NAS_HOST` is empty the component:

- **Auto-expands** the accordion on page load
- **Pre-selects** the Connection tab
- Shows an amber **"Setup required"** badge in the header
- Displays a setup notice explaining what to do

Fill in your credentials, click **Test Connection**, and copy the working values into your `.env`.

### After `.env` is configured

- The accordion is collapsed by default (unobtrusive)
- The header shows the normal subtitle with no warning badge
- The component opens to the **Schema** tab by default
- The Connection tab remains available for debugging or credential changes at any time

### Connection form fields

| Field | Notes |
|---|---|
| Protocol | `sftp` / `ftp` / `ftps` / `smb` — selecting one auto-suggests the default port |
| Host | IP address or hostname (required to test) |
| Port | Auto-filled when you switch protocol; editable |
| Username | |
| Password | Leave blank to use the saved `.env` password; shows a hint when a saved password exists |
| Base Path | Remote directory the Live Browser starts from |
| SMB Share | Shown only when protocol is `smb` |
| SMB Domain | Shown only when protocol is `smb` |

### Test Connection

Clicking **Test Connection** sends the form values to `POST /nas-file-manager/test` as credential overrides. The result appears inline:

- **Green ✓** — connection successful, message from the server
- **Red ✗** — connection failed, error message explaining why (wrong password, unreachable host, access denied, etc.)
- Leaving the password blank falls back to the password stored in config/`.env`

---

## Component Options

Pass any of these when including the component:

```blade
@include('nas-file-manager::file-manager', [
    'nodes'   => $treeNodes,                    // static schema nodes (see below)
    'canEdit' => true,                          // show create / rename / delete actions
    'title'   => 'Folder Structure & Files',   // accordion header title
])
```

### Schema nodes

The **Schema tab** displays a static folder tree — useful for documenting your expected NAS structure without making a live connection.

Each node:

```php
[
    'id'          => 1,
    'depth'       => 0,
    'label'       => 'Media',
    'path'        => 'Media',
    'parent_path' => null,
    'is_template' => false,  // true = shown in brand colour as a placeholder
    'can_edit'    => false,
]
```

You can define nodes statically in `config/nas-file-manager.php`:

```php
'schema' => [
    ['depth' => 0, 'label' => 'Media',   'path' => 'Media',         'parent_path' => null,    'is_template' => false, 'can_edit' => false],
    ['depth' => 1, 'label' => 'Outlets', 'path' => 'Media/Outlets', 'parent_path' => 'Media', 'is_template' => false, 'can_edit' => true],
    ['depth' => 2, 'label' => '{slug}',  'path' => 'Media/Outlets', 'parent_path' => 'Media', 'is_template' => true,  'can_edit' => true],
],
```

Or pass them dynamically from your controller:

```blade
@include('nas-file-manager::file-manager', ['nodes' => $nodes])
```

---

## Authorization

By default any authenticated user can use the file manager. Create/rename/delete actions are shown to all authenticated users unless you set `edit_gate`.

```php
// config/nas-file-manager.php
'edit_gate' => 'manage-files',  // Gate::allows('manage-files') must return true
```

Set to `null` (default) to allow all authenticated users.

---

## Routes

All routes are registered under the `web` + `auth` middleware stack.

| Method | URI | Action |
|---|---|---|
| `POST` | `/nas-file-manager/test` | Test a connection (supports credential overrides) |
| `POST` | `/nas-file-manager/shares` | List available SMB shares |
| `POST` | `/nas-file-manager/list-items` | List directory contents |
| `POST` | `/nas-file-manager/create` | Create a folder |
| `POST` | `/nas-file-manager/rename` | Rename a file or folder |
| `POST` | `/nas-file-manager/delete` | Delete a file or folder |

Change the prefix via `.env`:

```env
NAS_FM_ROUTE_PREFIX=my-files
```

---

## Customising Views

```bash
php artisan vendor:publish --tag=nas-file-manager-views
```

Publishes to `resources/views/vendor/nas-file-manager/`. Edit freely — future package updates will not overwrite published views.

---

## Protocol Notes

| Protocol | Port | Requires |
|---|---|---|
| `sftp` | 22 | SSH access on the NAS; rename uses `sshpass` |
| `ftp` | 21 | FTP server on the NAS |
| `ftps` | 21 | FTP server with TLS |
| `smb` | 445 | `smbclient` installed on the web server |

---

## License

MIT
