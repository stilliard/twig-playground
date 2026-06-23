
update:
	composer install

PORT ?= 8023

serve:
	php -S localhost:$(PORT)

docker-up:
	PORT=$(PORT) docker compose up --build

docker-down:
	docker compose down
