# Welcome to MinimalCMS

Your CMS is installed and running. Here's everything you need to know to get started.

---

## 1. Log in to the Admin Panel

Head to `/mc-admin/` in your browser. Use the credentials you created during the setup wizard. If you haven't run the wizard yet, visit `/mc-admin/setup.php`.

---

## 2. Create Your First Page

From the admin dashboard, click **Pages → Add New**. Give it a title and a slug, then write your content using **Markdown**.

Each page is stored as two files inside `mc-content/pages/<slug>/`:

- `<slug>.md` — the body text (Markdown)
- `<slug>.json` — metadata (title, status, author, etc.)

You can edit either file directly on disk or use the built-in editor.

---

## 3. Manage Plugins

Plugins live in `mc-content/plugins/<plugin-name>/<plugin-name>.php`. Activate or deactivate them from **Admin → Plugins**.

To write your own plugin, create the folder and PHP file, then add a file header:

```php
<?php
/**
 * Plugin Name: My Plugin
 * Description: Does something useful.
 * Version:     1.0.0
 * Author:      You
 */

mc_add_filter( 'mc_the_content', function ( string $content ): string {
    return $content . '<p>Hello from my plugin!</p>';
} );
```

---

## 4. Switch Themes

Themes live in `mc-content/themes/<theme-name>/`. The active theme is set in **Admin → Themes**. The default theme shipped with MinimalCMS is a clean starting point — copy it and rename the folder to begin your own.

---

## 5. Add Users

Go to **Admin → Users → Add New** to create additional accounts. There are four built-in roles:

| Role | Can do |
|---|---|
| Administrator | Everything |
| Editor | Publish and manage any content |
| Author | Publish and manage their own content |
| Contributor | Write drafts, cannot publish |

User data is encrypted at rest with a 256-bit key generated during setup.

---

## 6. Configure Site Settings

Visit **Admin → Settings** to update the site title, tagline, and other core options. Settings are stored in `mc-data/settings/core.general.json`.

---

## 7. Use Shortcodes

MinimalCMS has a built-in shortcode parser. Register a shortcode in a plugin or in `mc-content/mu-plugins/`:

```php
mc_add_shortcode( 'greeting', function ( array $atts ): string {
    $name = $atts['name'] ?? 'World';
    return "<strong>Hello, {$name}!</strong>";
} );
```

Then use it in any page or post:

```
[greeting name="MinimalCMS"]
```

---

## 8. Content Format Reference

Every content item lives in its own directory:

```
mc-content/pages/my-page/
├── my-page.md      ← Markdown body
└── my-page.json    ← Metadata
```

The `.json` sidecar supports these fields: `title`, `slug`, `status` (`publish` / `draft`), `excerpt`, `author`, `template`, `parent`, `order`, `meta`.

---

*You can edit or delete this page any time from **Admin → Pages**.*
