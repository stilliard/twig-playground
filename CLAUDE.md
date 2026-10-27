# Twig Playground

Web tool for writing Twig templates against JSON variables and seeing the rendered result live. Has real users, so take care with changes to default behaviour.

## Stack

- PHP 8.5 + Twig 3, rendered inside Twig's `SandboxExtension` with an allowlist of tags/filters/functions (`index.php`).
- No framework or build step: `index.php` (render + page), `render.php` (preview iframe), `app.js` (jQuery + CodeMirror 5 from cdnjs), `style.css` (light/dark via `data-theme` on `<html>`, set from the system theme or the header toggle's localStorage choice).
- The page POSTs back to itself with `ajax=1` (debounced as you type) and gets JSON back: output, status, iframe src.
- The preview iframe loads `render.php` with a single-use session token and a strict CSP. Each render issues a new token, so only the latest request may win (app.js aborts in-flight requests).
- The first file in `files[...]` is the entry template; the others can be included or extended.

## Running

Local PHP is too old, so always use Docker: `make docker-up` (port comes from `.env`, currently `8023`).

There's no volume mount. Files are copied in at build time, so rebuild after edits:

```bash
docker compose -p twig-playground up --build -d
```

## Browser testing with playwright-cli

`playwright-cli` is installed globally. Run it from the scratchpad, because it writes `.playwright-cli/` logs into the current directory.

```bash
playwright-cli open http://127.0.0.1:8023/
playwright-cli resize 2000 1385
playwright-cli screenshot --filename=page.png
playwright-cli snapshot                      # accessibility tree with element refs
playwright-cli click '#add-file-btn'         # CSS selector or snapshot ref
playwright-cli eval '() => document.querySelector("#render-status").innerText'
playwright-cli run-code "async page => { await page.emulateMedia({ colorScheme: 'dark' }); }"
playwright-cli close
```

- Use `-s=<name>` for a separate named session.
- CodeMirror editors are reachable via `el.nextSibling.CodeMirror` on their textarea, or `.CodeMirror` elements' `.CodeMirror` property. Use `setValue()` to simulate typing.
- The preview content is at `document.querySelector('#render-frame').contentDocument.body`.
