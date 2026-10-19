# Trama Network

Sistema web per la gestione documentale di impianti di rete multi-cliente: rack, dispositivi,
interfacce, connessioni, con visualizzazione topologica interattiva e vista rack elevation.

## Stack

Laravel 11 · Livewire 3 · Alpine.js · Tailwind · PostgreSQL 16 · Redis 7 · Cytoscape.js · Sanctum · Browsershot · Docker

## Funzionalità

- **Multi-tenant** (FASE 1): un'app per N clienti, isolamento completo via `tenant_id` + global scope.
  Ruoli admin/tecnico/cliente per-tenant via spatie/laravel-permission con teams.
- **Modello di rete completo** (FASE 2): sites → rooms → racks → equipment → interfaces → connections,
  con vincolo unique parziale che impedisce due connessioni attive sulla stessa interfaccia.
- **CRUD Livewire** (FASE 3) per ogni entità, audit log, validazione client+server.
- **Vista rack elevation** (FASE 4): SVG interattivo con drag &amp; drop su U disponibili,
  drawer di dettaglio, toggle front/rear, locked equipment.
- **Topologia Cytoscape** (FASE 5): grafo navigabile con cose-bilkent/dagre/breadthfirst,
  filtri sede/tipo/VLAN/status, mini-mappa, export PNG, drill-down al rack.
- **Export/Import** (FASE 6): PDF rack via Browsershot, CSV equipment con preview/transaction,
  storico import in audit trail.
- **REST API** (FASE 7): `/api/v1` con Sanctum + `X-Tenant-Id` header, rate limit 60/min,
  token creati dagli admin con permessi per risorsa/azione e per cliente; copre sedi, locali,
  rack, apparati, interfacce, connessioni, topologia, reti Wi‑Fi e VPN.
  Documentazione completa in **[docs/API.md](docs/API.md)**, Postman collection in `docs/postman/trama.json`.
- **Server MCP per assistenti AI**: endpoint `/mcp` per Claude Code, Claude Desktop, Cursor e altri
  client MCP; 35 tool per consultare e modificare l'impianto, limitati dai permessi del token.
  Guida in **[docs/MCP.md](docs/MCP.md)**.
- **Polish** (FASE 8): dashboard con KPI cached, ricerca globale debounced, dark mode persistente,
  notifiche persistenti con bell badge, mini-mappa room.

## Prerequisiti

- Docker Desktop (o Docker Engine + Compose plugin)
- Almeno 4 GB di RAM liberi
- Porte libere: 8081 (web), 5432 (postgres), 6379 (redis), 1025/8025 (mailpit). Se sono occupate, cambiale con le variabili `TRAMA_*_PORT` nel `.env` (vedi `.env.example`).

## Quick start

```bash
# 1. Clone
git clone <repo-url> trama
cd trama

# 2. Configura env
cp .env.example .env
# Allinea l'utente del container a quello che possiede la cartella del progetto
# (altrimenti composer/npm non possono scrivere vendor/ e node_modules/):
sed -i "s/^TRAMA_UID=.*/TRAMA_UID=$(id -u)/; s/^TRAMA_GID=.*/TRAMA_GID=$(id -g)/" .env

# 3. Avvia i container
docker compose up -d --build

# 4. Installa dipendenze PHP
docker compose exec app composer install

# 5. Genera key, migra DB, seed dati demo
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed

# 6. Crea il symlink public/storage -> storage/app/public
docker compose exec app php artisan storage:link

# 7. Build asset frontend
docker compose exec app npm install
docker compose exec app npm run build
# (in dev usa: docker compose exec app npm run dev)
```

> **Errore `vendor does not exist and could not be created`** (o permessi negati su
> `storage/`, `node_modules/`): i comandi nel container girano come l'utente `app`,
> con UID/GID presi da `TRAMA_UID`/`TRAMA_GID` nel `.env` (default 1000). Devono
> coincidere con il proprietario della cartella del progetto sull'host:
>
> ```bash
> ls -ldn .          # UID e GID proprietari della cartella
> id -u; id -g       # UID e GID del tuo utente
> ```
>
> Imposta nel `.env` `TRAMA_UID`/`TRAMA_GID` con quei valori, poi ricostruisci:
> `docker compose build app scheduler && docker compose up -d`. Se la cartella è
> stata clonata da root, prima rendila tua con `sudo chown -R $(id -u):$(id -g) .`.
> Non usare `TRAMA_UID=0`: se lavori da root, lascia 1000 e fai `chown -R 1000:1000 .`.

App raggiungibile su **http://localhost:8081**.
Mailpit (cattura email in dev) su **http://localhost:8025**.

