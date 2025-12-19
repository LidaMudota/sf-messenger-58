# sf-messenger-58

## Подготовка окружения Laravel
1. Скопируйте переменные окружения и задайте значения под Docker:
   ```powershell
   copy src\.env.example src\.env
   notepad src\.env
   ```

2. Запустите контейнеры (Docker Desktop должен быть включён):
   ```powershell
   docker compose up -d --build
   ```

3. Установите зависимости бэкенда и подготовьте Laravel (внутри контейнера php):
   ```powershell
   docker compose exec php bash -lc "cd /var/www/src && composer install"
   docker compose exec php bash -lc "cd /var/www/src && php artisan key:generate"
   docker compose exec php bash -lc "cd /var/www/src && php artisan storage:link"
   docker compose exec php bash -lc "cd /var/www/src && php artisan migrate --seed"
   ```

> Команда `php artisan reverb:start` запускается автоматически в контейнере `php` и доступна на `ws://localhost:6001`.

## Почта через MailHog
- Веб-интерфейс: [http://localhost:8025](http://localhost:8025)
- SMTP: `mailhog:1025` (значения уже указаны выше в `.env`).

## Frontend (Vite из папки `src`)
1. Установите зависимости:
   ```powershell
   cd src
   npm install
   ```
2. Запустите Vite в режиме разработчика:
   ```powershell
   npm run dev -- --host --port 5173
   ```
3. Приложение будет доступно по адресу [http://localhost:8080](http://localhost:8080) (Laravel + Nginx), а ассеты Vite — на [http://localhost:5173](http://localhost:5173).

## Дополнительные сервисы и полезные команды
- Логи всех контейнеров: `docker compose logs -f`
- Логи MySQL/Redis/MailHog: `docker compose logs -f mysql|redis|mailhog`
- Остановка окружения: `docker compose down`
- Перезапуск после изменения Dockerfile/зависимостей: `docker compose up -d --build`

## Что разворачивается
- **Laravel (PHP-FPM)** — порт `8080` через Nginx.
- **MySQL 8** — внешний порт `3307` (внутри сети Docker — `mysql:3306`).
- **Redis** — порт `6379`.
- **Reverb** — порт `6001` для веб-сокетов (ключи в `.env`).
- **MailHog** — SMTP на `1025`, веб UI на `8025`.
- **Vite** — порт `5173` для hot-reload.