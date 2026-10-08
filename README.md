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
        'email' => 'segreteria@studiorossi.example',
    ]),
]);

$members[0]->id;   // l'id del portale: il sender dei tuoi eventi
```

L'elenco è **completo**: gli aderenti che mancano diventano inattivi.

`email` è obbligatoria: è l'email coworking della struttura, l'indirizzo per le notifiche della sua area sul portale (M14). È unica fra gli aderenti di tutto il portale, di qualunque sistema (M15): una duplicata è un 422 su `members.N.email`. Il pacchetto controlla solo che due aderenti della stessa richiesta non abbiano la stessa email, perché non conosce quelli degli altri sistemi. Non fa da login. La risposta di `syncMembers()` la riporta per ogni aderente. `searchMembers()` invece non la mostra mai, come il codice fiscale (M11).

```php
$page = Platform::searchMembers(typology: 'commercialisti', search: 'bianchi');

foreach ($page->members as $member) { /* id, name, vat_number, municipality, province, typologies */ }

$next = Platform::searchMembers(typology: 'commercialisti', cursor: $page->nextCursor);
```

### Accesso all'area dello studio

Il sistema porta i suoi utenti nell'area riservata di un suo aderente sul portale con un link firmato (regole U1–U4). L'accesso è per studio e non per persona: la richiesta non ha corpo, e chi ha cliccato lo registra il sistema.

```php
use ITuoiProfessionistiDigitali\Connector\Exceptions\MemberNotAccessibleException;

try
{
    $link = Platform::memberAccessLink($aderente->platform_id);
}
catch (MemberNotAccessibleException)
{
    abort(404);
}

