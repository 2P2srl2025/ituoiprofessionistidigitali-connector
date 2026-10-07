<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use ITuoiProfessionistiDigitali\Connector\ConnectorConfig;
use ITuoiProfessionistiDigitali\Connector\Http\Controllers\WebhookController;
use ITuoiProfessionistiDigitali\Connector\Http\Middleware\VerifyPlatformSignature;

$path = resolve(ConnectorConfig::class)->webhookPath;

if ($path !== null)
{
    Route::post($path, WebhookController::class)
        ->middleware(VerifyPlatformSignature::class)
        ->name('platform.webhook');
}
