
update:
	composer install

server:
	php -S localhost:8000

docker-build:
	docker build -t twig-playground .

PORT=8088
docker-run:
	docker run --rm -p $(PORT):80 twig-playground

docker-dev:
	docker run --rm -p $(PORT):80 -v $(PWD):/var/www/html twig-playground
