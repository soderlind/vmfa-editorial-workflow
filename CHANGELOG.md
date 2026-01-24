# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
