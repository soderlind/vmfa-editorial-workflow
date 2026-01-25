# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.3.1] - 2026-01-25

### Added

- JavaScript test suite with Vitest for React components and utilities
- Test coverage for `buildFolderOptions`, `InboxCard`, and `PermissionMatrix`

### Changed

- Updated `vitest` to v4.0.18
- Added `vite`, `@vitejs/plugin-react`, `@testing-library/react`, `@testing-library/jest-dom`, and `jsdom` dev dependencies

## [1.3.0] - 2026-01-25

### Added

- Internationalization (i18n) support with Norwegian Bokmål translation
- NPM scripts for translation workflow (`npm run i18n`)
- `wp_set_script_translations()` for JavaScript translations

## [1.2.0] - 2026-01-25

### Added

- Unified Review page toolbar with single destination dropdown and Apply button
- Hierarchical folder display in all dropdowns (matching sidebar structure)
- Shared `buildFolderOptions` utility for DRY folder dropdown rendering

### Changed

- Simplified Review page UX: "Approve" is now first option in destination dropdown
- Folder dropdowns now show hierarchy with "— " prefix for nested folders

### Fixed

- "Allow Editors to review media" toggle now properly saves and loads
- Boolean options stored as '1'/'0' strings for consistent database handling

## [1.1.0] - 2026-01-25

### Added

- Configurable approved folder destination in workflow settings
- Support for all roles with `upload_files` capability (custom roles automatically included)
- Auto-dismiss success notices with fade-out animation after 3 seconds
- Race condition prevention on Review page (prevents double-clicks and concurrent operations)

### Changed

- Workflow is now always enabled when plugin is active (removed enable/disable toggle)
- Settings icon updated to Gutenberg settings icon
- Administrator role excluded from settings (always has full access)
- Editor has full access by default; Author and custom roles have no access by default

### Fixed

- "Revoke All Permissions" now works correctly for Editor role
- Saving settings no longer toggles all permissions ON unexpectedly
- Permission system properly handles empty arrays vs deleted entries

## [1.0.0] - 2026-01-24

### Added

- Role-based folder visibility and permissions
  - View, move, upload, and remove actions per folder per role
  - Permission matrix UI in settings
  - Administrators bypass all permission checks

- Inbox workflow
  - Role-to-folder inbox mapping
  - Automatic routing of uploads to inbox folders
  - `vmfa_inbox_assigned` action hook for extensibility

- Review workflow
  - System folders: `/Workflow/Needs Review` and `/Workflow/Approved`
  - Protected folders (cannot be renamed or deleted)
  - Dedicated Review admin screen under Media menu
  - Bulk approve and assign actions
  - Notification badge with pending item count

- Access enforcement across all surfaces
  - REST API filtering and permission checks
  - AJAX media query filtering
  - Admin folder list filtering
  - Server-side enforcement (fail-safe)

- Settings UI
  - Tab integration with VMF settings
  - Permission matrix component
  - Inbox mapping component
  - Workflow toggle

- REST API endpoints
  - GET/POST `/vmfa-editorial/v1/settings`
  - GET/POST `/vmfa-editorial/v1/permissions`
  - GET/POST `/vmfa-editorial/v1/inbox`
  - GET/POST `/vmfa-editorial/v1/workflow`

- Testing infrastructure
  - PHPUnit with Brain Monkey
  - AccessChecker, InboxService, WorkflowState test suites
  - Vite build configuration

### Security

- All REST endpoints require `manage_options` capability
- All AJAX actions verify nonces and capabilities
- Server-side enforcement prevents bypass of UI restrictions
