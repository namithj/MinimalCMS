# MinimalCMS

![Version](https://img.shields.io/badge/version-0.0.1-blue)
![PHP](https://img.shields.io/badge/PHP-8.0%2B-8892BF)
![License](https://img.shields.io/badge/license-GPL--2.0--or--later-blue)
![Status](https://img.shields.io/badge/status-early%20development-orange)

> A lightweight, flat-file CMS built with a WordPress-inspired architecture — no database, no bloat, just PHP and Markdown.

---

## What is MinimalCMS?

MinimalCMS is a file-based content management system that borrows the familiar patterns of WordPress (hooks, template hierarchy, plugins, themes, shortcodes) without requiring a database, a framework, or complex infrastructure.

Content is written in **Markdown**, metadata lives in **JSON sidecars**, user data is **sodium-encrypted at rest**, and the entire system boots from a single `index.php` front controller. If you can upload files to a PHP + Apache host, you can run MinimalCMS.

It is designed as a learning reference, a lightweight CMS for small sites, and a clean base for developers who want WordPress-style extensibility without the overhead.

---

## At a Glance

| Property | Detail |
|---|---|
| **Storage** | Flat files (Markdown + JSON) |
| **Language** | PHP 8.0+ |
| **Server** | Apache + `mod_rewrite` |
| **Dependencies** | Parsedown (Markdown parser) |
| **Admin UI** | Built-in dashboard with Markdown editor |
| **Auth** | Encrypted user file, bcrypt passwords, HMAC nonces |
| **Extensibility** | Plugin & theme system with WordPress-style hooks |

---

## Features

**Content**
- Write pages and posts in Markdown (`.md`) with JSON sidecar metadata
- Custom content types, archive routes, and permalink slugs
- Shortcode parser — `[greet name="World"]` syntax, identical to WordPress

**Security**
- User data encrypted with `sodium_crypto_secretbox` (256-bit key)
- Passwords hashed with bcrypt
- All forms protected by HMAC-based nonces
- `mc-data/` is locked behind Apache `Deny all` + PHP `die()` double guard

**Extensibility**
- WordPress-style actions & filters with `mc_add_action()` / `mc_add_filter()`
- Plugin lifecycle (activate, deactivate) driven by `config.json`
- Theme system with template hierarchy, child theme support, and `theme.json` manifests
- MU-plugins directory for must-use code

**Developer Experience**
- Single front controller — no framework magic
- File-based PHP cache with TTL
- Full PHPUnit test suite (unit + integration)
- PHPStan + PHPCS (WordPress Coding Standards) pre-configured
- SCSS build pipeline via npm + Sass

---

## Requirements

| Requirement | Version |
|---|---|
| PHP | 8.0+ (sodium bundled since 7.2) |
| Web server | Apache with `mod_rewrite` |
| Disk access | Write permission on the project root |

No database. No framework. No container.

---

## Quick Start

```bash
# 1. Clone or download
git clone https://github.com/your-org/minimalcms.git my-site
cd my-site

# 2. Install PHP dev dependencies (optional — only needed for tests/linting)
composer install

# 3. Point Apache at the project root and enable mod_rewrite

# 4. Open the admin panel in your browser
open http://your-site.local/mc-admin/
```

The **setup wizard** runs automatically on the first visit. It will:
- Generate a `secret_key` and `encryption_key` in `config.json`
- Create the initial administrator account
- Redirect you to the dashboard

---

## How It Works

```
Browser Request
      │
      ▼
 index.php  ──► mc-blog-header.php
                      │
                      ├─ mc-load.php        (constants, autoload)
                      ├─ mc-settings.php    (config, hooks, plugins, theme)
                      ├─ rewrite.php        (resolve URL → content item)
                      ├─ template-loader.php (pick best template file)
                      └─ theme template     (render HTML)
```

Every request flows through a single entry point. There is no routing framework — just PHP includes and a clean hook system that plugins and themes tap into at well-defined points.

---

## Directory Structure

```
minimalcms/
├── config.json                  # Site configuration (keys, active theme/plugins)
├── index.php                    # Front controller — all requests start here
├── .htaccess                    # Rewrite all non-file requests to index.php
├── mc-load.php                  # Bootstrap: constants, autoload, early hooks
├── mc-settings.php              # Boot sequence: config → hooks → plugins → theme
├── mc-blog-header.php           # Orchestrates: boot → route → render
├── mc-includes/                 # Core PHP libraries
│   ├── version.php
│   ├── load.php                 # Constants, path helpers
│   ├── class-mc-error.php       # Error object
│   ├── hooks.php                # Actions & filters engine
│   ├── formatting.php           # Escaping & sanitisation
│   ├── http.php                 # Nonces, redirects, JSON responses
│   ├── cache.php                # File-based cache
│   ├── capabilities.php         # Roles & capabilities
│   ├── user.php                 # Encrypted user CRUD & auth
│   ├── content.php              # Content type queries & CRUD
│   ├── content-types.php        # Default 'page' type registration
│   ├── markdown.php             # Parsedown wrapper
│   ├── rewrite.php              # URL routing engine
│   ├── plugin.php               # Plugin lifecycle
│   ├── theme.php                # Theme discovery & loading
│   ├── template-loader.php      # Template hierarchy resolver
│   ├── template-tags.php        # Head/footer hooks, asset enqueuing
│   ├── shortcodes.php           # Shortcode parser
│   ├── default-filters.php      # Core hook wiring
│   └── lib/
│       └── Parsedown.php        # MIT-licensed Markdown parser
├── mc-data/                     # Protected data (users file)
│   └── .htaccess                # Deny all access
├── mc-content/                  # User content
│   ├── pages/                   # Page markdown + JSON files
│   │   ├── index/
│   │   ├── about/
│   │   └── contact/
│   ├── plugins/
│   │   └── hello-world/         # Example plugin
│   └── themes/
│       └── default/             # Default theme (12 templates)
└── mc-admin/                    # Admin panel
    ├── admin.php                # Admin bootstrap + first-run detection
    ├── login.php                # Authentication
    ├── setup.php                # First-run wizard
    ├── index.php                # Dashboard
    ├── pages.php                # Content listing
    ├── edit-page.php            # Markdown editor
    ├── users.php                # User management
    ├── user-edit.php            # Create/edit user
    ├── plugins.php              # Plugin management
    ├── themes.php               # Theme management
    ├── settings.php             # Site settings
    ├── admin-ajax.php           # AJAX handler
    ├── admin-header.php         # Admin layout header
    ├── admin-footer.php         # Admin layout footer
    ├── assets/
    │   ├── css/admin.css        # Compiled admin styles
    │   ├── js/admin.js          # Admin JavaScript
    │   ├── js/editor.js         # Markdown editor (EasyMDE)
    │   ├── vendor/              # Third-party libs (copied from node_modules)
    │   └── src/scss/            # Admin SCSS source
    └── includes/
        └── admin-functions.php  # Admin helpers & menu
```

---

## Content Format

Each content item lives in its own folder with two files:

```
mc-content/pages/my-page/
├── my-page.md       # Markdown body
└── my-page.json     # Metadata (title, slug, status, excerpt, etc.)
```

### JSON sidecar example

```json
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

---

## Plugin Development

Plugins live in `mc-content/plugins/{plugin-name}/{plugin-name}.php` and use a WordPress-style file header:

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

Activate plugins from **Admin → Plugins** or by adding the path to `config.json`'s `active_plugins` array.

---

## Theme Development

Themes live in `mc-content/themes/{theme-name}/` and require a `theme.json` manifest:

```json
{
    "name": "My Theme",
    "version": "1.0.0",
    "author": "You",
    "description": "A custom theme.",
    "parent": ""
}
```

### Template Hierarchy

The template loader follows a WordPress-like cascade:

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

MinimalCMS fires hooks throughout its lifecycle, prefixed with `mc_`:

| Hook | Type | Description |
|------|------|-------------|
| `mc_muplugins_loaded` | Action | After MU plugins load |
| `mc_plugins_loaded` | Action | After regular plugins load |
| `mc_init` | Action | Full system ready |
| `mc_loaded` | Action | Boot complete |
| `mc_template_redirect` | Action | Before template renders |
| `mc_head` | Action | Inside `<head>` |
| `mc_footer` | Action | Before `</body>` |
| `mc_admin_init` | Action | Start of admin page |
| `mc_admin_menu` | Action | Build admin menu |
| `mc_the_content` | Filter | Content HTML output |
| `mc_document_title` | Filter | Page `<title>` |
| `mc_body_class` | Filter | Body CSS classes |
| `mc_user_can` | Filter | Permission check |

---

## Security

- **User file encryption**: All user data (including bcrypt-hashed passwords) is encrypted with `sodium_crypto_secretbox` using a 256-bit key from `config.json`
- **Nonces**: HMAC-based tokens protect all form submissions and admin actions
- **Capabilities**: Role-based permission system with 4 default roles (administrator, editor, author, contributor)
- **Data protection**: `mc-data/` folder has an `.htaccess` deny rule and a PHP `die()` guard in the users file

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

Then create content in `mc-content/posts/{slug}/{slug}.md` + `.json`.

---

## Development

### Running Tests

```bash
# All tests
composer test

# Unit tests only
composer test:unit

# Integration tests only
composer test:integration
```

### Static Analysis

```bash
# PHPStan (level configured in phpstan.neon)
composer analyse

# PHP CodeSniffer (WordPress Coding Standards)
composer cs

# Auto-fix coding standard violations
composer cs:fix
```

### Building Assets

```bash
# Install Node dependencies
npm install

# Full build (vendor copy + compile SCSS for admin and default theme)
npm run build

# Watch SCSS for changes during development
npm run watch
```

---

## License

MinimalCMS is licensed under the [GNU General Public License v2.0 or later](https://www.gnu.org/licenses/gpl-2.0.html) (GPL-2.0-or-later).

You are free to use, modify, and distribute this software under the terms of the GPL. Any derivative work must also be distributed under the same license.

> Note: The bundled [Parsedown](https://github.com/erusev/parsedown) library remains under its own MIT license.
