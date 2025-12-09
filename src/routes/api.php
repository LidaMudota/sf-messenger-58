<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

// Breeze API (стандартные контроллеры аутентификации)
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
// use App\Http\Controllers\Auth\VerifyEmailController; // ✖ больше не нужен
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\NewPasswordController;

// Доп. контроллеры проекта
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\UserLookupController;

/*
|--------------------------------------------------------------------------
| ПУБЛИЧНЫЕ РОУТЫ (Breeze API)
|--------------------------------------------------------------------------
| guest:sanctum — чтобы авторизованные пользователи не дергали эти эндпоинты
| throttle — базовая защита от брута
*/
Route::post('/register', [RegisteredUserController::class, 'store'])
    ->middleware(['guest:sanctum', 'throttle:10,1']);

Route::post('/login', [AuthenticatedSessionController::class, 'store'])
    ->middleware(['guest:sanctum', 'throttle:20,1']);

Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
    ->middleware(['guest:sanctum', 'throttle:6,1']);

Route::post('/reset-password', [NewPasswordController::class, 'store'])
    ->middleware(['guest:sanctum', 'throttle:6,1']);

/* Отправка письма подтверждения авторизованному пользователю */
Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
    ->middleware(['auth:sanctum', 'throttle:6,1']);

/*
|--------------------------------------------------------------------------
| ВАЖНО: роут подтверждения email из API убрали
|--------------------------------------------------------------------------
| Раньше здесь был GET /verify-email/{id}/{hash} с именем verification.verify.
| Он конфликтовал с web-маршрутом из routes/auth.php и ломал валидацию
| (Request::user() == null → getKey() on null).
| Теперь единственный маршрут с именем verification.verify — web-вариант.
*/

/* Токен-логин (если используешь персональные токены) */
Route::post('/token-login', [AuthController::class, 'tokenLogin'])
    ->middleware(['guest:sanctum', 'throttle:20,1']);

/* Выход по токену (Sanctum) */
Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth:sanctum');

/*
|--------------------------------------------------------------------------
| ЗАЩИЩЁННЫЕ РОУТЫ (Sanctum)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    // текущий пользователь (для проверки email_verified_at и профиля)
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // чаты
    Route::get('/chats', [ChatController::class, 'index']);
    Route::post('/chats', [ChatController::class, 'store']); // direct|group
    Route::post('/chats/{chat}/participants', [ChatController::class, 'addParticipant']); // из контактов
    Route::patch('/chats/{chat}/mute', [ChatController::class, 'toggleMute']); // mute/unmute

    // сообщения (привязаны к чату)
    Route::get('/messages/{chat}', [MessageController::class, 'index']); // список с пагинацией
    Route::post('/messages/{chat}', [MessageController::class, 'store']); // отправка
    Route::patch('/messages/{message}', [MessageController::class, 'update']); // редактирование
    Route::delete('/messages/{message}', [MessageController::class, 'destroy']); // удаление
    Route::post('/messages/{message}/forward', [MessageController::class, 'forward']); // переслать

    // профиль
    Route::get('/profile/me', [ProfileController::class, 'show']);
    Route::patch('/profile', [ProfileController::class, 'update']); // nickname, avatar, email_hidden

    // контакты
    Route::get('/contacts', [ContactController::class, 'index']); // список контактов
    Route::post('/contacts', [ContactController::class, 'store']); // добавить по nickname/email
    
    // поиск пользователей
    Route::get('/users/search', UserLookupController::class); // email/nickname
});
