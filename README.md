# p7h/nas-file-manager

A Laravel package that embeds a NAS file manager directly into your Blade views. Supports SFTP, FTP, FTPS, and SMB protocols. Includes a built-in Connection tab for first-time setup — no configuration required to get started.

---

## Requirements

- PHP 8.1+
- Laravel 10, 11, or 12
- Linux server (for SMB/SFTP — see System Dependencies below)

---

## Installation

```bash
composer require p7h/nas-file-manager
```

Laravel auto-discovers the service provider. No manual registration needed.

---

## System Dependencies (Auto-installed)

When you run `composer require` on a Linux server, the package automatically installs the required system binaries if they are not already present:

| Binary | Used for | Package installed |
|---|---|---|
| `smbclient` | SMB protocol browsing | `smbclient` (apt) / `samba-client` (dnf/yum/apk) |
| `sshpass` | SFTP rename operations | `sshpass` |

The installer detects your package manager (`apt-get`, `dnf`, `yum`, `apk`, `pacman`) and runs the appropriate install command. If the process has root privileges it installs directly; otherwise it prepends `sudo`.

**On macOS or Windows** the auto-install is skipped and a message is printed — install the binaries manually if needed.

**If auto-install fails** (e.g. no sudo in CI) the package still works fully for SFTP/FTP/FTPS. Only SMB browsing and SFTP rename require the binaries. The exact manual command is printed if the install fails.

---

## Quick Start

Include the component anywhere inside an authenticated Blade view:

```blade
@include('nas-file-manager::file-manager')
```

If `NAS_HOST` is not set, the component opens automatically to the **Connection tab** so you can enter and test credentials before saving them to `.env`.

---

## Configuration

### 1. Publish the config (optional)

```bash
php artisan vendor:publish --tag=nas-file-manager-config
```

Copies `config/nas-file-manager.php` into your project for customisation, including adding multiple connections.

### 2. Add to `.env`

```env
NAS_ENABLED=true           # enable or disable the primary connection (default: true)
NAS_PROTOCOL=sftp          # sftp | ftp | ftps | smb  (default: sftp)
NAS_HOST=192.168.1.100     # IP address or hostname
NAS_PORT=22                # 22=sftp, 21=ftp/ftps, 445=smb
NAS_USERNAME=admin
NAS_PASSWORD=secret
NAS_PATH=/media            # starting subdirectory on the NAS (default: /media)

# SMB only
NAS_SMB_SHARE=media        # share name (required for smb)
NAS_SMB_DOMAIN=WORKGROUP   # domain or workgroup
```

### 3. Multiple connections (published config only)

After publishing the config, add extra entries to the `connections` array:

```php
// config/nas-file-manager.php
'connections' => [
    [
        'name'         => 'Primary NAS',
        'enabled'      => true,
        'protocol'     => 'sftp',
        'host'         => '192.168.1.100',
        'port'         => 22,
        'username'     => 'admin',
        'password'     => env('NAS_PASSWORD'),
        'subdirectory' => '/media',
    ],
    [
        'name'         => 'Backup NAS',
        'enabled'      => true,
        'protocol'     => 'smb',
        'host'         => '192.168.1.200',
        'port'         => 445,
        'username'     => 'backup',
        'password'     => env('NAS_BACKUP_PASSWORD'),
        'share'        => 'backups',
        'smb_domain'   => 'WORKGROUP',
        'subdirectory' => '/archives',
    ],
],
```

The first **enabled** connection is used by the Live Browser tab.

---

## The Three Tabs

### Schema

Displays a static folder tree defined in config or passed as `$nodes`. Useful for documenting your expected NAS structure without making a live connection. No credentials needed.

### Live Browser

Connects to the first enabled NAS connection and lets you navigate folders, create new folders, rename, and delete — subject to the `edit_gate` setting.

### Connection

Manage all your NAS connections from the UI. Supports multiple connections, live testing, an enable/disable toggle per connection, and a visual subdirectory picker.

---

## Connection Tab

### First install — no `.env` set

When `NAS_HOST` is empty the component:

- **Auto-expands** the accordion on page load
- **Pre-selects** the Connection tab
- Shows an amber **"Setup required"** badge in the header
- Displays a setup notice with instructions

Fill in your credentials, click **Test Connection**, confirm it works, then copy the values into your `.env`.

### After `.env` is configured

- The accordion is **collapsed by default** — unobtrusive
- The component opens to the **Schema** tab
- The Connection tab is still accessible at any time for debugging or changes

### Connection cards

Each NAS connection is shown as a collapsible card. The collapsed header shows:

- Connection name (editable inline)
- Protocol badge
- Host address
- Test status dot (green = ok, red = failed, amber = testing)
- Enable / disable toggle

Expanding a card reveals the full form.

### Enable / disable toggle

Each connection has an iOS-style toggle in the card header. Flip it without expanding the card.

- **Green (on)** — connection is active; the Live Browser uses the first enabled connection
- **Grey (off)** — connection is inactive and ignored by the Live Browser

### Connection form fields

| Field | Notes |
|---|---|
| Protocol | `sftp` / `ftp` / `ftps` / `smb` — switching auto-suggests the default port (22 / 21 / 445) |
| Host | IP address or hostname — required to test |
| Port | Auto-filled on protocol change; editable |
| Username | |
| Password | Leave blank to use the saved `.env` password; a hint appears when a saved password exists |
| Share | SMB only — the share name on the server (required for SMB) |
| Domain | SMB only — workgroup or Windows domain |
| Subdirectory | Starting directory on the NAS for the Live Browser |

