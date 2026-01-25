# Development

## Prerequisites

- Node.js 18+
- PHP 8.3+
- Composer

## Build Assets

```bash
npm install
npm run build
```

For development with watch mode:

```bash
npm run dev
```

## Run Tests

### PHP Tests

```bash
composer install
composer test
```

### JavaScript Tests

```bash
npm test
```

Run tests once (CI mode):

```bash
npm test -- --run
```

## Generate Translations

```bash
npm run i18n
```

This command runs the full translation workflow:
1. Extracts strings to `.pot` file
2. Updates `.po` files
3. Compiles `.mo` files
4. Generates JSON for JavaScript
5. Creates PHP translation files

## Lint Code

### PHP

```bash
composer lint
```

### JavaScript

```bash
npm run lint:js
```

### CSS

```bash
npm run lint:css
```

## Project Structure

```
vmfa-editorial-workflow/
├── build/              # Compiled assets
├── docs/               # Documentation
├── languages/          # Translation files
├── src/
│   ├── css/            # Source stylesheets
│   ├── js/             # Source JavaScript/React
│   │   ├── review/     # Review page scripts
│   │   └── settings/   # Settings panel components
│   └── php/            # PHP classes
│       ├── Admin/      # Admin pages
│       ├── REST/       # REST API endpoints
│       └── Services/   # Business logic
├── tests/
│   └── php/            # PHPUnit tests
└── vendor/             # Composer dependencies
```

## Coding Standards

- PHP: WordPress Coding Standards (WPCS)
- JavaScript: WordPress ESLint configuration
- CSS: WordPress Stylelint configuration

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
