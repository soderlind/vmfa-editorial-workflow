# Virtual Media Folders – Editorial Workflow

Role-based folder access, move restrictions, and Inbox workflow for Virtual Media Folders.

## Description

This add-on extends [Virtual Media Folders](https://github.com/soderlind/virtual-media-folders) with enterprise-grade editorial workflow features:

- **Per-role folder visibility** — Control which folders each role can see
- **Move/assignment restrictions** — Define who can move media into which folders
- **Inbox workflow** — Contributors upload to an inbox; editors triage and move onward
- **Review workflow** — Track items needing review with dedicated admin screen
- **Internationalization** — Fully translatable with Norwegian Bokmål included

## Requirements

- WordPress 6.8+
- PHP 8.3+
- Virtual Media Folders plugin

## Installation

1. Upload the `vmfa-editorial-workflow` folder to `/wp-content/plugins/`
2. Ensure Virtual Media Folders is installed and activated
3. Activate the plugin through the 'Plugins' menu
4. Configure settings under Media → VMF Settings → Editorial Workflow

## Configuration

### Folder Permissions

Navigate to **Media → VMF Settings → Editorial Workflow** to configure:

1. **Permission Matrix** — Set view/move/upload/remove permissions per folder per role
2. **Inbox Mapping** — Assign default upload folders for each role
3. **Approved Folder** — Choose which folder approved items are moved to

#### Supported Roles

The permission settings automatically include **all roles that have the `upload_files` capability** — not just Editor and Author. This means custom roles are fully supported:

- Custom roles like "Contributor with upload" or "Shop Manager" will appear automatically
- Any role granted the `upload_files` capability via plugins (e.g., Members, User Role Editor) will be configurable
- Administrator is excluded from settings as they always have full access to all folders

#### Default Permissions

Out of the box, the plugin applies sensible defaults:

- **Editor** — Full access to all folders by default (can be restricted via settings)
- **Author** — No access by default (must be explicitly granted permissions)
- **Custom roles** — No access by default (must be explicitly granted permissions)

### Workflow Folders

On activation, the plugin creates protected system folders:

- `/Workflow/Needs Review` — Items pending editorial review
- `/Workflow/Approved` — Items that have been approved

These folders cannot be renamed or deleted.

### Review Screen

Access **Media → Review** to:

- View all items needing review
- Bulk approve items (moves to Approved folder)
- Bulk assign items to destination folders
- See notification badge with count of pending items

## Hooks Reference

### Actions

| Hook | Description | Parameters |
|------|-------------|------------|
| `vmfa_inbox_assigned` | Fired after upload is routed to inbox | `$attachment_id, $folder_id, $user_id` |
| `vmfa_marked_needs_review` | Fired after item marked for review | `$attachment_id, $folder_id` |
| `vmfa_approved` | Fired after item is approved | `$attachment_id, $folder_id` |

### Filters

The plugin integrates with VMF core hooks:

| Filter | Description |
|--------|-------------|
| `vmfo_can_delete_folder` | Used to protect system folders |

## REST API

### Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/vmfa-editorial/v1/settings` | Get all settings |
| POST | `/vmfa-editorial/v1/settings` | Update all settings |
| GET | `/vmfa-editorial/v1/permissions` | Get folder permissions |
| POST | `/vmfa-editorial/v1/permissions` | Update folder permissions |
| GET | `/vmfa-editorial/v1/inbox` | Get inbox mapping |
| POST | `/vmfa-editorial/v1/inbox` | Update inbox mapping |
| GET | `/vmfa-editorial/v1/workflow` | Get workflow settings |
| POST | `/vmfa-editorial/v1/workflow` | Update workflow settings |

All endpoints require `manage_options` capability.

## Development

### Build Assets

```bash
npm install
npm run build
```

### Run Tests

```bash
# PHP tests
composer install
composer test

# JavaScript tests
npm test
```

### Generate Translations

```bash
npm run i18n
```

### Lint Code

```bash
composer lint
npm run lint:js
```

## License

GPL-2.0-or-later
