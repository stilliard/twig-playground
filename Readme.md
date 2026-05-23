Twig Playground
=================

Allows a simple way to set variables via JSON, manage multiple "files", and quickly test compiling them via Twig to HTML.

## Requirements

- PHP 8.5+ with Composer, **or**
- Docker (recommended)

## Running with Docker

Build the image:

```bash
docker build -t twig-playground .
```

Run it (available at http://localhost:8088):

```bash
docker run --rm -p 8088:80 twig-playground
```

Optionally for local development with live file editing (mounts the current directory):

```bash
docker run --rm -p 8088:80 -v $(PWD):/var/www/html twig-playground
```

Or use the provided Makefile shortcuts:

```bash
make docker-build   # build the image
make docker-run     # run on port 8088
make docker-dev     # optionally run with live file mounting
```

## Running locally (without Docker)

Install dependencies:

```bash
composer install
```

Start the built-in PHP server:

```bash
make server
```

Then open http://localhost:8000 in your browser.

## License

MIT - see [LICENSE](LICENSE).
