DC = docker compose
PHP = $(DC) exec php bash -lc "cd /var/www/src && "
NODE = $(DC) exec node bash -lc "cd /app && "

.PHONY: up down restart build logs phpsh nodesh key link env migrate seed fresh ws tinker npm dev prod status mysql-logs redis-logs mailhog

up:
	$(DC) up -d

down:
	$(DC) down

restart:
	$(DC) down
	$(DC) up -d

build:
	$(DC) build --no-cache

logs:
	$(DC) logs -f

phpsh:
	$(DC) exec php bash

nodesh:
	$(DC) exec node sh

# --- Laravel utility ---
key:
	$(PHP) php artisan key:generate

link:
	$(PHP) php artisan storage:link || true

env:
	$(PHP) 'test -f .env || cp .env.example .env'

migrate:
	$(PHP) php artisan migrate

seed:
	$(PHP) php artisan db:seed

fresh:
	$(PHP) php artisan migrate:fresh --seed

ws:
	$(PHP) 'php artisan websockets:serve & disown || true'

tinker:
	$(PHP) php artisan tinker

status:
	$(DC) ps

npm:
	$(NODE) npm i

dev:
	$(NODE) npm run dev

prod:
	$(NODE) npm run build

# --- Service logs ---
mysql-logs:
	$(DC) logs -f mysql

redis-logs:
	$(DC) logs -f redis

mailhog:
	$(DC) logs -f mailhog
