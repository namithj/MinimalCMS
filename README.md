# MinimalCMS

![Version](https://img.shields.io/badge/version-0.0.6-blue)
![PHP](https://img.shields.io/badge/PHP-8.2%2B-8892BF)
![License](https://img.shields.io/badge/license-MIT-blue)
![Status](https://img.shields.io/badge/status-early%20development-orange)

> A lightweight, flat-file CMS built with a familiar hook, plugin, and theme architecture — no database, no bloat, just PHP and Markdown.

---

## What is MinimalCMS?

MinimalCMS is a file-based content management system with a familiar hook, plugin, and theme architecture — without requiring a database, a framework, or complex infrastructure.

Content is written in **Markdown**, metadata lives in **PHP-guarded sidecars**, user data is **sodium-encrypted at rest**, and the entire system boots from a single `index.php` front controller through an **`MC_App` singleton container** that wires 27 object-oriented classes. If you can upload files to a PHP + Apache host, you can run MinimalCMS.

It is designed as a learning reference, a lightweight CMS for small sites, and a clean base for developers who want a hook-driven, extensible CMS without the overhead.

---

## At a Glance

| Property | Detail |
|---|---|
| **Architecture** | 27 OOP classes, `MC_App` singleton container |
| **Storage** | Flat files (Markdown + PHP-guarded JSON) |
| **Language** | PHP 8.2+ |
| **Server** | Apache + `mod_rewrite` (via Docker or standalone) |
| **Dependencies** | Parsedown + Parsedown Extra (Markdown), EasyMDE (editor) |
| **Admin UI** | Built-in dashboard with Markdown editor and dashboard widgets |
| **Auth** | Encrypted user file, bcrypt passwords, HMAC nonces |
| **Extensibility** | 92 hooks (44 actions, 48 filters), plugin & theme system |
| **JavaScript** | 7 ES Module classes, `window.MC` global |

---

## Features

**Content**
- Write pages and posts in Markdown (`.md`) with PHP-guarded sidecar metadata
- Custom content types, archive routes, and permalink slugs
- Shortcode parser — `[greet name="World"]` syntax
- Field Registry with built-in types: text, textarea, number, url, checkbox, select, markdown, html

**Security**
- All data files use PHP Guard pattern (`<?php die(); ?>` header + `.php` extension) — no web server can serve raw data
- Cryptographic keys isolated in an encrypted keystore (sodium secretbox) with master key hierarchy
- User data encrypted with `sodium_crypto_secretbox` (256-bit key)
- Passwords hashed with bcrypt
- All forms protected by HMAC-based nonces
- `mc-data/` is locked behind Apache `Deny all` + PHP `die()` double guard

**Extensibility**
- 92 documented hooks (44 actions, 48 filters) across all subsystems
- Action & filter hooks with `mc_add_action()` / `mc_add_filter()`
- Plugin lifecycle (activate, deactivate) driven by `config.php`
- Theme system with template hierarchy, child theme support, and `theme.php` manifests
- MU-plugins directory for must-use code
- 3-layer Settings API: storage (`MC_Settings`), registry (`MC_Settings_Registry`), fields (`MC_Field_Registry`)
- Dashboard widgets (site info, recent pages, quick links)

**Developer Experience**
- Single front controller — no framework magic
- 27 focused classes with typed methods (PHP 8.0+ union types)
- 7 ES Module JavaScript classes — no jQuery, no global soup
- File-based PHP cache with TTL
- Full PHPUnit test suite (unit + integration)
- PHPStan + PHPCS (MinimalCMS Coding Standards) pre-configured
- SCSS build pipeline via npm + Sass
- Docker-based development environment with one-command setup
- In-depth architecture documentation in `docs/architecture/`

---

## Requirements

| Requirement | Version |
|---|---|
| Docker | 20+ with Compose v2 |
| Node.js | 18+ (for build tools) |

> **No PHP, Composer, or web server needed on your machine** — Docker provides everything.

---

## Quick Start

```bash
# 1. Clone or download
git clone https://github.com/your-org/minimalcms.git my-site
cd my-site

# 2. Install Node dependencies
npm install

# 3. Run first-time setup (builds Docker image, installs PHP deps, compiles assets)
npm run setup

# 4. Start the dev server
npm run dev
```

Open **http://localhost:8080** — the setup wizard runs automatically on the first visit.

To use a different port: `MC_PORT=9000 npm run dev`

To stop: `npm run serve:stop` or <kbd>Ctrl</kbd>+<kbd>C</kbd>

### Manual / Production Setup

For production or non-Docker environments:

| Requirement | Version |
|---|---|
| PHP | 8.2+ (sodium bundled since 7.2) |
| Extensions | sodium, json, mbstring |
| Web server | Apache with `mod_rewrite` |
| Disk access | Write permission on the project root |

```bash
composer install                   # PHP dependencies (optional — tests/linting only)
npm install && npm run build       # Compile SCSS assets
# Point Apache at the project root, enable mod_rewrite, visit /mc-admin/
```

The **setup wizard** runs automatically on the first visit. It will:
- Generate a master key and provision an encrypted keystore
- Create the initial administrator account
- Redirect you to the dashboard

---

## How It Works

Every request hits `index.php`, which loads `mc-blog-header.php`. That file calls `MC_App::boot()`, which wires all services in five layers:

```
Browser Request
      │
      ▼
 index.php ──► mc-blog-header.php ──► mc-load.php (constants + autoload)
                                           │
                                    MC_App::boot()
                                           │
              ┌────────────────────────────┼────────────────────────────┐
              │                            │                            │
     ┌────────┴────────┐         ┌────────┴────────┐         ┌────────┴────────┐
     │   Foundation    │         │ Infrastructure  │         │    Content      │
     │                 │         │                 │         │                 │
     │  Config         │         │  Capabilities   │         │  Field Registry │
     │  Hooks          │         │  Session        │         │  Settings       │
     │  Formatter      │         │  User Manager   │         │  Content Types  │
     │  Http           │         │                 │         │  Content Mgr    │
     │  Cache          │         │                 │         │  Markdown       │
     │                 │         │                 │         │  Shortcodes     │
     └────────┬────────┘         └────────┬────────┘         └────────┬────────┘
              │                            │                            │
     ┌────────┴────────┐         ┌────────┴────────┐                   │
     │  Presentation   │         │ Extensibility   │                   │
     │                 │         │                 │                   │
     │  Router         │         │  Theme Manager  │                   │
     │  Template Loader│         │  Plugin Manager │                   │
     │  Asset Manager  │         │  Admin Bar      │                   │
     │  Template Tags  │         │  Setup          │                   │
     └────────┬────────┘         └────────┬────────┘                   │
              └────────────────────────────┼───────────────────────────┘
                                           │
                                    Lifecycle Hooks
                                           │
              mc_muplugins_loaded ──► mc_plugins_loaded ──► mc_after_setup_theme
                                           │
                                    mc_init ──► mc_loaded
                                           │
                                    MC_Router::dispatch()
                                           │
                                    MC_Template_Loader::load()
                                           │
                                      Theme Template
```

There is no routing framework — just a clean hook system that plugins and themes tap into at well-defined points. The `MC_App` singleton is the **only global** in the system; every service is accessed through typed accessors like `MC_App::instance()->hooks()` or `MC_App::instance()->users()`.

---

## Directory Structure

```
minimalcms/
├── index.php                    # Front controller — all requests start here
├── .htaccess                    # Rewrite all non-file requests to index.php
├── mc-blog-header.php           # Orchestrates: boot → route → render
├── mc-load.php                  # Bootstrap: constants, autoload, MC_App::boot()
├── config.sample.php            # Template config — copied to config.php on setup
├── composer.json                # PHP dependencies & dev scripts
├── package.json                 # Node dependencies & build scripts
├── Dockerfile                   # PHP 8.2 + Apache dev image
├── docker-compose.yml           # Dev container definition
├── phpunit.xml                  # PHPUnit configuration
├── phpcs.xml.dist               # PHP CodeSniffer ruleset
│
├── mc-includes/                 # Core PHP classes & autoloader
│   ├── autoload.php             # PSR-4 style autoloader for MC_* classes
│   ├── functions.php            # Global helpers (mc_app, mc_site_url, mc_is_error, etc.)
│   ├── classes/                 # 27 core classes
│   │   ├── class-mc-app.php              # Singleton service container
│   │   ├── class-mc-config.php           # Config loader (config.php)
│   │   ├── class-mc-hooks.php            # Action & filter engine
│   │   ├── class-mc-formatter.php        # Escaping & sanitisation
│   │   ├── class-mc-http.php             # Nonces, redirects, AJAX, JSON
│   │   ├── class-mc-cache.php            # File-based + runtime cache with TTL
│   │   ├── class-mc-capabilities.php     # Roles & permission checks
│   │   ├── class-mc-session.php          # PHP session lifecycle
│   │   ├── class-mc-user-manager.php     # User CRUD, auth, encrypted storage
│   │   ├── class-mc-keystore.php          # Encrypted key storage with master key hierarchy
│   │   ├── class-mc-settings.php         # File-based settings storage (PHP-guarded)
│   │   ├── class-mc-settings-registry.php # Settings pages, sections, fields
│   │   ├── class-mc-content-type-registry.php # Content type definitions
│   │   ├── class-mc-content-manager.php  # Content CRUD (get, save, delete, query)
│   │   ├── class-mc-markdown.php         # Parsedown wrapper
│   │   ├── class-mc-shortcodes.php       # Shortcode parser
│   │   ├── class-mc-router.php           # URL routing & query state
│   │   ├── class-mc-template-loader.php  # Template hierarchy resolver
│   │   ├── class-mc-asset-manager.php    # CSS/JS enqueue & localization
│   │   ├── class-mc-template-tags.php    # Template helper functions
│   │   ├── class-mc-theme-manager.php    # Theme discovery & loading
│   │   ├── class-mc-plugin-manager.php   # Plugin discovery & lifecycle
│   │   ├── class-mc-admin-bar.php        # Front-end admin toolbar
│   │   ├── class-mc-setup.php            # First-run wizard logic
│   │   └── class-mc-error.php            # Error container object│   │   ├── class-mc-field-registry.php   # Field type registration & rendering
│   │   ├── class-mc-file-guard.php       # PHP Guard file I/O (read/write guarded files)│   └── vendor/                  # Composer dependencies (Parsedown, dev tools)
│
├── mc-admin/                    # Admin panel
│   ├── admin.php                # Admin bootstrap + auth gate
│   ├── login.php                # Authentication
│   ├── setup.php                # First-run wizard
│   ├── index.php                # Dashboard
│   ├── pages.php                # Content listing
│   ├── edit-page.php            # Markdown editor
│   ├── users.php                # User management
│   ├── user-edit.php            # Create/edit user
│   ├── plugins.php              # Plugin management
│   ├── themes.php               # Theme management
│   ├── settings.php             # Site settings
│   ├── template-sections.php    # Template section management
│   ├── form-submissions.php     # Form submission viewer
│   ├── admin-ajax.php           # AJAX handler (mc_ajax_{action} hooks)
│   ├── admin-header.php         # Admin layout header
│   ├── admin-footer.php         # Admin layout footer
│   ├── includes/
│   │   └── admin-functions.php  # Admin helpers & menu builder
│   ├── widgets/                 # Dashboard widgets
│   │   ├── widget-site-info.php
│   │   ├── widget-recent-pages.php
│   │   └── widget-quick-links.php
│   └── assets/
│       ├── css/                 # Compiled stylesheets (admin.css, auth.css + .min)
│       ├── js/                  # Admin JS entry points & modules
│       ├── vendor/              # Third-party libs (EasyMDE, etc.)
│       └── src/scss/            # SCSS source (admin.scss, auth.scss, variables, mixins)
│
├── mc-content/                  # User content & extensions
│   ├── pages/                   # Page content (Markdown + JSON per folder)
│   │   └── home/                # Default home page
│   ├── plugins/                 # Plugin directory
│   │   ├── forms/               # Bundled forms plugin (submissions, assets, includes)
│   │   └── posts/               # Bundled posts plugin (blog content type)
│   └── themes/
│       └── default/             # Default theme
│           ├── theme.php        # Theme metadata & settings (PHP-guarded)
│           ├── functions.php    # Theme hooks & customization
│           ├── style.css        # Compiled stylesheet
│           ├── style.min.css    # Minified stylesheet
│           ├── front-page.php   # Home page template
│           ├── page.php         # Single page template
│           ├── page-sidebar.php # Page with sidebar template
│           ├── single.php       # Single post template
│           ├── archive.php      # Archive/listing template
│           ├── 404.php          # Not found template
│           ├── header.php       # Header partial
│           ├── footer.php       # Footer partial
│           ├── sidebar.php      # Sidebar partial
│           ├── index.php        # Ultimate fallback template
│           └── src/scss/        # Theme SCSS source
│
├── mc-data/                     # Protected data directory
│   ├── .htaccess                # Deny all direct access
│   ├── keys.php                 # Encrypted keystore (sodium secretbox)
│   ├── sessions/                # PHP session files
│   └── settings/                # Settings files ({group}.{section}.php, PHP-guarded)
│
├── docs/                        # Documentation
│   └── architecture/            # In-depth architecture reference
│       ├── README.md            # Architecture overview
│       ├── OBJECT-MODEL.md      # All 25 classes, boot sequence, full API
│       ├── HOOKS-CATALOG.md     # All 92 hooks with signatures & examples
│       └── JAVASCRIPT-CONVENTIONS.md  # ES Module patterns & class catalog
│
├── tests/                       # Test suite
│   ├── bootstrap.php            # PHPUnit bootstrap
│   ├── unit/                    # Unit tests (one per class)
│   └── integration/             # Integration tests
│
└── scripts/                     # Build & automation scripts
    ├── setup.js                 # First-time project setup
    ├── check-env.js             # Docker environment verification
    └── update-version.js        # Version bump across all files
```

---

## Content Format

Each content item lives in its own folder with two files:

```
mc-content/pages/my-page/
├── my-page.md       # Markdown body
└── my-page.php      # Metadata sidecar (PHP-guarded JSON)
```

### Sidecar example

Sidecar files use the PHP Guard pattern — a `<?php die('Access denied'); ?>` header followed by JSON. This prevents any web server from serving the raw data:

```php
<?php die('Access denied'); ?>
{
    "title": "About Us",
    "slug": "about",
    "status": "publish",
    "excerpt": "Learn more about our team.",
    "author": "admin-uuid",
    "template": "",
    "parent": "",
    "order": 0,
    "meta": {},
    "created_at": "2025-01-01T00:00:00+00:00",
    "updated_at": "2025-01-01T00:00:00+00:00"
}
```

The `meta` field holds arbitrary key-value data for custom fields. Content is managed through `MC_Content_Manager` with `get()`, `save()`, `delete()`, `query()`, `exists()`, and `count()` methods. All file I/O goes through `MC_File_Guard` which transparently handles the guard header.

---

## Custom Content Types

Register custom content types in a plugin:

```php
mc_register_content_type( 'post', array(
    'label'        => 'Posts',      // plural  → storage folder becomes "posts"
    'singular'     => 'Post',       // singular → used in admin UI labels
    'hierarchical' => false,
    'has_archive'  => true,
    'rewrite'      => array( 'slug' => 'blog' ),
) );
```

Then create content in `mc-content/posts/{slug}/{slug}.md` + `.php` (PHP-guarded sidecar).

MinimalCMS ships with two bundled plugins: **forms** (form builder with submissions) and **posts** (blog content type with archives).

---

## Plugin Development

Plugins live in `mc-content/plugins/{plugin-name}/{plugin-name}.php` and use a standard file header:

```php
<?php
/**
 * Plugin Name: My Plugin
 * Description: Does something cool.
 * Version:     1.0.0
 * Author:      You
 */

// Add a filter.
mc_add_filter( 'mc_the_content', function ( string $content ): string {
    return $content . '<p>Appended by my plugin!</p>';
} );

// Add an action.
mc_add_action( 'mc_init', function (): void {
    // Runs after all plugins load.
} );

// Register a shortcode.
mc_add_shortcode( 'greet', function ( array $atts ): string {
    return 'Hello, ' . mc_esc_html( $atts['name'] ?? 'World' ) . '!';
} );
```

Activate plugins from **Admin → Plugins** or by adding the path to `config.php`'s `active_plugins` array. All plugin functions must be prefixed with `{slug}_` (e.g. `forms_register_content_type`).

---

## Theme Development

Themes live in `mc-content/themes/{theme-name}/` and require a `theme.php` manifest (PHP-guarded JSON):

```php
<?php die('Access denied'); ?>
{
    "name": "My Theme",
    "version": "1.0.0",
    "author": "You",
    "description": "A custom theme.",
    "parent": ""
}
```

Themes also include `functions.php` for hooks and `style.css` for styles. SCSS sources live in `src/scss/` and compile to `style.css` + `style.min.css` via `npm run build`.

### Template Hierarchy

The template loader follows a cascade:

1. `front-page.php` (home page only)
2. `page-{slug}.php` → `page.php` (pages)
3. `single-{type}.php` → `single.php` (custom types)
4. `archive-{type}.php` → `archive.php` (listings)
5. `404.php` (not found)
6. `index.php` (ultimate fallback)

### Template Tags

```php
mc_head();                          // Outputs <head> assets
mc_footer();                        // Outputs footer scripts
mc_the_title();                     // Current content title
mc_the_content();                   // Parsed Markdown → HTML
mc_the_excerpt();                   // Content excerpt
mc_body_class();                    // CSS classes for <body>
mc_get_header();                    // Include header.php
mc_get_footer();                    // Include footer.php
mc_get_sidebar();                   // Include sidebar.php
mc_get_template_part( $slug );      // Include a template partial
mc_enqueue_style( $handle, $src );  // Register a stylesheet
mc_enqueue_script( $handle, $src ); // Register a script
```

---

## Hook Reference

MinimalCMS fires **92 hooks** (44 actions, 48 filters) across its entire lifecycle. Every data transformation and lifecycle event fires a hook, enabling plugins and themes to customize behavior without modifying core.

### Key Hooks

| Hook | Type | Description |
|------|------|-------------|
| `mc_muplugins_loaded` | Action | After MU plugins load |
| `mc_plugins_loaded` | Action | After regular plugins load |
| `mc_after_setup_theme` | Action | After theme loads |
| `mc_init` | Action | Full system ready |
| `mc_loaded` | Action | Boot complete |
| `mc_template_redirect` | Action | Before template renders |
| `mc_head` | Action | Inside `<head>` |
| `mc_footer` | Action | Before `</body>` |
| `mc_admin_init` | Action | Start of admin page |
| `mc_admin_menu` | Action | Build admin menu |
| `mc_the_content` | Filter | Content HTML output |
| `mc_the_title` | Filter | Content title output |
| `mc_document_title` | Filter | Page `<title>` |
| `mc_body_class` | Filter | Body CSS classes |
| `mc_user_can` | Filter | Permission check |

### Hooks by Category

| Category | Hooks | Examples |
|----------|-------|---------|
| **Lifecycle** | 4 | `mc_muplugins_loaded`, `mc_plugins_loaded`, `mc_init`, `mc_loaded` |
| **Configuration** | 3 | `mc_config_loaded`, `mc_config_pre_save`, `mc_config_saved` |
| **User Management** | 8 | `mc_pre_authenticate`, `mc_login`, `mc_logout`, `mc_user_created` |
| **Capabilities** | 6 | `mc_user_roles`, `mc_user_can`, `mc_role_added` |
| **Content CRUD** | 6 | `mc_pre_save_content`, `mc_content_saved`, `mc_content_deleted` |
| **Content Types** | 3 | `mc_register_content_type_args`, `mc_registered_content_type` |
| **Settings** | 9 | `mc_get_settings`, `mc_pre_update_settings`, `mc_settings_updated` |
| **Fields** | 5 | `mc_registered_field_type`, `mc_render_field`, `mc_sanitize_field_{type}` |
| **Templates** | 6 | `mc_template_hierarchy`, `mc_template_include`, `mc_the_content` |
| **Assets** | 4 | `mc_enqueue_style`, `mc_enqueue_script`, `mc_print_styles` |
| **Themes** | 5 | `mc_after_setup_theme`, `mc_switch_theme`, `mc_page_templates` |
| **Plugins** | 6 | `mc_plugin_loaded`, `mc_plugin_activated`, `mc_plugin_deactivated` |
| **Routing** | 4 | `mc_request_path`, `mc_parse_request`, `mc_custom_routes` |
| **Admin UI** | 6 | `mc_admin_init`, `mc_admin_head`, `mc_admin_menu`, `mc_admin_dashboard` |
| **AJAX** | 2 | `mc_ajax_{action}`, `mc_ajax_nopriv_{action}` (dynamic) |
| **Other** | 15 | Cache, session, formatting, HTTP, markdown, setup, admin bar |

> See `docs/architecture/HOOKS-CATALOG.md` for the full catalog with signatures, parameters, and examples.

### Hook API

```php
mc_add_action( $hook, $callback, $priority = 10, $accepted_args = 1 );
mc_do_action( $hook, ...$args );
mc_add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 );
mc_apply_filters( $hook, $value, ...$args );
mc_remove_action( $hook, $callback, $priority );
mc_remove_filter( $hook, $callback, $priority );
```

---

## JavaScript Architecture

The admin UI uses **vanilla ES Modules** — no jQuery, no bundler. Source files live in `mc-admin/assets/src/js/` and are class-based with a single approved global: `window.MC`.

### Core Modules

| Class | Purpose |
|-------|---------|
| `SidebarManager` | Mobile sidebar toggle and overlay |
| `ConfirmDialog` | Intercept destructive actions, require confirmation |
| `NoticeManager` | Auto-dismiss admin notices (4s default) |
| `SlugGenerator` | Watch title field, auto-generate URL slug |
| `MarkdownEditor` | EasyMDE wrapper with autosave (drafts to localStorage) |
| `AjaxClient` | AJAX requests with automatic nonce injection |
| `HooksClient` | Client-side hooks system for JS plugins |

### Entry Points

- `admin-app.js` — Wires all admin modules, bootstraps `window.MC`
- `editor-app.js` — `MarkdownEditor`-specific initialization

### Pattern

```js
export class ConfirmDialog {
    #selector = '.confirm-delete';
    #message = 'Are you sure?';

    constructor(options = {}) {
        this.#selector = options.selector || this.#selector;
    }

    init() {
        document.addEventListener('click', (e) => this.#handleClick(e));
    }

    #handleClick(event) { /* ... */ }
    destroy() { /* cleanup */ }
}
```

Server data is passed via `mc_localize_script()` and accessed as `window.mcData`.

> See `docs/architecture/JAVASCRIPT-CONVENTIONS.md` for the full specification.

---

## Security

- **PHP Guard pattern**: All data files (config, settings, sidecars, theme manifests) use `<?php die('Access denied'); ?>` + `.php` extension — no web server can serve raw data, regardless of server software
- **Encrypted keystore**: Cryptographic keys are isolated from config and stored in a sodium-encrypted keystore (`mc-data/keys.php`) with master key hierarchy (env var → above-webroot file → in-webroot guarded file)
- **User file encryption**: All user data (including bcrypt-hashed passwords) is encrypted with `sodium_crypto_secretbox` using a 256-bit key from the keystore
- **Nonces**: HMAC-based tokens protect all form submissions and admin actions
- **Capabilities**: Role-based permission system with 4 default roles (administrator, editor, author, contributor)
- **Data protection**: `mc-data/` folder has an `.htaccess` deny rule and a PHP `die()` guard in the users file
- **Output escaping**: All output must use `mc_esc_html()`, `mc_esc_attr()`, `mc_esc_url()`, `mc_esc_js()`, or `mc_esc_textarea()`
- **Input sanitisation**: All input must use `mc_sanitize_text()`, `mc_sanitize_slug()`, `mc_sanitize_email()`, `mc_sanitize_html()`, or `mc_sanitize_filename()`

---

## Development

### npm Scripts (Primary Workflow)

```bash
npm run setup          # First-time: build Docker image, install deps, compile assets
npm run dev            # Start Docker server + SCSS watcher (concurrently)
npm run serve          # Start Docker server only
npm run serve:stop     # Stop Docker container
npm run build          # Vendor copy + compile SCSS (admin + default theme, expanded + minified)
npm run watch          # Watch SCSS for changes
npm run vendor:copy    # Copy vendor assets from node_modules
npm run version        # Update version string across all files
```

### Composer Scripts (Inside Container or Standalone)

```bash
composer test              # All tests (unit + integration)
composer test:unit         # Unit tests only
composer test:integration  # Integration tests only
composer analyse           # PHPStan static analysis
composer cs                # PHP CodeSniffer check
composer cs:fix            # Auto-fix coding standard violations
```

### Project Configuration

| File | Purpose |
|------|---------|
| `config.sample.php` | Template config — copied to `config.php` on first setup |
| `phpunit.xml` | PHPUnit test suites (unit + integration), coverage source |
| `phpcs.xml.dist` | MinimalCMS coding standards ruleset |
| `Dockerfile` | PHP 8.2 + Apache + Composer dev image |
| `docker-compose.yml` | Container ports, volumes, environment |

---

## License

MinimalCMS is licensed under the [MIT License](LICENSE).

You are free to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of this software, subject to the conditions in the LICENSE file.
