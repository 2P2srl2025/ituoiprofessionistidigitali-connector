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

- Ogni incarico inviato va registrato sul portale **quando nasce**, e poi a ogni cambio: nello stato `invited` se è affidato a una controparte che il sistema conosce (collaboratore persona, altra struttura dello stesso sistema), nello stato `published`, senza controparte e con `expires_at`, se è pubblicato sul portale. Non è facoltativo: è la regola R9 del contratto.
- Una transazione è un invio con prezzi fermi: dopo la prima registrazione cambiano solo lo stato, le sue date e, per ogni attività, stato, minuti lavorati e chiusura (R13). Per cambiare un prezzo revoca l'attività e mandala in un invio nuovo, con un riferimento nuovo.
- Lo stato segue le attività (R14): `invited`, `declined`, `published` e `withdrawn` le hanno tutte `open`; `accepted` almeno una `open`; `completed` nessuna `open` e almeno una `completed`; `revoked` nessuna `open` e nessuna `completed`. Un invio ritirato prima della risposta è `revoked`, con tutte le attività `revoked`.
- I minuti previsti sono obbligatori su ogni attività, anche a ore: il totale c'è sempre. I minuti lavorati si mandano solo su un'attività chiusa di un incarico a persona.
- Costruisci la transazione con i DTO tipizzati (`TransactionData`, `CounterpartyData`, `TransactionActivityData`, `CompensationData`, `ActivityDescriptionData`), mai con un array a mano. La mappatura dai modelli del sistema sta in una sola classe del sistema: il pacchetto non conosce i tuoi modelli.
- Una controparte persona porta l'anagrafica del professionista: codice fiscale, nome e cognome obbligatori; email, partita IVA, comune e provincia se il sistema li ha, altrimenti `null`. Il portale crea o aggiorna il professionista per codice fiscale. Un aderente porta solo il suo `id`.
- Un incarico pubblicato ha `kind` e `counterparty` a `null`, `expires_at` e `open_to`; li conserva anche dopo che il portale gli assegna una controparte.
- Il modello dell'invio implementa `RecordsPlatformTransaction` e usa il trait `RecordsOnPlatform`; `platformTransactionReference()` è `null` per una bozza, che non si registra. I modelli che cambiano l'invio senza esserlo (le righe, l'incarico che lo contiene) implementano `AffectsPlatformTransactions` e usano `RecordsAffectedOnPlatform`. Il pacchetto scrive ogni salvataggio in una outbox, nella stessa transazione del database, e lo manda in coda con la revisione giusta. Non chiamare `Platform::recordTransaction()` a mano per questi modelli.
- La registrazione segue il lavoro, non lo blocca: l'affidamento è operativo da subito e l'outbox lo comunica dopo. `$model->isRecordedOnPlatform()` serve a mostrare se il portale ha confermato l'ultima versione, non a fermare il lavoro.
- Una transazione non si cancella mai sul portale (R12): prima di eliminare un affidamento portalo a `revoked`, con `closed_at`, e salvalo; poi eliminalo. L'outbox manda la versione salvata anche dopo il delete.
- Mai aggiornamenti di massa (`Model::query()->update()`, `DB::table()->update()`) sui campi che finiscono in `toPlatformTransaction()`: saltano il modello e l'outbox. Per quei campi aggiorna riga per riga. Sui campi che non fanno parte della transazione, come l'ultimo accesso del collaboratore, l'aggiornamento di massa va bene.
- Il comando `platform:send-outbox` gira da solo ogni cinque minuti e rimanda le versioni rimaste indietro: serve che lo scheduler di Laravel sia attivo.
- La descrizione `assignment` di ogni attività porta solo i nomi del modello di processo e dell'attività del catalogo dello studio, oppure `null`: **mai** il nome, il codice fiscale o la partita IVA del cliente, il nome di un'agenda o di un'area di progetto, né un testo libero.
- R6: niente testo libero nelle descrizioni delle attività; `title` e `description` della testata sono testi dello studio, senza dati del cliente; un incarico pubblicato li mostra sul portale. In un incarico pubblicato sono obbligatori: `title` testo semplice fino a 255 caratteri, `description` Markdown fino a 10000.
- Lo storico già esistente si carica una volta con `Platform::recordTransactions()`, fino a 500 per chiamata.
- Una riga dell'outbox in stato `failed` è un errore nel codice (contratto violato o conflitto di revisione): leggi `last_error` e correggi, non ritentare alla cieca.
- Un test di architettura nel progetto rende l'obbligo verificabile:

```php
arch('assignments reach the register of the platform')
    ->expect(App\Models\P2pExternalAssignmentProposal::class)
    ->toImplement(ITuoiProfessionistiDigitali\Connector\Contracts\RecordsPlatformTransaction::class)
    ->toUseTrait(ITuoiProfessionistiDigitali\Connector\Concerns\RecordsOnPlatform::class);
```

- Nei test di ogni action che tocca un invio, le sue righe o l'incarico, verifica la comunicazione con `ITuoiProfessionistiDigitali\Connector\Testing\PlatformOutbox::assertRecorded($proposta)`, e con `assertNotRecorded()` che una bozza non parta.

## Errori

- `422`: il contratto è violato. Non ritentare: leggi `->errors`, le cui chiavi sono i campi in notazione puntata.
- `401`: il client rinnova il token da solo. Non mettere in cache il token a mano.
- `403`: leggi `->reason` (`pending`, `suspended`, `revoked`). È una questione di collegamento, da sistemare con l'operatore del portale, non nel codice.
- `5xx` ed errori di rete: il client riprova da solo qualche volta; dopo, la stessa busta si può rimandare.

## Segreti e configurazione

- Client secret e segreto di firma stanno solo nel `.env` del server: mai nel frontend, in una vista o in un log.
- Leggi la configurazione con `config('platform.*')` o `ConnectorConfig`, mai con `env()` nel codice applicativo.
