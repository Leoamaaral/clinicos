.PHONY: build up down logs shell migrate fresh deploy

build:
	docker compose build

up:
	docker compose up -d

down:
	docker compose down

logs:
	docker compose logs -f app

shell:
	docker compose exec app bash

migrate:
	docker compose exec app php artisan migrate --force

fresh:
	docker compose exec app php artisan migrate:fresh --seed --force

deploy: build up
	@echo "Aguardando MySQL..."
	@sleep 15
	docker compose exec app php artisan migrate --force
	docker compose exec app php artisan storage:link --force
	@echo "Deploy concluído. Acesse http://localhost (ou APP_PORT no .env)"
