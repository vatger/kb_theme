<?php

use Bookstack\Facades\Theme;
use BookStack\Theming\ThemeEvents;
use BookStack\Theming\ThemeViews;
use Illuminate\Support\Facades\Route;
use Vatger\Auth\Controller\WelcomeController;
use Vatger\Auth\Controllers\AuthController;

require_once __DIR__ . '/src/loader.php';

Theme::listen(ThemeEvents::THEME_REGISTER_VIEWS, function (ThemeViews $views) {
    if (getenv("HIDE_WELCOME") != "true") {
        $views->renderBefore('shelves.parts.list', 'content.welcome');
        $views->renderBefore('books.parts.list', 'content.welcome');
    }
});

Theme::listen(ThemeEvents::APP_BOOT, function () {
    registerRoutes();
});

function registerRoutes(): void
{
    Route::prefix('/vatger')->middleware(['web'])->group(function () {
        Route::prefix('/oauth')->group(function () {
                Route::get("/login", [
                    AuthController::class,
                    'login'
                ]);

                Route::get("/callback", [
                    AuthController::class,
                    'callback'
                ]);

                Route::post('/logout', [
                    AuthController::class,
                    'logout'
                ]);
        });

        Route::middleware(['auth'])->group(function () {
            Route::post("/welcome", [WelcomeController::class, 'handleWelcome']);
        });
    });
}