### Subdirectory browser

The **Subdirectory** field has a **Browse** button. Clicking it opens an inline file picker directly below the field:

- Connects to the NAS using the credentials currently entered in the card (before saving)
- Browses from the **NAS root** — not the current subdirectory — so you can pick any path
- Shows only folders (files are hidden)
- Has breadcrumb navigation to go deeper into the tree
- Hovering a folder reveals a green **Select** button; clicking it sets the subdirectory instantly
- The **Select /current/path** button in the toolbar selects the currently browsed level
- Click **Browse** again (now labelled **Close**) to dismiss the picker without changing anything

### Test Connection

Clicking **Test Connection** on a card sends the form values to `POST /nas-file-manager/test` as live credential overrides. The result appears inline below the form:

- **Green ✓** — connected successfully
- **Red ✗** — failed, with a plain-English error (wrong password, unreachable host, access denied, etc.)
- Leaving the password blank falls back to the password stored in `.env` / config

### Saving credentials

Each connection card has a **Save** split button with two options:

#### Save to Database *(recommended)*

Stores the connection in the `nas_fm_connections` table (created automatically by the package migration). The package reads from this table on every page load, so connections survive deployments and `.env` resets. Passwords are stored **encrypted** using Laravel's `encrypt()`.

- Works for **all connections**, including multiple ones
- No `.env` changes required
- The button label changes to **Update** once a connection has been saved
- Run `php artisan migrate` once after installing the package to create the table

#### Save to .env

Writes `NAS_*` variables directly to your `.env` file and calls `config:clear` automatically.

- Supports the **primary (first) connection only**
- Requires the `.env` file to be writable by the web server
- A page reload is needed after saving for changes to take effect
- Use this option if you manage credentials outside the app (e.g. CI secrets, Docker env)

---

### Multiple connections

Click **+ Add Connection** at the bottom of the Connection tab to add a new blank card. Each card is independent — different hosts, protocols, and credentials. Remove a connection using the **Remove** link in its card footer (visible only when two or more connections exist).

---

## Component Options

```blade
@include('nas-file-manager::file-manager', [
    'nodes'   => $treeNodes,                   // static schema nodes (see below)
    'canEdit' => true,                         // show create / rename / delete actions
    'title'   => 'Folder Structure & Files',  // accordion header title
])
```

### Schema nodes

Each node passed to `$nodes`:

```php
[
    'id'          => 1,
    'depth'       => 0,
    'label'       => 'Media',
    'path'        => 'Media',
    'parent_path' => null,
    'is_template' => false,  // true = italic brand-colour placeholder
    'can_edit'    => false,
]
```

Define statically in config:

```php
'schema' => [
    ['depth' => 0, 'label' => 'Media',   'path' => 'Media',         'parent_path' => null,    'is_template' => false, 'can_edit' => false],
    ['depth' => 1, 'label' => 'Outlets', 'path' => 'Media/Outlets', 'parent_path' => 'Media', 'is_template' => false, 'can_edit' => true],
    ['depth' => 2, 'label' => '{slug}',  'path' => 'Media/Outlets', 'parent_path' => 'Media', 'is_template' => true,  'can_edit' => true],
],
```

Or pass dynamically:

```blade
@include('nas-file-manager::file-manager', ['nodes' => $nodes])
```

---

## Authorization

By default any authenticated user can browse, create, rename, and delete. Restrict write actions with a gate:

```php
// config/nas-file-manager.php
'edit_gate' => 'manage-nas',  // Gate::allows('manage-nas') must return true
```

Set to `null` (default) to allow all authenticated users.

---

## Routes

All routes are registered under `web` + `auth` middleware.

| Method | URI | Action |
|---|---|---|
| `POST` | `/nas-file-manager/test` | Test a connection (supports live credential overrides) |
| `POST` | `/nas-file-manager/shares` | List available SMB shares |
| `POST` | `/nas-file-manager/list-items` | List directory contents |
| `POST` | `/nas-file-manager/create` | Create a folder |
| `POST` | `/nas-file-manager/rename` | Rename a file or folder |
| `POST` | `/nas-file-manager/delete` | Delete a file or folder |
| `POST` | `/nas-file-manager/connections/save` | Save a connection to database or `.env` |

Change the URL prefix:

```env
NAS_FM_ROUTE_PREFIX=my-nas
```

---

## Database Migration

The package auto-loads its migration via `loadMigrationsFrom`, so running `php artisan migrate` is all that's needed:

```bash
php artisan migrate
```

This creates the `nas_fm_connections` table. To publish the migration file into your project (e.g. to modify it):

```bash
php artisan vendor:publish --tag=nas-file-manager-migrations
```

---

## Customising Views

```bash
php artisan vendor:publish --tag=nas-file-manager-views
```

Publishes to `resources/views/vendor/nas-file-manager/`. Future package updates will not overwrite published views.

---

## Protocol Notes

| Protocol | Default port | System binary | Notes |
|---|---|---|---|
| `sftp` | 22 | `sshpass` (auto-installed) | SSH must be enabled on the NAS |
| `ftp` | 21 | none | |
| `ftps` | 21 | none | FTP over TLS |
| `smb` | 445 | `smbclient` (auto-installed) | Share name required |

---

## License

MIT
