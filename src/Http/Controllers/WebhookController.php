<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector\Http\Controllers;

use Exception;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use ITuoiProfessionistiDigitali\Connector\Contract;
use ITuoiProfessionistiDigitali\Connector\Data\CounterpartySelectedData;
use ITuoiProfessionistiDigitali\Connector\Data\EnvelopeData;
use ITuoiProfessionistiDigitali\Connector\Events\PlatformEventReceived;
use ITuoiProfessionistiDigitali\Connector\Outbox\TransactionOutbox;
use TypeError;

/**
 * The webhook of the system: answers the verification of the platform and hands every other event to the application,
 * from a member or from the platform (rule L6).
 *
 * The selection of a counterparty reaches the outbox before the listeners: the next change of the model goes out
 * with a revision higher than the one of the platform (rule L4).
 */
final readonly class WebhookController
{
    public function __invoke(Request $request, Dispatcher $events, TransactionOutbox $outbox): JsonResponse|Response
    {
        try
        {
            $envelope = EnvelopeData::from($request->json()->all());
            $selection = $envelope->type === Contract::COUNTERPARTY_SELECTED && $envelope->sender === null
                ? CounterpartySelectedData::from($envelope->payload)
                : null;
        }
        catch (Exception|TypeError)
        {
            return response()->json(['message' => 'Busta non valida.'], 400);
        }

        if ($envelope->isVerification())
        {
            return response()->json(['challenge' => $envelope->payload['challenge'] ?? null]);
        }

        if ($envelope->sender === null && !$envelope->isFromPlatform())
        {
            return response()->json(['message' => 'Busta non valida.'], 400);
        }

        if ($selection !== null)
        {
            $outbox->selected($selection->transaction);
        }

        $events->dispatch(new PlatformEventReceived($envelope));

        return response()->noContent();
    }
}
