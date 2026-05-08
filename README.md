# Trama Network

Sistema web per la gestione documentale di impianti di rete multi-cliente: rack, dispositivi,
interfacce, connessioni, con visualizzazione topologica interattiva e vista rack elevation.

## Stack

Laravel 11 · Livewire 3 · Alpine.js · Tailwind · PostgreSQL 16 · Redis 7 · Cytoscape.js · Docker

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
