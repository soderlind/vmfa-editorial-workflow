=== Virtual Media Folders – Editorial Workflow ===
Contributors: PerS
Tags: media, folders, workflow, editorial, permissions
Requires at least: 6.8
Tested up to: 6.9
Requires PHP: 8.3
Stable tag: 1.2.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Role-based folder access, move restrictions, and Inbox workflow for Virtual Media Folders.

== Description ==

This add-on extends [Virtual Media Folders](https://github.com/soderlind/virtual-media-folders) with enterprise-grade editorial workflow features:

* **Per-role folder visibility** — Control which folders each role can see
* **Move/assignment restrictions** — Define who can move media into which folders
* **Inbox workflow** — Contributors upload to an inbox; editors triage and move onward
* **Review workflow** — Track items needing review with dedicated admin screen

= Requirements =

* WordPress 6.8+
* PHP 8.3+
* Virtual Media Folders plugin

= Optional =

* [VMFA Rules Engine](https://github.com/soderlind/vmfa-rules-engine) — For advanced upload routing and conditions

== Installation ==

1. Upload the `vmfa-editorial-workflow` folder to `/wp-content/plugins/`
2. Ensure Virtual Media Folders is installed and activated
3. Activate the plugin through the 'Plugins' menu
4. Configure settings under Media → VMF Settings → Editorial Workflow

== Configuration ==

= Folder Permissions =

Navigate to **Media → VMF Settings → Editorial Workflow** to configure:

1. **Permission Matrix** — Set view/move/upload/remove permissions per folder per role
2. **Inbox Mapping** — Assign default upload folders for each role
3. **Approved Folder** — Choose which folder approved items are moved to

= Supported Roles =

The permission settings automatically include **all roles that have the `upload_files` capability** — not just Editor and Author. This means custom roles are fully supported:

* Custom roles like "Contributor with upload" or "Shop Manager" will appear automatically
* Any role granted the `upload_files` capability via plugins (e.g., Members, User Role Editor) will be configurable
* Administrator is excluded from settings as they always have full access to all folders

= Default Permissions =

Out of the box, the plugin applies sensible defaults:

* **Editor** — Full access to all folders by default (can be restricted via settings)
* **Author** — No access by default (must be explicitly granted permissions)
* **Custom roles** — No access by default (must be explicitly granted permissions)

= Workflow Folders =

On activation, the plugin creates protected system folders:

* `/Workflow/Needs Review` — Items pending editorial review
* `/Workflow/Approved` — Items that have been approved

These folders cannot be renamed or deleted.

= Review Screen =

Access **Media → Review** to:

* View all items needing review
* Bulk approve items (moves to Approved folder)
* Bulk assign items to destination folders
* See notification badge with count of pending items

== Frequently Asked Questions ==

= Does this work with custom roles? =

Yes! Any role with the `upload_files` capability will automatically appear in the settings. This includes custom roles created by plugins like Members or User Role Editor.

= Can I change where approved items go? =

Yes, you can configure the approved folder destination in the Workflow settings.

= What happens to existing media when I activate the plugin? =

Existing media is not affected. The workflow only applies to new uploads and explicit move actions.

== Screenshots ==

1. Permission matrix for configuring folder access per role
2. Inbox mapping configuration
3. Review screen with pending items
4. Workflow settings

== Changelog ==

= 1.2.0 =

**Added**

* Unified Review page toolbar with single destination dropdown and Apply button
* Hierarchical folder display in all dropdowns (matching sidebar structure)

**Changed**

* Simplified Review page UX: "Approve" is now first option in destination dropdown
* Folder dropdowns now show hierarchy with indentation for nested folders

**Fixed**

* "Allow Editors to review media" toggle now properly saves and loads

= 1.1.0 =

**Added**

* Configurable approved folder destination in workflow settings
* Support for all roles with `upload_files` capability (custom roles automatically included)
* Auto-dismiss success notices with fade-out animation after 3 seconds
* Race condition prevention on Review page (prevents double-clicks and concurrent operations)

**Changed**

* Workflow is now always enabled when plugin is active (removed enable/disable toggle)
* Settings icon updated to Gutenberg settings icon
* Administrator role excluded from settings (always has full access)
* Editor has full access by default; Author and custom roles have no access by default

**Fixed**

* "Revoke All Permissions" now works correctly for Editor role
* Saving settings no longer toggles all permissions ON unexpectedly
* Permission system properly handles empty arrays vs deleted entries

= 1.0.0 =

* Initial release
* Role-based folder permissions
* Inbox workflow with automatic routing
* Review screen with bulk actions
* System folder protection

== Upgrade Notice ==

= 1.1.0 =
Workflow is now always enabled. If you previously disabled the workflow, it will be active after updating.
