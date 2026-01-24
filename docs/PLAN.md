# Plan: VMFA Editorial Workflow Plugin Architecture

Add-on for role-based folder access, move restrictions, and Inbox workflow. Hook-based routing, term meta for permissions, Review submenu with bulk actions, audit logging deferred to v1.1.

## Steps

1. **Create plugin bootstrap** in vmfa-editorial-workflow.php — `Requires Plugins: virtual-media-folders` header, PHP 8.3 / WP 6.8 requirements, constants, load `Plugin.php` singleton on `plugins_loaded`; detect Rules Engine via `defined('VMFA_RULES_ENGINE_VERSION')` for future integration hooks.

2. **Build `AccessChecker` service** in src/php/Services/AccessChecker.php — methods `can_view_folder()`, `can_move_to_folder()`, `can_upload_to_folder()`, `can_remove_from_folder()`; read term meta `vmfa_access_{role}` per folder; cache results in static property per request; expose `get_allowed_folders($user_id, $action)` for UI filtering.

3. **Enforce access across all surfaces** in src/php/AccessEnforcer.php:
   - Filter `rest_pre_dispatch` for `/vmfo/v1/folders` GET requests
   - Hook `rest_request_before_callbacks` for `/vmfo/v1/folders/{id}/media` POST/DELETE
   - Filter `ajax_query_attachments_args` to scope media queries
   - Return `WP_Error` with appropriate status codes on denied operations

4. **Implement Inbox routing** in src/php/Services/InboxService.php — option `vmfa_inbox_map` (role → folder_id); hook `wp_generate_attachment_metadata` priority 15; assign to inbox if `AccessChecker::can_upload_to_folder()` passes; fire `vmfa_inbox_assigned` action.

5. **Create workflow state system folders** in src/php/WorkflowState.php — activation creates `/Workflow/Needs Review` + `/Workflow/Approved`; term meta `vmfa_system_folder = true`; hook `vmfo_can_delete_folder` to protect; helpers `mark_needs_review()`, `mark_approved()`, `get_items_needing_review()`.

6. **Build Review admin submenu with bulk actions** in src/php/Admin/ReviewPage.php:
   - Register under Media menu with badge showing count
   - Render media grid via `WP_Media_List_Table` subclass or custom React component
   - Bulk actions: "Approve" (move to Approved folder), "Assign to…" (dropdown of user's allowed folders)
   - Single-item actions: same options via row actions

7. **Create settings UI** in src/php/Admin/SettingsTab.php + src/js/settings/:
   - Register via `vmfo_settings_tabs` filter
   - Permission matrix: roles × folders × actions (view/move/upload/remove checkboxes)
   - Inbox mapping: role → folder dropdown
   - Workflow toggle: enable/disable system folders
   - REST endpoint `POST /vmfa-editorial/v1/settings`

8. **Write tests** in tests/:
   - PHPUnit + Brain Monkey: `AccessCheckerTest`, `InboxServiceTest`, `WorkflowStateTest`, REST enforcement tests
   - Vitest for JS settings components
   - Cover: permission checks, inbox routing with/without Rules Engine, system folder protection

## File Structure

```
vmfa-editorial-workflow/
├── vmfa-editorial-workflow.php
├── src/php/
│   ├── Plugin.php
│   ├── AccessEnforcer.php
│   ├── WorkflowState.php
│   ├── Services/
│   │   ├── AccessChecker.php
│   │   └── InboxService.php
│   ├── Admin/
│   │   ├── ReviewPage.php
│   │   └── SettingsTab.php
│   └── REST/
│       └── SettingsController.php
├── src/js/
│   ├── settings/
│   │   ├── index.js
│   │   ├── PermissionMatrix.jsx
│   │   └── InboxMapping.jsx
│   └── review/
│       └── BulkActions.js
├── tests/
│   ├── php/
│   │   ├── bootstrap.php
│   │   ├── AccessCheckerTest.php
│   │   ├── InboxServiceTest.php
│   │   └── WorkflowStateTest.php
│   └── js/
├── languages/
├── composer.json
├── package.json
├── vite.config.js
└── phpunit.xml.dist
```

## v1.1 Roadmap (Deferred)

- User-level permission overrides (in addition to role-based)
- Audit logging with custom table + viewer
- Rules Engine matchers: `current_user_role`, `current_user_id`, `upload_context`
