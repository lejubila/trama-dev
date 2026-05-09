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
  Swagger UI su `/api/documentation`, Postman collection in `docs/postman/trama.json`.
- **Polish** (FASE 8): dashboard con KPI cached, ricerca globale debounced, dark mode persistente,
  notifiche persistenti con bell badge, mini-mappa room.

## Prerequisiti

- Docker Desktop (o Docker Engine + Compose plugin)
- Almeno 4 GB di RAM liberi
- Porte libere: 8081 (web), 5432 (postgres), 6379 (redis), 1025/8025 (mailpit)

## Quick start

```bash
# 1. Clone
git clone <repo-url> trama
cd trama

# 2. Configura env
cp .env.example .env

# 3. Avvia i container
docker compose up -d --build

# 4. Installa dipendenze PHP
docker compose exec app composer install

# 5. Genera key, migra DB, seed dati demo
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed

# 6. Build asset frontend
docker compose exec app npm install
docker compose exec app npm run build
# (in dev usa: docker compose exec app npm run dev)
```

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

## Licenza

Privato / da definire.
