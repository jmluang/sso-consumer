<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Jmluang\SsoConsumer\Http\Controllers\ConsumeController;

Route::get(
    config('sso-consumer.consume_path', '/admin-app/sso/consume'),
    ConsumeController::class,
)
    ->middleware(config('sso-consumer.consume_middleware', ['web']))
    ->name('sso-consumer.consume');
