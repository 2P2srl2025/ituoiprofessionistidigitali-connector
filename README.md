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

## Registro delle transazioni

Ogni incarico inviato si registra sul portale quando nasce, e poi a ogni cambio. Lo stesso dato copre i due casi:

- incarico **affidato** a una controparte che il sistema conosce già: `counterparty` presente, stati `invited`, `accepted`, `declined`, `revoked`, `completed`;
- incarico **pubblicato** sul portale: `counterparty` e `kind` a `null`, stati `published` e `withdrawn`, con `expires_at` e `open_to` (`person`, `member` o tutti e due).

Ogni transazione ha le sue attività (`TransactionActivityData`), ognuna con il proprio compenso a ore o a corpo, i minuti previsti (sempre obbligatori), lo stato, i minuti lavorati alla chiusura e la descrizione con i soli nomi del catalogo. `totalCents()` dà il totale come lo calcola il portale.

La testata porta anche `title` (testo semplice, da 1 a 255 caratteri) e `description` (Markdown, al massimo 10000 caratteri; l'HTML dentro il testo non è un errore). Sono obbligatori in un incarico pubblicato, facoltativi negli altri. Regola R6: niente testo libero nelle descrizioni delle attività; `title` e `description` sono testi dello studio, senza dati del cliente; un incarico pubblicato li mostra sul portale.

I DTO sono tipizzati e non conoscono i modelli del sistema: è il sistema che mappa i suoi modelli su di loro, di solito in una sola classe.

| DTO | Cosa porta |
| --- | --- |
| `TransactionData` | La testata dell'invio, con `title` e `description` dello studio, e le sue attività |
| `CounterpartyData` | `CounterpartyData::person($codiceFiscale, $nome, $cognome, $email, $partitaIva, $comune, $provincia)`, gli ultimi quattro facoltativi: il portale crea o aggiorna il professionista per codice fiscale. Oppure `CounterpartyData::member($idAderente)` |
| `TransactionActivityData` | Un'attività: riferimento, compenso, minuti, stato, chiusura, descrizione |
| `CompensationData` | `CompensationData::hourly($centesimiAllOra)` o `CompensationData::fixed($centesimi)` |
| `ActivityDescriptionData` | Nome del processo e dell'attività nel catalogo, o `null`, e scadenza `Y-m-d` |

```php
$diretto = new TransactionData(
    assignment_reference: $incarico->uuid,
    kind: TransactionKind::PersonAssignment,
    principal: $idAderente,
    counterparty: CounterpartyData::person('RSSMRA80A01H501U', 'Mario', 'Rossi', 'mario.rossi@example.com', municipality: 'Bari', province: 'BA'),
    typology: 'commercialisti',
    status: TransactionStatus::Invited,
    sent_at: CarbonImmutable::parse($proposta->sent_at),
    activities: [new TransactionActivityData(
        reference: (string) $riga->id,
        compensation: CompensationData::hourly(4500),
        estimated_minutes: 120,
        status: TransactionActivityStatus::Open,
        description: new ActivityDescriptionData('Contabilità ordinaria', 'Registrazione fatture', '2026-11-30'),
    )],
);
```

Un incarico pubblicato ha `kind: null`, `counterparty: null`, `status: TransactionStatus::Published`, `expires_at` e `open_to: ['person', 'member']`. `TransactionData::from()` legge anche il corpo del portale, con la descrizione nella forma dello schema (`{"process": {"name": "…"}}`).

Il modello che corrisponde all'invio lo dichiara con un'interfaccia, e il pacchetto fa il resto:

```php
use ITuoiProfessionistiDigitali\Connector\Concerns\RecordsOnPlatform;
use ITuoiProfessionistiDigitali\Connector\Contracts\RecordsPlatformTransaction;
use ITuoiProfessionistiDigitali\Connector\Data\TransactionData;

final class ExternalAssignmentProposal extends Model implements RecordsPlatformTransaction
{
    use RecordsOnPlatform;

    public function platformTransactionReference(): ?string
    {
        return $this->uuid; // null per una bozza mai inviata
    }

    public function toPlatformTransaction(): TransactionData
    {
        return resolve(ProposalMapper::class)->toTransaction($this); // la mappatura del sistema
    }
}
```

I modelli che cambiano la transazione senza esserlo, come le sue righe o l'incarico che la contiene, la registrano di nuovo a ogni salvataggio ed eliminazione:

```php
use ITuoiProfessionistiDigitali\Connector\Concerns\RecordsAffectedOnPlatform;
use ITuoiProfessionistiDigitali\Connector\Contracts\AffectsPlatformTransactions;

final class ExternalAssignmentActivity extends Model implements AffectsPlatformTransactions
{
    use RecordsAffectedOnPlatform;

    public function affectedPlatformTransactions(): iterable
    {
        return ExternalAssignmentProposal::query()->whereKey($this->proposal_id)->get();
    }
}
```

- Un trait usato senza la sua interfaccia lancia una `LogicException`.
- Ogni salvataggio scrive la versione attuale in una outbox (`php artisan migrate` crea la tabella `platform_transaction_outbox`), nella stessa transazione del database, con una revisione nuova solo se qualcosa è cambiato. Una bozza, con il riferimento a `null`, non ci arriva.
- Un job in coda la manda con `PUT /transactions/{reference}`. Riprova sugli errori di rete e su un sistema non ancora attivo; si ferma, segnando l'errore, su una violazione del contratto o un conflitto.
- `$model->isRecordedOnPlatform()` dice se il portale ha confermato la versione attuale. La registrazione segue il lavoro, non lo blocca.
- Ogni cinque minuti il comando schedulato `platform:send-outbox` rimanda le versioni rimaste indietro, per esempio mentre il sistema era in attesa di verifica.
- Una transazione non si cancella: un invio ritirato si salva come `revoked`, con tutte le attività chiuse.
- Lo storico si carica con `Platform::recordTransactions()`, fino a 500 per chiamata; `Platform::transactions()` legge il registro del sistema.
- Nei test del sistema `PlatformOutbox::assertRecorded($model)` verifica che la versione attuale sia nella outbox, `PlatformOutbox::assertNotRecorded($model)` che una bozza non ci sia.

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
