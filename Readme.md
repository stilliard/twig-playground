Twig Playground
=================

Allows a simple way to set variables via JSON, manage multiple "files", and quickly test compiling them via Twig to HTML.

## Requirements

- PHP 8.5+ with Composer or **Docker** (recommended method)

## Running with Docker

Build and start (available at http://127.0.0.1:8080):

```bash
make docker-up
```

To use a different port, pass `PORT`:

```bash
make docker-up PORT=9090
```

Stop:

```bash
make docker-down
```

## Running locally (without Docker)

Install dependencies:

```bash
make update
```

Start the built-in PHP server:

```bash
make server
```

Then open http://localhost:8000 in your browser.

## License

MIT - see [LICENSE](LICENSE).
