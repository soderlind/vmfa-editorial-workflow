# Virtual Media Folders – Editorial Workflow

Role-based folder access, move restrictions, and Inbox workflow for Virtual Media Folders.

## Description

This add-on extends [Virtual Media Folders](https://wordpress.org/plugins/virtual-media-folders/) with enterprise-grade editorial workflow features:

- **Per-role folder visibility** — Control which folders each role can see
- **Move/assignment restrictions** — Define who can move media into which folders
- **Inbox workflow** — Contributors upload to an inbox; editors triage and move onward
- **Review workflow** — Track items needing review with dedicated admin screen
- **Internationalization** — Fully translatable with Norwegian Bokmål included

## Requirements

- WordPress 6.8+
- PHP 8.3+
- [Virtual Media Folders](https://wordpress.org/plugins/virtual-media-folders/) plugin

## Installation

1. Download [`vmfa-editorial-workflow.zip`](https://github.com/soderlind/vmfa-editorial-workflow/releases/latest/download/vmfa-editorial-workflow.zip)
2. Upload via  `Plugins → Add New → Upload Plugin`
3. Activate via `WordPress Admin → Plugins`

Plugin [updates are handled automatically](https://github.com/soderlind/wordpress-plugin-github-updater#readme) via GitHub. No need to manually download and install updates.

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

To add the `upload_files` capability to a custom role, you can use a role management plugin or run the following WP-CLI command:
```bash
# Example: Grant 'upload_files' capability to a custom role
wp role create media_contributor "Media Contributor" --clone=subscriber 
wp cap add media_contributor upload_files
```

#### Default Permissions

Out of the box, the plugin applies sensible defaults:

- **Editor** — Full access to all folders by default (can be restricted via settings)
- **Author** — No access by default (must be explicitly granted permissions)
- **Custom roles** — No access by default (must be explicitly granted permissions)

#### Permission Types

| Permission | Description |
|------------|-------------|
| **View** | Can see the folder in the sidebar and browse media inside it |
| **Move To** | Can drag-and-drop or assign media INTO this folder |
| **Upload To** | New uploads from this role can be routed to this folder via Inbox Mapping |
| **Delete** | Can delete this folder (system folders are always protected) |

> **Note:** Moving media from Folder A to Folder B only requires "Move To" permission on Folder B (and "View" on both). Anyone can remove media to Uncategorized.

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

## Development

See [docs/development.md](docs/development.md) for build instructions, testing, hooks reference, and REST API documentation.

## License

GPL-2.0-or-later
