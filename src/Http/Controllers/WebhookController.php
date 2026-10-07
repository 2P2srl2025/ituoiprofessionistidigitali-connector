<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector\Http\Controllers;

use Exception;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use ITuoiProfessionistiDigitali\Connector\Data\EnvelopeData;
use ITuoiProfessionistiDigitali\Connector\Events\PlatformEventReceived;
use TypeError;

/**
 * The webhook of the system: answers the verification of the platform and hands every other event to the application.
 */
final readonly class WebhookController
{
    public function __invoke(Request $request, Dispatcher $events): JsonResponse|Response
    {
        try
        {
            $envelope = EnvelopeData::from($request->json()->all());
        }
        catch (Exception|TypeError)
        {
            return response()->json(['message' => 'Busta non valida.'], 400);
        }

        if ($envelope->isVerification())
        {
            return response()->json(['challenge' => $envelope->payload['challenge'] ?? null]);
        }

        if ($envelope->sender === null)
        {
            return response()->json(['message' => 'Busta non valida.'], 400);
        }

        $events->dispatch(new PlatformEventReceived($envelope));

        return response()->noContent();
    }
}
