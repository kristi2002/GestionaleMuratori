# Gestionale Muratori — Field Service & Construction Management

Sistema di gestione cantieri, interventi tecnici sul campo, magazzino in tempo reale
e report per il cliente. Tre ruoli: **Amministratore**, **Operaio**, **Cliente**.

> Tutto il testo rivolto all'utente è in italiano; codice, commenti e nomi delle colonne
> del database sono in inglese (vedi `lang/it.php`).

## Stack

- PHP 8.0+ (sviluppato e testato su XAMPP / PHP 8.0; forward-compatibile con 8.2+)
- MySQL 8 (InnoDB, utf8mb4) — accesso solo via **PDO** con prepared statement, nessun ORM
- Frontend: pagine server-rendered + jQuery/AJAX (fasi successive)
- PDF: mPDF · Excel: PhpSpreadsheet (installati via Composer, usati dalla Fase 7)

## Requisiti

- XAMPP (o PHP 8.0+ con estensioni `pdo_mysql`, `gd`, `mbstring`, `zip`, `fileinfo`) e MySQL 8 in esecuzione.
- [Composer](https://getcomposer.org/) per `mpdf/mpdf` e `phpoffice/phpspreadsheet` (report PDF/Excel, Fase 7). Senza `vendor/` installato l'app funziona comunque: solo il download dei report fallisce.

## Struttura cartelle

```
/public      entry point (index.php) + asset
/src         codice applicativo (Support/, in seguito Controllers/, Models/, Services/)
/views       template (dalla Fase 2)
/lang        stringhe UI in italiano (it.php)
/config      configurazione (config.php legge .env)
/database    migrazioni (migrations/*.sql) + runner (migrate.php) + seed.php
/storage     upload (foto, firme) — escluso da git
```

## Installazione (XAMPP, Windows)

Il PHP di XAMPP non è sul PATH: usa il percorso completo
`C:\xampp\php\php.exe` (negli esempi sotto: `php`).

1. **Avvia** Apache e MySQL dal pannello di controllo di XAMPP.

2. **Configura l'ambiente** — copia il file di esempio:
   ```powershell
   Copy-Item .env.example .env
   ```
   I valori predefiniti corrispondono a un'installazione XAMPP standard
   (`root` senza password, database `gestionale_muratori`). Modificali se necessario.

3. **Crea lo schema** (crea anche il database se non esiste):
   ```powershell
   C:\xampp\php\php.exe database\migrate.php
   ```

4. **Carica i dati di esempio** (seed):
   ```powershell
   C:\xampp\php\php.exe database\seed.php
   ```

5. **Installa le dipendenze Composer** (mPDF + PhpSpreadsheet, usate dai report — Fase 7):
   ```powershell
   composer install --no-dev
   ```

6. **Apri la pagina di stato** nel browser:
   - Se il progetto è sotto `htdocs`: `http://localhost/GestionaleMuratori/public/`
   - Oppure avvia il server di sviluppo PHP (lo script `public/index.php` fa da
     router, necessario per le URL pulite tipo `/login`, `/admin`):
     ```powershell
     C:\xampp\php\php.exe -S localhost:8000 -t public public/index.php
     ```
     e visita `http://localhost:8000/`.

   Verrai reindirizzato alla pagina di **accesso**. Effettua il login con una
   delle credenziali sotto: ogni ruolo viene portato alla propria area
   (amministratore, operaio, cliente).

## Dati di esempio (seed)

`database/seed.php` è **idempotente** (svuota e ricarica). Crea:

- 1 amministratore, 2 operai, 2 referenti cliente
- 2 clienti (aziende), 5 progetti, 10 articoli di magazzino, 6 interventi di esempio

**Credenziali** (password per tutti: `password`):

| Email | Ruolo |
|-------|-------|
| `admin@gestionale.local`   | Amministratore |
| `worker1@gestionale.local` | Operaio |
| `worker2@gestionale.local` | Operaio |
| `client1@gestionale.local` | Cliente (Edilizia Rossi) |
| `client2@gestionale.local` | Cliente (Costruzioni Bianchi) |

## Integrità del magazzino

`warehouse_items.qty_in_stock` è un **totale di cache**: la fonte di verità è il
registro `stock_movements`. Il seed inserisce un movimento `in` pari alla giacenza
iniziale di ogni articolo, quindi cache e registro coincidono fin dall'inizio
(criterio §9). La logica di prenotazione/scarico arriva nelle Fasi 4–5.

## Stato avanzamento (fasi)

- [x] **Fase 1 — Fondazione**: skeleton, config, PDO, migrazioni, seed, README.
- [x] **Fase 2 — Auth + RBAC**: login/logout AJAX, sessione, guard per ruolo, aree admin/operaio/cliente.
- [x] **Fase 3 — CRUD Admin**: clienti, progetti e magazzino (tabelle + modali), carichi/rettifiche di magazzino con registro movimenti e riconciliazione della giacenza.
- [x] **Fase 4 — Interventi + prenotazione**: creazione interventi con materiali pianificati (prenotazione automatica e blocco se la giacenza non basta), assegnazione operaio, macchina a stati per i passaggi di stato (`pending→in_progress→on_hold→…`) con rilascio della giacenza prenotata in caso di annullamento.
- [x] **Fase 5 — App mobile operaio**: "Le mie attività di oggi", avvio/sospensione/ripresa intervento, completamento con conferma `qty_used` per materiale (scarico + rilascio eccedenza sul registro), upload foto prima/durante/dopo con miniatura generata via GD, firma cliente da canvas. Il completamento è bloccato finché manca una foto "dopo" o la quantità utilizzata di un materiale (§4.4).
- [x] **Fase 6 — Vista cliente**: elenco progetti del cliente in sola lettura, dettaglio progetto con interventi e galleria foto prima/dopo (le foto "durante" restano interne all'operaio). Nessun accesso a magazzino, costi o dati di altri clienti.
- [x] **Fase 7 — Report PDF + Excel**: report di progetto scaricabile da admin (tutti i progetti) e cliente (solo i propri). PDF (mPDF) in formato A4 stampabile con intestazione cliente/progetto, tabella interventi, materiali utilizzati (dal registro movimenti `out`, non dai piani), griglia foto prima/dopo per intervento e firma cliente. Excel (PhpSpreadsheet) come export dati equivalente senza immagini.
- [x] **Fase 8 — Rifiniture**: pagina 500 generica con gestore d'eccezioni globale (mai più uno stack trace grezzo all'utente; in debug l'errore resta visibile), riconciliazione giacenza azionabile dall'admin (ricalcola `qty_in_stock` dal registro e segnala eventuali scostamenti), filtri rapidi "Oggi"/"Questa settimana" per il dispacciamento interventi, upload foto offline-friendly (compressione lato client via canvas prima dell'invio, coda di retry in `localStorage` alla riconnessione). Passata di validazione: scoperto e corretto un possibile overflow silenzioso delle quantità di magazzino (MySQL qui gira senza `STRICT_TRANS_TABLES`, quindi un valore DECIMAL fuori range veniva troncato invece di generare un errore) — ora ogni quantità è validata anche rispetto al limite della colonna, sia in input sia sul totale risultante.
  - **Non incluso, segnalato come da §8**: coda offline completa via PWA/service worker (esplicitamente uno stretch goal, non richiesto); check-in GPS/orario e ore di manodopera (fuori scope v1 per design dello schema).
