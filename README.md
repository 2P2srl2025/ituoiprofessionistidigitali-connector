# I tuoi professionisti digitali: connector

Il contratto di [I tuoi professionisti digitali](https://ituoiprofessionistidigitali.it) in forma di codice, per i sistemi Laravel che si collegano al portale: DTO, client, firma dei webhook, validazione dei payload e comando di installazione.

Il portale è l'autorità sul contratto. Il pacchetto lo segue: se un dato non rispetta il contratto, la richiesta non parte. Il contratto completo, con ogni regola e il suo codice (E6, W4…), è `docs/contratto.md` nel repo del portale.

## Requisiti

PHP 8.4 o 8.5, Laravel 13.

## Installazione

```bash
composer require ituoiprofessionistidigitali/connector
php artisan platform:install
```

Il comando pubblica `config/platform.php` e deposita le istruzioni per gli agenti AI: in `.ai/rules/platform-connector.md` se il progetto usa quella convenzione, altrimenti in `AGENTS-platform-connector.md`. Dentro `vendor/` nessun agente le leggerebbe.

## Credenziali

Le rilascia l'operatore del portale, che le vede una volta sola:

```dotenv
PLATFORM_URL=https://staging.ituoiprofessionistidigitali.it
PLATFORM_CLIENT_ID=0199b6ef-…
PLATFORM_CLIENT_SECRET=…
PLATFORM_SIGNING_SECRET=…
```

Senza queste chiavi il sistema non è collegato e nulla si rompe: `ConnectorConfig::isConnected()` dice se usare il portale.

| Chiave | Predefinito | Cosa fa |
| --- | --- | --- |
| `PLATFORM_WEBHOOK_PATH` | `platform/webhook` | Percorso della rotta che riceve gli eventi; vuoto per non registrarla |
| `PLATFORM_REPLY_TO_PINGS` | `true` | Risponde da solo ai `platform.ping` degli aderenti |
| `PLATFORM_QUEUE` | coda predefinita | Coda della risposta ai ping |
| `PLATFORM_VALIDATE_PAYLOADS` | `true` | Controlla il payload sullo schema del catalogo prima di mandarlo |
| `PLATFORM_CATALOG_TTL` | `3600` | Secondi di cache del catalogo usato dalla validazione |
| `PLATFORM_TIMEOUT`, `PLATFORM_RETRIES` | `10`, `2` | Timeout in secondi e nuovi tentativi su errori 5xx e di rete |

## Collegamento

```php
use ITuoiProfessionistiDigitali\Connector\Facades\Platform;

// Dopo il deploy, o quando cambia l'URL del webhook
$system = Platform::present(route('platform.webhook'));

$system->status;   // SystemStatus::Pending finché il portale non ha verificato il webhook
```

La verifica la gestisce il pacchetto: la rotta `platform.webhook` risponde alla sfida del portale.

## Aderenti

```php
use ITuoiProfessionistiDigitali\Connector\Data\MemberData;

$members = Platform::syncMembers([
    MemberData::validateAndCreate([
        'external_ref' => 'struttura-1',
        'subject_type' => 'organization',
        'name' => 'Studio Rossi e Associati',
        'vat_number' => '01234567897',
        'tax_code' => null,
        'municipality' => 'Bari',
        'province' => 'BA',
        'typologies' => ['commercialisti'],
        'listed' => true,
    ]),
]);

$members[0]->id;   // l'id del portale: il sender dei tuoi eventi
```

L'elenco è **completo**: gli aderenti che mancano diventano inattivi.

```php
$page = Platform::searchMembers(typology: 'commercialisti', search: 'bianchi');

foreach ($page->members as $member) { /* id, name, vat_number, municipality, province, typologies */ }

$next = Platform::searchMembers(typology: 'commercialisti', cursor: $page->nextCursor);
```

## Eventi

```php
use ITuoiProfessionistiDigitali\Connector\Data\EnvelopeData;

$ping = EnvelopeData::make(
    type: 'platform.ping',
    schemaVersion: 1,
    sender: $mioAderente,
    recipient: $altroAderente,
    typology: 'commercialisti',
    payload: ['challenge' => bin2hex(random_bytes(12))],
);

$accepted = Platform::send($ping);   // 202 la prima volta, 200 se la stessa busta era già arrivata
```

Salva la busta prima di mandarla: dopo un timeout si rimanda la stessa, con lo stesso `event_id`.

Per ricevere, ascolta `PlatformEventReceived`, in coda e in modo idempotente su `event_id`:

```php
use Illuminate\Contracts\Queue\ShouldQueue;
use ITuoiProfessionistiDigitali\Connector\Events\PlatformEventReceived;

final class HandlePlatformEvent implements ShouldQueue
{
    public function handle(PlatformEventReceived $event): void
    {
        $envelope = $event->envelope;   // EnvelopeData con firma già verificata
    }
}
```

## Errori

Ogni rifiuto è una `PlatformRequestException`, con `->status`, `->errors` (chiavi puntate come `payload.challenge`), `->reason` per i 403 (`SystemStatus`) e `->existingEvent` per i 409. Senza credenziali il client lancia `PlatformNotConfiguredException`.

## Sistemi non Laravel

Il contratto è HTTP; il pacchetto è una comodità. La firma di un webhook si verifica così:

```text
firma = hex( HMAC-SHA256( PLATFORM_SIGNING_SECRET, X-Platform-Timestamp + "." + corpo grezzo ) )
```

`X-Platform-Signature` contiene una o due firme `v1=<hex>` separate da virgola: basta che una coincida. Va rifiutato con 401 un timestamp distante più di 5 minuti.

## Versioni

La major del pacchetto cambia a ogni rottura del contratto: un campo che sparisce, uno che diventa obbligatorio, una regola più stretta. Le versioni degli schemi di un tipo di evento si aggiungono e non cambiano mai: quelle fissate in `resources/schemas` sono confrontate con il portale da un contract test.

## Sviluppo

```bash
composer lint            # Pint e PHPStan al livello max
composer test            # Pest
composer test:coverage   # copertura al 100%, con PCOV o Xdebug
```
