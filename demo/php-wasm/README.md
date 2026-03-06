# PHP-WASM Demo

This project can be previewed in the browser using PHP compiled to WebAssembly.

## Run

From the project root:

```bash
npm run demo:wasm
```

Then open:

```text
http://127.0.0.1:9400/
```

## GitHub Pages

This repository includes a workflow that deploys a browser demo to GitHub Pages from `main`:

- Workflow: `.github/workflows/demo-pages.yml`
- Demo URL: `https://<owner>.github.io/<repo>/`

The Pages bundle contains:

- Static demo shell from `demo/php-wasm/pages/`
- A generated `site.zip` with a sanitized copy of the CMS files

To enable this in GitHub:

1. Open repository Settings -> Pages
2. Set source to GitHub Actions
3. Push to `main` or run the `Demo Pages` workflow manually

## Notes

- The command uses `@wp-playground/cli` and auto-mounts this repository.
- This is intended for quick demos and previews, not production hosting.
- The entire `demo/` directory is excluded from release archives.