### Credenziali demo

| Ruolo   | Email                | Password   |
|---------|----------------------|------------|
| Admin   | admin@demo.test      | password   |
| Tecnico | tecnico@demo.test    | password   |
| Cliente | cliente@demo.test    | password   |

## Comandi utili

```bash
# Shell nel container app
docker compose exec app bash

# Tinker
docker compose exec app php artisan tinker

# Test
docker compose exec app php artisan test

# Linter / formatter
docker compose exec app ./vendor/bin/pint
docker compose exec app ./vendor/bin/phpstan analyse

# Queue worker (parte già nel container scheduler)
docker compose exec app php artisan queue:work

# Reset completo del DB
docker compose exec app php artisan migrate:fresh --seed

# Icone globali di default (database/icons): installa quelle mancanti
# su un'installazione esistente, senza toccare quelle già personalizzate
docker compose exec app php artisan icons:defaults
# …oppure aggiorna il set di default con le icone globali impostate ora
docker compose exec app php artisan icons:defaults --export
```

## Struttura del progetto

```
.
├── CLAUDE.md                # Istruzioni master per Claude Code
├── docker-compose.yml
├── docker/                  # Dockerfile e conf di servizio
│   ├── app/
│   ├── nginx/
│   └── postgres/
├── docs/                    # Documentazione di progetto
│   ├── API.md               # Documentazione API REST
│   ├── MCP.md               # Server MCP per assistenti AI
│   ├── DATA_MODEL.md
│   ├── UI_UX.md
│   └── PHASE_*.md           # Roadmap a fasi
├── app/                     # Codice Laravel (generato)
├── resources/               # Views, JS, CSS
├── database/                # Migrations, factories, seeders
└── tests/                   # Pest tests
```

## Sviluppo guidato da Claude Code

Questo progetto è stato pensato per essere sviluppato con **Claude Code**.
La fonte di verità è `CLAUDE.md`. Le fasi di sviluppo sono in `docs/PHASE_*.md`.

Apri il progetto con Claude Code e digita semplicemente:

> Leggi CLAUDE.md e poi inizia con la FASE 0.

Claude Code procederà fase per fase, fermandosi a fine di ognuna per chiedere conferma.

## Pubblicare la demo

Trama può girare come **demo pubblica**: i visitatori entrano con un click,
modificano liberamente i dati (tranne gli utenti) e ogni giorno tutto viene
cancellato e ripristinato.

> ⚠️ Con `DEMO_MODE=true` il comando `demo:reset` **cancella tutti i dati**.
> Usala solo su un'installazione dedicata alla demo.

1. Prepara i dati: crea (anche in locale) un cliente dimostrativo curato, con foto,
   planimetrie, Wi-Fi, VPN, documenti e snapshot, ed esportalo da
   *Clienti → Esporta dati*. Metti lo `.zip` in `database/demo/`; ogni archivio
   presente lì diventa un cliente della demo.
2. Nel `.env` del server demo:
   ```dotenv
   APP_ENV=production
   APP_DEBUG=false
   MAIL_MAILER=log
   DEMO_MODE=true
   DEMO_USER_EMAIL=demo@trama.test
   DEMO_USER_PASSWORD=demo
   DEMO_RESET_TIME=03:00
   DEMO_TIMEZONE=Europe/Rome

   # Se sull'host le porte di default sono occupate:
   APP_URL=http://tuo-host:8083
   SANCTUM_STATEFUL_DOMAINS=tuo-host:8083
   TRAMA_HTTP_PORT=8083
   TRAMA_REDIS_PORT=6380
   ```
3. Avvia anche lo scheduler e fai il primo popolamento:
   ```bash
   docker compose up -d scheduler
   docker compose exec app php artisan demo:reset
   ```

In modalità demo:
- la pagina di login mostra il pulsante **Entra nella demo** e le credenziali;
- un banner su ogni pagina avvisa che i dati vengono ripristinati ogni giorno
  (con il conto alla rovescia), e dopo un reset compare un avviso;
- sono disattivati gestione utenti, modifica di profilo/email/password,
  eliminazione dell'account, token API, assegnazione utenti ai clienti e
  recupero password;
- lingua e cliente attivo sono salvati nella sessione del visitatore, perché
  l'account demo è condiviso.

## Licenza

Copyright © 2026 David Bigagli.

Trama Network è distribuito con licenza [Apache License 2.0](LICENSE): puoi usarlo,
modificarlo e ridistribuirlo liberamente, anche per scopi commerciali, a condizione di
mantenere la nota di copyright, il file [NOTICE](NOTICE) con l'attribuzione all'autore
originale e di indicare le modifiche apportate.
