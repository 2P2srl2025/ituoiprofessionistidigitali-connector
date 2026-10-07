# Istruzioni per agenti AI: connettore di I tuoi professionisti digitali

Regole per chi scrive codice che usa `ituoiprofessionistidigitali/connector` in un sistema collegato al portale. Il contratto completo è `docs/contratto.md` nel repo del portale: il portale ne è l'autorità e il sistema si adegua, mai il contrario.

## Non aggirare il pacchetto

- Parla con il portale solo con `Platform::…` (o `PlatformClient`). Mai una `Http::post()` a mano verso `/api/v1`: token, nuovi tentativi, validazione ed errori stanno nel client.
- Costruisci gli eventi con `EnvelopeData::make()` o, per rispondere, con `$evento->reply()`. Non comporre la busta come array.
- Non inventare tipi di evento, versioni o campi del payload: esistono solo quelli di `Platform::eventTypes()`. Un tipo nuovo nasce nel portale, con una pull request, prima che un sistema lo mandi.

## Tipologie

- Non dare mai per scontato `commercialisti`. Le tipologie sono un catalogo (`Platform::typologies()`): un codice è un dato, non una costante sparsa nel codice.
- Un aderente può avere più tipologie. Ogni evento dichiara la tipologia dello scambio, che dev'essere comune a mittente e destinatario.

## Aderenti

- `Platform::syncMembers()` vuole l'elenco **completo** degli aderenti del sistema: chi manca diventa inattivo. Non mandare mai un elenco parziale, né «solo quello cambiato».
- `external_ref` è il tuo riferimento stabile e opaco: non cambiarlo e non riusarlo per un altro soggetto.
- Salva l'`id` che il portale restituisce per ogni aderente: è il `sender` dei tuoi eventi. Gli id degli altri sistemi arrivano da `Platform::searchMembers()` o dagli eventi ricevuti.
- `listed: false` tiene l'aderente fuori dalla ricerca, ma non dagli scambi.
- Partita IVA e codice fiscale vanno in maiuscolo, senza spazi: il pacchetto e il portale verificano anche il carattere di controllo.

## Mandare eventi

- Il `sender` è sempre un aderente **tuo**: il portale ricava il sistema dalle credenziali e rifiuta un mittente altrui.
- Salva la busta prima di mandarla. Se l'invio va in timeout, rimanda **la stessa busta**, con lo stesso `event_id`: il portale risponde 200 senza duplicare. Una busta nuova con un `event_id` nuovo è un evento nuovo.
- Un 409 vuol dire che hai riusato un `event_id` con un contenuto diverso: è un errore nel codice, non qualcosa da ritentare.

## Ricevere eventi

- La rotta del webhook la registra il pacchetto (`PLATFORM_WEBHOOK_PATH`, nome `platform.webhook`) e verifica la firma da sola. Non scriverne un'altra e non togliere il middleware della firma.
- La verifica del webhook (il `platform.ping` senza mittente) e la risposta ai `platform.ping` degli aderenti le gestisce il pacchetto. Non rispondere a mano.
- Ascolta `PlatformEventReceived`. Il listener:
  - va **in coda** (`ShouldQueue`): il portale aspetta al massimo dieci secondi;
  - è **idempotente su `event_id`**: lo stesso evento può arrivare due volte, quindi salva gli id elaborati con un indice unico;
  - non conta sull'ordine: usa `occurred_at` e `correlation_id`;
  - ignora i tipi che non gestisce, senza eccezioni.
- Un'eccezione in un listener sincrono fa rispondere 500 e il portale riprova: va bene solo se il lavoro non è stato salvato.

## Collegamento

- Senza `PLATFORM_URL`, `PLATFORM_CLIENT_ID` e `PLATFORM_CLIENT_SECRET` il sistema non è collegato: il client lancia `PlatformNotConfiguredException`. Controlla prima con `resolve(ConnectorConfig::class)->isConnected()` e spegni la funzione, invece di romperla.
- Dopo il deploy o quando cambia l'URL del webhook chiama `Platform::present(route('platform.webhook'))`. Finché la verifica non riesce, il sistema legge i cataloghi ma non pubblica aderenti né eventi (403 con `reason: pending`).

## Registro delle transazioni

- Ogni affidamento deciso dentro il sistema (incarico diretto a un collaboratore persona, affidamento fra due strutture dello stesso sistema) va registrato sul portale **quando nasce**, nello stato `invited`, e poi a ogni cambio. Non è facoltativo: è la regola R9 del contratto.
- Il modello dell'affidamento implementa `RecordsPlatformTransaction` e usa il trait `RecordsOnPlatform`. Il pacchetto scrive ogni salvataggio in una outbox, nella stessa transazione del database, e lo manda in coda con la revisione giusta. Non chiamare `Platform::recordTransaction()` a mano per questi modelli.
- Rendi operativo l'affidamento (link al collaboratore, attività nell'altra struttura) solo quando `$model->isRecordedOnPlatform()` è vero.
- Mai `Model::query()->update()` o `DB::table()->update()` sui modelli che implementano l'interfaccia: gli aggiornamenti di massa saltano il modello e l'outbox. Aggiorna riga per riga.
- Il payload `assignment` porta solo i nomi del processo e delle attività del catalogo dello studio: **mai** il nome, il codice fiscale o la partita IVA del cliente, e nessun testo libero.
- Lo storico già esistente si carica una volta con `Platform::recordTransactions()`, fino a 500 per chiamata.
- Una riga dell'outbox in stato `failed` è un errore nel codice (contratto violato o conflitto di revisione): leggi `last_error` e correggi, non ritentare alla cieca.
- Un test di architettura nel progetto rende l'obbligo verificabile:

```php
arch('assignments reach the register of the platform')
    ->expect(App\Models\P2pExternalAssignment::class)
    ->toImplement(ITuoiProfessionistiDigitali\Connector\Contracts\RecordsPlatformTransaction::class)
    ->toUseTrait(ITuoiProfessionistiDigitali\Connector\Concerns\RecordsOnPlatform::class);
```

## Errori

- `422`: il contratto è violato. Non ritentare: leggi `->errors`, le cui chiavi sono i campi in notazione puntata.
- `401`: il client rinnova il token da solo. Non mettere in cache il token a mano.
- `403`: leggi `->reason` (`pending`, `suspended`, `revoked`). È una questione di collegamento, da sistemare con l'operatore del portale, non nel codice.
- `5xx` ed errori di rete: il client riprova da solo qualche volta; dopo, la stessa busta si può rimandare.

## Segreti e configurazione

- Client secret e segreto di firma stanno solo nel `.env` del server: mai nel frontend, in una vista o in un log.
- Leggi la configurazione con `config('platform.*')` o `ConnectorConfig`, mai con `env()` nel codice applicativo.
