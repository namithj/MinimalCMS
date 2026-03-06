import { PHP } from 'https://cdn.jsdelivr.net/npm/@php-wasm/web@2.0.16/+esm';

const statusNode = document.getElementById('status');
const pathNode = document.getElementById('path');
const openButton = document.getElementById('open');
const previewFrame = document.getElementById('preview');

let php = null;

function setStatus(message) {
  statusNode.textContent = message;
}

function ensureLeadingSlash(path) {
  if (!path) {
    return '/';
  }
  return path.startsWith('/') ? path : '/' + path;
}

function dirname(path) {
  const idx = path.lastIndexOf('/');
  if (idx <= 0) {
    return '/www';
  }
  return path.slice(0, idx);
}

function normalizeZipPath(path) {
  const trimmed = path.replace(/^\.\//, '').replace(/^\//, '');
  return '/www/' + trimmed;
}

function decodeResponseText(response) {
  if (typeof response.text === 'string') {
    return response.text;
  }
  if (response.bytes) {
    return new TextDecoder().decode(response.bytes);
  }
  return '';
}

function toRequestPath(input, currentPath) {
  if (!input) {
    return currentPath;
  }
  if (/^https?:\/\//i.test(input)) {
    const url = new URL(input);
    return url.pathname + url.search;
  }
  if (input.startsWith('/')) {
    return input;
  }
  const base = new URL(currentPath, 'https://minimalcms.local');
  return new URL(input, base).pathname;
}

async function mountProjectZip() {
  setStatus('Downloading demo filesystem...');
  const res = await fetch('./site.zip', { cache: 'no-store' });
  if (!res.ok) {
    throw new Error('Failed to fetch site.zip (' + res.status + ')');
  }

  const zipData = await res.arrayBuffer();
  const zip = await window.JSZip.loadAsync(zipData);
  const entries = Object.values(zip.files);

  setStatus('Mounting files into PHP runtime...');

  const directories = entries.filter((entry) => entry.dir);
  const files = entries.filter((entry) => !entry.dir);

  for (const entry of directories) {
    const dest = normalizeZipPath(entry.name);
    await php.mkdirTree(dest);
  }

  for (const entry of files) {
    const dest = normalizeZipPath(entry.name);
    await php.mkdirTree(dirname(dest));
    const content = await entry.async('uint8array');
    php.writeFile(dest, content);
  }
}

async function inlineStyles(doc, currentPath) {
  const stylesheetLinks = Array.from(doc.querySelectorAll('link[rel="stylesheet"][href]'));

  for (const link of stylesheetLinks) {
    const href = link.getAttribute('href') || '';
    if (/^https?:\/\//i.test(href)) {
      continue;
    }

    const targetPath = toRequestPath(href, currentPath);
    try {
      const cssResponse = await php.request({ method: 'GET', url: targetPath });
      const cssText = decodeResponseText(cssResponse);
      const styleTag = doc.createElement('style');
      styleTag.textContent = cssText;
      link.replaceWith(styleTag);
    } catch {
      link.remove();
    }
  }
}

async function renderPath(path) {
  const safePath = ensureLeadingSlash(path.trim());

  setStatus('Rendering ' + safePath + ' ...');
  const response = await php.request({ method: 'GET', url: safePath });
  const html = decodeResponseText(response);

  const doc = new DOMParser().parseFromString(html, 'text/html');
  await inlineStyles(doc, safePath);

  for (const script of Array.from(doc.querySelectorAll('script'))) {
    script.remove();
  }

  previewFrame.srcdoc = '<!doctype html>\n' + doc.documentElement.outerHTML;
  setStatus('Ready: ' + safePath);
}

async function bootstrap() {
  try {
    openButton.disabled = true;

    setStatus('Loading PHP-WASM runtime...');
    php = await PHP.load('8.3', {
      requestHandler: {
        documentRoot: '/www',
      },
    });

    await php.mkdirTree('/www');
    await mountProjectZip();
    await renderPath(pathNode.value);

    openButton.disabled = false;
  } catch (error) {
    setStatus('Failed to start demo. See console for details.');
    console.error(error);
  }
}

openButton.addEventListener('click', async () => {
  if (!php) {
    return;
  }
  openButton.disabled = true;
  try {
    await renderPath(pathNode.value);
  } finally {
    openButton.disabled = false;
  }
});

pathNode.addEventListener('keydown', async (event) => {
  if (event.key !== 'Enter' || !php) {
    return;
  }

  event.preventDefault();
  openButton.disabled = true;
  try {
    await renderPath(pathNode.value);
  } finally {
    openButton.disabled = false;
  }
});

void bootstrap();
