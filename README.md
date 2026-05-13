# p7h/nas-file-manager

A Laravel package that embeds a NAS file manager directly into your Blade views. Supports SFTP, FTP, FTPS, and SMB protocols. Includes a built-in Connection tab for first-time setup — configure and save credentials from the UI with no manual file editing required.

---

## Requirements

- PHP 8.1+
- Laravel 10, 11, or 12
- Linux server (for SMB/SFTP — see System Dependencies below)

---

## Installation

```bash
composer require p7h/nas-file-manager
php artisan migrate
```

`composer require` auto-discovers the service provider and installs system dependencies. `migrate` creates the `nas_fm_connections` table used to store saved connections.

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

If no connection is configured the component **auto-expands** and opens the **Connection tab** so you can enter credentials, test the connection, and save — all from the UI.

---

## Credential Storage

The package supports two ways to persist connection credentials. The **database** is the recommended approach.

### Priority order

When loading a connection, the package checks sources in this order:

1. **Database** (`nas_fm_connections` table) — if the table has rows, these are used exclusively
2. **Config** (`config/nas-file-manager.php` `connections` array) — used if the table is empty
3. **Legacy `.env`** (`NAS_HOST`, `NAS_USERNAME`, etc.) — used as a final fallback

### Save to Database *(recommended)*

Saves credentials directly from the Connection tab UI into the `nas_fm_connections` table.

- Works for **all connections**, including multiple ones
- Passwords are stored **encrypted** using Laravel's `encrypt()` — never in plaintext
- Survives `.env` resets and deployments
- No file system access required
- The **Save to DB** button label becomes **Update** after the first save

Run the migration once to create the table:

```bash
php artisan migrate
```

### Save to .env

Writes `NAS_*` variables directly to your `.env` file from the Connection tab UI.

- Supports the **primary (first) connection only**
- Calls `config:clear` automatically after writing
- Requires the `.env` file to be writable by the web server
- A page reload is needed for changes to take effect
- Use this if you manage credentials outside the app (CI secrets, Docker env, etc.)

The `.env` variables written are:

```env
NAS_ENABLED=true
NAS_PROTOCOL=sftp          # sftp | ftp | ftps | smb
NAS_HOST=192.168.1.100
NAS_PORT=22
NAS_USERNAME=admin
NAS_PASSWORD=secret        # only written if a new password is entered
NAS_PATH=/media
NAS_SMB_SHARE=media        # SMB only
NAS_SMB_DOMAIN=WORKGROUP   # SMB only
```

### Manual config (advanced)

If you prefer to manage connections in code, publish the config and add entries to the `connections` array:

```bash
php artisan vendor:publish --tag=nas-file-manager-config
```

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

> Config-defined connections are ignored once any row exists in `nas_fm_connections`. Save to database or keep the table empty if you want config to take effect.

---

## The Three Tabs

### Schema

Displays a static folder tree defined in config or passed as `$nodes`. Documents the expected NAS structure without making a live connection. No credentials needed.

### Live Browser

Connects to the first **enabled** connection and lets you navigate folders, create new folders, rename, and delete — subject to the `edit_gate` setting.

### Connection

Manage all NAS connections from the UI. Supports multiple connections, live credential testing, enable/disable per connection, a visual subdirectory picker, and saving to database or `.env`.

---

## Connection Tab

### First time — no connection saved

When no credentials are saved the component:

- **Auto-expands** the accordion on page load
- **Pre-selects** the Connection tab
- Shows an amber **"Setup required"** badge in the header
- Displays a setup notice with instructions

Fill in credentials → **Test** → confirm green → **Save to DB** (or **Save to .env**).

### After a connection is saved

- The accordion is **collapsed by default** — unobtrusive
- The component opens to the **Schema** tab
- The Connection tab remains accessible at any time for changes or debugging

### Connection cards

Each NAS connection is a collapsible card. The collapsed header shows:

| Element | Description |
|---|---|
| Connection name | Editable inline — click to rename |
| Protocol badge | `sftp` / `ftp` / `ftps` / `smb` |
| Host address | Shown when set |
| Test status dot | Green = ok · Red = failed · Amber = testing |
| Enable / disable toggle | iOS-style — flip without expanding the card |

Expanding a card reveals the full form.

### Enable / disable toggle

- **Green (on)** — connection is active; the Live Browser uses the first enabled one
- **Grey (off)** — connection is inactive and ignored everywhere

### Connection form fields

| Field | Notes |
|---|---|
| Protocol | `sftp` / `ftp` / `ftps` / `smb` — switching auto-suggests the default port (22 / 21 / 445) |
| Host | IP address or hostname — required to test or save |
| Port | Auto-filled on protocol change; editable |
| Username | |
| Password | Leave blank to keep the saved password; a hint confirms when one is already stored |
| Share | SMB only — the share name on the server (required for SMB) |
| Domain | SMB only — workgroup or Windows domain |
| Subdirectory | Starting directory on the NAS for the Live Browser |

### Subdirectory browser

The **Subdirectory** field has a **Browse** button that opens an inline file picker:

- Uses the credentials currently entered in the card (before saving)
- Browses from the **NAS root** so you can pick any path
- Shows folders only
- Breadcrumb navigation to go deeper
- Hover a row → green **Select** button appears; clicking sets the subdirectory instantly
- **Select /current/path** button in the toolbar selects the currently browsed level
- Click **Close** to dismiss without changing anything

### Test Connection

Sends the form values to `POST /nas-file-manager/test` as live credential overrides:

- **Green ✓** — connected successfully
- **Red ✗** — failed, with a plain-English error (wrong password, unreachable host, access denied, etc.)
- Leaving the password blank falls back to the already-saved password

### Saving credentials

Each card has a **Save** split button:

- **Primary button** — saves to the database (or updates if already saved)
- **Chevron ▾** — opens a dropdown with both options and a description of each

#### Save to Database

Click the primary button or choose from the dropdown. On success:

- The result banner shows green with a confirmation message
- The button label switches from **Save to DB** → **Update**
- The connection's `db_id` is stored in the UI so future clicks update the same row
- The banner auto-clears after 5 seconds

#### Save to .env

Choose from the chevron dropdown. On success:

- The result banner shows green with a "reload required" note
- The page must be reloaded for the new values to take effect in the running app

Both options leave the **password field blank** after saving — a "Saved password will be used" hint confirms the stored password will be sent on the next test or browser load.

### Multiple connections

Click **+ Add Connection** at the bottom to add a new blank card. Each card is fully independent. Remove a connection with the **Remove** link in its footer (only shown when two or more connections exist).

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

Set to `null` (default) to allow all authenticated users. The save-connection endpoint also respects this gate.

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

## Database

### Migration

The package auto-loads its migration, so `php artisan migrate` is all that's needed:

```bash
php artisan migrate
```

To publish the migration file into your project (e.g. to modify it):

```bash
php artisan vendor:publish --tag=nas-file-manager-migrations
```

### Table: `nas_fm_connections`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint | Primary key |
| `name` | string | Display name |
| `enabled` | boolean | Whether the connection is active |
| `protocol` | string | `sftp` / `ftp` / `ftps` / `smb` |
| `host` | string | IP or hostname |
| `port` | smallint | |
| `username` | string | |
| `password` | text | Encrypted via `encrypt()`, nullable |
| `share` | string | SMB share name |
| `smb_domain` | string | SMB domain / workgroup |
| `subdirectory` | string | Starting path on the NAS |
| `sort_order` | smallint | Display order |
| `created_at` / `updated_at` | timestamp | |

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
