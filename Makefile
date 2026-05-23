
update:
	composer install

server:
	php -S localhost:8000

PORT ?= 8080

docker-up:
	PORT=$(PORT) docker compose up --build

docker-down:
	docker compose down