return redirect()->away($link->url);
```

- Il link vale **una volta sola** e scade dopo 5 minuti (`$link->expires_at`). Ogni chiamata ne crea uno nuovo.
- Il link è un **segreto** (U4): usalo subito con un redirect del browser, senza conservarlo, metterlo in cache o scriverlo in un log. Se un log ha bisogno di un riferimento, usa solo `expires_at`. Il pacchetto non lo mette in cache e non lo scrive nei log. Se il sistema registra le risposte del client HTTP, per esempio con Telescope o con un listener di `ResponseReceived`, deve escludere questa rotta.
- Un aderente di un altro sistema, inesistente o inattivo dà `MemberNotAccessibleException`: il portale non dice quale dei tre. Un aderente con `listed: false` ha il suo link come gli altri. Un sistema non attivo riceve 403, oltre 30 richieste al minuto 429, entrambi come `PlatformRequestException`.

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

Ogni incarico inviato si registra sul portale quando nasce, e poi a ogni cambio (regola R9). Ogni PUT è la fotografia completa della transazione, quindi il portale accetta la prima registrazione in qualunque stato, purché coerente con le sue date e con le sue attività. Lo stesso dato copre i due casi:

- incarico **affidato** a una controparte che il sistema conosce già: `counterparty` presente, `audience` uguale al suo tipo (`person` o `member`), `expires_at` a `null`, stati `invited`, `accepted`, `declined`, `revoked`, `completed`;
- incarico **pubblicato** sul portale: `counterparty` a `null`, stati `published` e `withdrawn`, con `expires_at` e `audience` (`person`, `member` o `any`).

La struttura è una sola: tutte le chiavi ci sono sempre, anche quando valgono `null`, nella testata (`counterparty`, `expires_at`, `responded_at`, `closed_at`), nella controparte, nelle attività, nel compenso e nella descrizione. Una chiave mancante è un 422; i DTO del pacchetto le emettono tutte. `audience` non cambia dopo la prima registrazione.

Ogni transazione ha le sue attività (`TransactionActivityData`), ognuna con il proprio compenso a ore o a corpo, i minuti previsti (sempre obbligatori), lo stato, i minuti lavorati alla chiusura e la descrizione con i soli nomi del catalogo. `totalCents()` dà il totale come lo calcola il portale.

La testata porta anche `title` (testo semplice, da 1 a 255 caratteri) e `description` (Markdown, al massimo 10000 caratteri; l'HTML dentro il testo non è un errore). Sono obbligatori e non vuoti in ogni stato. Regola R6: niente testo libero nelle descrizioni delle attività; `title` e `description` sono testi dello studio, senza dati del cliente; un incarico pubblicato li mostra sul portale.

I DTO sono tipizzati e non conoscono i modelli del sistema: è il sistema che mappa i suoi modelli su di loro, di solito in una sola classe.

| DTO | Cosa porta |
| --- | --- |
| `TransactionData` | La testata dell'invio, con `title` e `description` dello studio, e le sue attività |
| `CounterpartyData` | `CounterpartyData::person($codiceFiscale, $nome, $cognome, $email, $partitaIva, $comune, $provincia)`, con l'email obbligatoria e gli ultimi tre facoltativi: l'anagrafica al momento dell'invio, identica a ogni revisione (R13). Oppure `CounterpartyData::member($idAderente)`. Sul filo ha sempre le nove chiavi `type`, `member_id`, `tax_code`, `first_name`, `last_name`, `email`, `vat_number`, `municipality`, `province` |
| `TransactionActivityData` | Un'attività: riferimento, compenso, minuti, stato, chiusura, descrizione |
| `CompensationData` | `CompensationData::hourly($centesimiAllOra)` o `CompensationData::fixed($centesimi)` |
| `ActivityDescriptionData` | Nome del processo e dell'attività nel catalogo, o `null`, e scadenza `Y-m-d` |

```php
$diretto = new TransactionData(
    assignment_reference: $incarico->uuid,
    audience: Audience::Person,
    principal: $idAderente,
    counterparty: CounterpartyData::person('RSSMRA80A01H501U', 'Mario', 'Rossi', 'mario.rossi@example.com', municipality: 'Bari', province: 'BA'),
    typology: 'commercialisti',
    title: 'Contabilità ordinaria 2026',
    description: 'Registrazione delle fatture del 2026.',
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

Un incarico pubblicato ha `counterparty: null`, `status: TransactionStatus::Published`, `expires_at` e `audience: Audience::Any` (o `Person`, `Member`). I minuti lavorati dipendono dal tipo della controparte: con una persona sono obbligatori in un'attività `completed`, con un aderente o senza controparte sono sempre `null` (R5).

La parte ferma all'invio (audience, controparte, tipologia, testi, prezzi) non cambia fra una revisione e l'altra (R13). Il modello che registra la transazione la costruisce da quello che ha fotografato all'invio, non dalle anagrafiche di oggi; un cambio dell'anagrafica si dichiara a parte (vedi «Anagrafica dei professionisti»). `TransactionData::from()` legge anche il corpo del portale, con la descrizione nella forma dello schema (`{"process": {"name": "…"}}`).

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
- Ogni salvataggio scrive la versione attuale in una outbox (`php artisan migrate` crea la tabella `platform_transaction_outbox`), nella stessa transazione del database, con una revisione nuova solo se qualcosa è cambiato. Una bozza, con il riferimento a `null`, non ci arriva. L'outbox tiene solo l'ultima versione: se l'invio di `invited` non è riuscito e intanto l'incarico è stato accettato, al portale arriva come prima registrazione la versione `accepted`, ed è accettata.
- Un job in coda la manda con `PUT /transactions/{reference}`. Riprova sugli errori di rete e su un sistema non ancora attivo; si ferma, segnando l'errore, su una violazione del contratto o un conflitto. Vale anche quando la violazione la trova il pacchetto prima della richiesta, per esempio su una versione salvata prima che cambiasse una regola: la riga finisce `failed` e `platform:send-outbox` non la riprende.
- `$model->isRecordedOnPlatform()` dice se il portale ha confermato la versione attuale. La registrazione segue il lavoro, non lo blocca.
- Ogni cinque minuti il comando schedulato `platform:send-outbox` rimanda le versioni rimaste indietro, per esempio mentre il sistema era in attesa di verifica.
- Una transazione non si cancella: un invio ritirato si salva come `revoked`, con tutte le attività chiuse.
- Lo storico si carica con `Platform::recordTransactions()`, fino a 500 per chiamata, con le stesse regole della PUT (R10). Ogni transazione che il portale ha (`created`, `updated`, `unchanged`) entra nella outbox come già confermata, così la outbox conosce ogni persona incaricata. `Platform::transactions()` legge il registro del sistema, con il filtro `audience`.
- Nei test del sistema `PlatformOutbox::assertRecorded($model)` verifica che la versione attuale sia nella outbox, `PlatformOutbox::assertNotRecorded($model)` che una bozza non ci sia.

## Anagrafica dei professionisti

Il professionista è uno per codice fiscale in tutto il portale. Nasce dalla prima registrazione di un incarico a persona, con l'anagrafica dell'invio. Quando poi l'anagrafica cambia nel sistema, il sistema la dichiara con `PUT /professionals/{tax_code}` (regola R18), datata dal momento del cambio: vince la dichiarazione più recente, non l'ultima arrivata (R19). Le transazioni non cambiano: ognuna conserva l'anagrafica del suo invio (R13).

```php
use ITuoiProfessionistiDigitali\Connector\Data\ProfessionalRecordData;
use ITuoiProfessionistiDigitali\Connector\Outbox\ProfessionalOutbox;

resolve(ProfessionalOutbox::class)->declare($collaboratore->codice_fiscale, new ProfessionalRecordData(
    first_name: 'Mario',
    last_name: 'Rossi',
    declared_at: CarbonImmutable::parse($collaboratore->updated_at),
    email: 'mario.rossi@example.com',
    vat_number: '01234567897',
    municipality: 'Lecce',
    province: 'LE',
));
```

- L'anagrafica è sempre completa: un campo facoltativo a `null` toglie il dato sul portale. Nome, cognome ed email sono obbligatori, come nella controparte persona; facoltativi restano partita IVA, comune e provincia. `declared_at` non può essere oltre 5 minuti nel futuro.
- Una persona mai incaricata non arriva al portale: senza una riga della outbox delle transazioni con quel codice fiscale, in qualunque stato, `declare()` non tiene né manda nulla e restituisce `null`. Il sistema può chiamarla a ogni cambio dell'anagrafica, senza condizioni sue.
- L'outbox delle dichiarazioni (tabella `platform_professional_outbox`) tiene l'ultima per codice fiscale: ignora una dichiarazione più vecchia e una con la stessa anagrafica. La manda in coda dopo il commit.
- Il portale risponde 404 finché non ha una transazione a persona del sistema con quel codice fiscale. Se l'outbox delle transazioni ne ha ancora di mai confermate, la dichiarazione aspetta e riparte da sola quando una è confermata; altrimenti si chiude come `discarded`, con un log, e non è un errore (per esempio un codice fiscale corretto dopo l'invio).
- Un 204 chiude la dichiarazione anche quando il portale tiene la sua anagrafica, più recente o bloccata (R20): il sistema non lo sa e non deve saperlo.
- `Platform::declareProfessional($codiceFiscale, $anagrafica)` fa la sola chiamata, senza outbox: un 404 diventa `ProfessionalNotAssignedException`.
- Una dichiarazione che viola il contratto, anche se la trova il pacchetto prima della richiesta, finisce `failed` con l'errore e non si ripete. Vale anche per una dichiarazione salvata prima che cambiasse una regola.
- `platform:send-outbox` rimanda anche le dichiarazioni in attesa. Nei test `PlatformOutbox::assertDeclared($codiceFiscale, $anagrafica)` verifica che sia l'ultima dichiarazione nella outbox.

## Errori

Ogni rifiuto del portale è una `PlatformRequestException`, con `->status`, `->errors` (chiavi puntate come `payload.challenge`), `->reason` per i 403 (`SystemStatus`) e `->existingEvent` per i 409. Lo è anche quando il pacchetto rifiuta prima di mandare per lo schema del payload o per un limite, come le 500 transazioni. Un DTO che viola il contratto è invece una `ValidationException` di Laravel, con le stesse chiavi puntate in `->errors()`, prima di qualunque richiesta, come due email uguali nella stessa `syncMembers()`. Due 404 hanno un'eccezione propria: `ProfessionalNotAssignedException` per l'anagrafica e `MemberNotAccessibleException` per il link d'accesso. Senza credenziali il client lancia `PlatformNotConfiguredException`.

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
