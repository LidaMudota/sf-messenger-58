# sf-messenger-58

## Клонирование репозитория
```powershell
git clone https://github.com/LidaMudota/sf-messenger-58
cd sf-messenger-58
```

## Подготовка переменных окружения
1. Скопируйте пример `.env` для Laravel:
   ```powershell
   Copy-Item .\src\.env.example .\src\.env
   ```
2. Установите необходимые секреты в `.env`:

- `APP_KEY`
- `DB_PASSWORD`
- `REVERB_APP_KEY`
- `REVERB_APP_SECRET`
- и т.д.

## Запуск через Docker (рекомендуется)
Все сервисы поднимаются одной командой: PHP-FPM + Laravel, Nginx, MySQL, Redis, MailHog, Reverb (websocket) и Node-контейнер для фронтенда.

1. Соберите и запустите контейнеры:
   ```powershell
   docker compose up -d --build
   ```
2. Установите зависимости Laravel и выполните миграции внутри PHP-контейнера:
   ```powershell
   docker compose exec php composer install
   docker compose exec php php artisan migrate --seed
   docker compose exec php php artisan storage:link
   ```
   `php`-контейнер уже запускает `php artisan reverb:start` на `:6001`, поэтому вебсокеты будут готовы сразу после старта.
3. Соберите фронтенд (Vite + Vue) внутри контейнера Laravel:
   ```powershell
   docker compose exec php npm ci
   docker compose exec php npm run build
   ```
   Для разработки в режиме hot-reload вместо сборки используйте `npm run dev -- --host --port 5173` в отдельной сессии:
   ```powershell
   docker compose exec php npm run dev -- --host --port 5173
   ```

## Полезные адреса
- Приложение Laravel: http://localhost:8080/messenger
- API/Vite (если dev-режим): http://localhost:5173
- WebSocket Reverb: ws://localhost:6001
- MailHog UI: http://localhost:8025 (SMTP на `localhost:1025`)
- MySQL: `localhost:3307` (пользователь `sf58`, пароль `sf58pass`, база `sf58`)
- Redis: `localhost:6379`