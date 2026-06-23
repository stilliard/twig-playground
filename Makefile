-include .env
export

PROJECT=twig-playground
PORT ?= 8080

update:
	composer install

serve:
	php -S localhost:$(PORT)

docker-up:
	docker compose -p $(PROJECT) up --build

docker-down:
	docker compose -p $(PROJECT) down
