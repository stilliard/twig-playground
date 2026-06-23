-include .env
export

PORT ?= 8080

update:
	composer install

serve:
	php -S localhost:$(PORT)

docker-up:
	docker compose up --build

docker-down:
	docker compose down
