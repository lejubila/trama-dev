# Trama Network — Network Infrastructure Management System

## Cosa stiamo costruendo

Trama Network è un'applicazione web per la gestione e la documentazione di impianti di rete di clienti.
Permette di catalogare e visualizzare graficamente rack, patch panel, dispositivi di rete (switch,
router, firewall, access point, controller, ecc.), le loro interfacce e le connessioni fisiche
e logiche tra di essi.

**Casi d'uso principali:**
- Un system integrator gestisce gli impianti di rete di N clienti
- Per ogni cliente può documentare una o più sedi
- Per ogni sede disegna i rack, ci posiziona dentro patch panel e dispositivi
- Per ogni dispositivo descrive le interfacce (porte) e il loro funzionamento
- Disegna le connessioni tra interfacce di dispositivi diversi
- Visualizza l'impianto in due modalità: **vista rack** (struttura fisica) e **vista topologica** (logica/L2-L3)
- Naviga interattivamente cliccando su un dispositivo per vederne il dettaglio

## Stack tecnologico (FISSATO — non cambiare)

- **Backend:** Laravel 11 (PHP 8.3)
- **Frontend:** Livewire 3 + Alpine.js + Tailwind CSS
- **Database:** PostgreSQL 16
- **Cache/Queue:** Redis 7
- **Web server:** Nginx
- **Container:** Docker + Docker Compose
- **Visualizzazione grafica:** **Cytoscape.js** (per la topologia logica) integrato via Alpine component
- **Vista rack:** SVG renderizzato lato server con interazioni Alpine.js
- **PDF export:** Spatie Browsershot (Puppeteer headless)
- **Multi-tenancy:** stancl/tenancy v3 (single-database, foreign-key based) — vedi sezione Multi-tenancy
- **Autorizzazione:** spatie/laravel-permission
- **Audit:** owen-it/laravel-auditing
- **Test:** Pest

### Perché Cytoscape.js
È la libreria leader per topologie di rete: layout automatici (cose, breadthfirst, dagre),
performance su grafi grandi, ottime API di pan/zoom/select, supporto edge bundling e
icone custom per ogni tipo di nodo. Perfetta per il caso d'uso.

## Architettura ad alto livello

```
┌─────────────────────────────────────────────────────────┐
│                        Browser                           │
│  Livewire components + Alpine.js + Cytoscape.js + SVG   │
└────────────────────────┬────────────────────────────────┘
                         │ HTTP / Livewire WebSocket
┌────────────────────────▼────────────────────────────────┐
│                       Nginx                              │
└────────────────────────┬────────────────────────────────┘
                         │
┌────────────────────────▼────────────────────────────────┐
│                  PHP-FPM (Laravel 11)                    │
│  ┌──────────────┐ ┌─────────────┐ ┌─────────────────┐  │
│  │  HTTP/Web    │ │  REST API   │ │  Livewire       │  │
│  │  Controllers │ │  (Sanctum)  │ │  Components     │  │
│  └──────────────┘ └─────────────┘ └─────────────────┘  │
│  ┌─────────────────────────────────────────────────┐   │
│  │  Domain Services (Topology, Rack, Tenancy)      │   │
│  └─────────────────────────────────────────────────┘   │
│  ┌─────────────────────────────────────────────────┐   │
│  │  Eloquent Models + Policies                     │   │
│  └─────────────────────────────────────────────────┘   │
└──────────┬─────────────────────────────┬────────────────┘
           │                             │
   ┌───────▼────────┐           ┌───────▼────────┐
   │  PostgreSQL    │           │     Redis      │
   │  (dati app)    │           │  (cache/queue) │
   └────────────────┘           └────────────────┘
```

## Modello dati (entità principali)

### Tenant / Cliente
- `tenants`: id, name, slug, domain (opzionale), settings (json), created_at
- Ogni Tenant rappresenta un **cliente** del system integrator
- Tutte le tabelle "domain" hanno una colonna `tenant_id` con foreign key + global scope

### Utenti e ruoli
- `users`: id, name, email, password, current_tenant_id
- `users_tenants` (pivot): user_id, tenant_id, role (admin / tecnico / cliente)
- Un utente può appartenere a più tenant (es. un tecnico interno gestisce 30 clienti)
- Ruoli (via spatie/laravel-permission, scoped per tenant):
  - **admin**: tutto, anche gestione utenti del tenant
  - **tecnico**: CRUD su impianti, dispositivi, connessioni
  - **cliente**: solo lettura sui propri impianti

### Strutturali
- `sites`: id, tenant_id, name, address, notes — una sede fisica del cliente
- `rooms`: id, site_id, name, floor, notes — locale tecnico / armadio
- `racks`: id, room_id, name, height_units (default 42), width, depth, position_x, position_y, notes
- `rack_units` non è una tabella: è calcolato. Ogni equipaggiamento occupa N U dentro un rack.

### Equipaggiamenti
- `equipment`: id, tenant_id, rack_id (nullable), name, type (enum), vendor, model, serial,
  firmware, position_u_start, position_u_height, mounted (bool), notes, custom_fields (json)
- `type` è enum: `switch`, `router`, `firewall`, `access_point`, `controller`, `patch_panel`,
  `server`, `ups`, `pdu`, `media_converter`, `other`
- I patch panel sono `equipment` con type=`patch_panel` e hanno `interfaces` di tipo `keystone`/`copper_port`
- Equipment può non essere montato in un rack (es. AP a soffitto): `rack_id` nullable, `mounted` false

### Interfacce
- `interfaces`: id, equipment_id, name (es. "Gi0/1", "eth0", "Port 24"), type (enum), index,
  speed (Mbps), media (copper/fiber/wireless), connector (RJ45/SFP/SFP+/QSFP/LC/SC/...),
  vlan_mode (access/trunk/hybrid/none), vlan_default, vlans_allowed (json array),
  ip_address (cidr nullable), mac_address, status (up/down/admin_down), poe (none/pse/pd),
  description, custom_fields (json)
- `type` enum: `ethernet`, `fiber`, `wireless`, `console`, `management`, `power`, `keystone`, `virtual`

### Connessioni
- `connections`: id, tenant_id, from_interface_id, to_interface_id, cable_type,
  cable_length_m (nullable), cable_label, color, notes, established_at
- Vincolo: un'interfaccia può avere al massimo UNA connessione attiva (unique parziale)
- Le connessioni rappresentano cavi fisici. Per legami logici (es. trunk LAG) si usa `link_groups`.

### Gruppi di link (LAG / port-channel)
- `link_groups`: id, tenant_id, name, mode (lacp/static), notes
- `link_group_members`: link_group_id, connection_id

### Custom & metadata
- `tags`: id, tenant_id, name, color — taggable polimorfico su equipment / connections
- `audits` (gestita da owen-it/laravel-auditing): tutte le modifiche tracciate

## Multi-tenancy — strategia

Usiamo **stancl/tenancy v3 in modalità single-database con foreign keys**:
- Una sola istanza PostgreSQL, una sola schema
- Ogni tabella di dominio ha `tenant_id`
- Un global scope Eloquent (`BelongsToTenant`) filtra automaticamente le query
- Switch tenant via subdomain OPPURE via selettore in UI (`current_tenant_id` su user)
- Per la fase 1 implementiamo solo lo **switch via UI** (più semplice), il subdomain arriverà dopo.
- Tutti i model che hanno `tenant_id` devono usare il trait `BelongsToTenant`.

## Vista grafica — due modalità

### 1. Vista Rack (rack elevation diagram)
- SVG renderizzato lato server (Blade component) con dimensioni proporzionali
- Ogni equipment è un rettangolo posizionato alle U corrette
- Click su equipment → drawer Livewire con dettaglio + lista interfacce
- Drag & drop per riposizionare via Alpine + endpoint Livewire
- Esportabile come SVG o PNG

### 2. Vista Topologica (logical / physical graph)
- Cytoscape.js inizializzato dentro un componente Alpine
- Nodi = equipment (icona diversa per type)
- Edge = connections (colore in base al media: rame=grigio, fibra=arancione, wireless=blu tratteggiato)
- Layout selezionabili: `cose-bilkent`, `dagre` (gerarchico), `breadthfirst`, `circle`
- Pan/zoom, selezione, doppio click su nodo → naviga al dettaglio
- Filtri: per tipo, per VLAN, per stato
- Esportabile come PNG (via `cy.png()`) o PDF (via Browsershot della pagina)

### Drill-down
- Cliente → Sedi → Rack → Equipment → Interfacce → Connessione
- Breadcrumb sempre visibile
- Da un'interfaccia si può saltare all'altro estremo della connessione

## Convenzioni di codice

### PHP / Laravel
- PSR-12, strict types attivi (`declare(strict_types=1)`)
- Form Requests per ogni endpoint che riceve input
- Policy per OGNI model (no controller authorization a mano)
- Service classes per logica complessa (cartella `app/Services/`)
- Action classes single-purpose (`app/Actions/`) per operazioni transazionali
- Enum PHP 8.1+ per tutti i campi `type`/`status`
- Mai logica nei Blade — solo presentazione
- Migrations: nomi `create_xxx_table`, `add_yyy_to_xxx_table`, etc.

### Frontend
- Livewire components in `app/Livewire/{Domain}/{Component}.php`
- Le view component in `resources/views/livewire/{domain}/{component}.blade.php`
- Alpine: niente codice inline lungo, usare `x-data="componentName()"` con factory in `resources/js/alpine/`
- Tailwind: utility-first, niente CSS custom se non strettamente necessario
- Componenti Blade riutilizzabili in `resources/views/components/`

### Test
- Pest per tutto
- Feature test per ogni endpoint/Livewire component
- Unit test per Service e Action
- Factory per ogni model
- Test multi-tenancy: verificare l'isolamento (utente del tenant A non vede dati del tenant B)

### Naming
- Tabelle plurali snake_case: `equipment_interfaces`
- Model singolari PascalCase: `Equipment`, `Interface` (attenzione: `Interface` è keyword PHP!
  → usiamo `NetworkInterface` come nome model, tabella `interfaces`)
- Enum: `EquipmentType`, `InterfaceType`, ecc.

## API REST

- Prefisso: `/api/v1`
- Auth: Laravel Sanctum (token-based)
- Tenant scope: header `X-Tenant-Id` obbligatorio (validato contro i tenant dell'utente)
- Risorse: `tenants`, `sites`, `rooms`, `racks`, `equipment`, `interfaces`, `connections`
- Standard JSON:API-like (data, meta, links) — usare Laravel API Resources
- Rate limit: 60 req/min per token
- OpenAPI spec generata con `darkaonline/l5-swagger`

## Roadmap a fasi (segui questo ordine!)

**FASE 0 — Setup ambiente** (vedi `docs/PHASE_00_SETUP.md`)
- docker-compose con tutti i servizi
- Laravel installer, configurazione iniziale, .env

**FASE 1 — Auth & Multi-tenancy** (vedi `docs/PHASE_01_AUTH_TENANCY.md`)
- Pacchetti, migrations base, login, registrazione, switch tenant
- Seeder con tenant demo + utente admin

**FASE 2 — Modello dati core** (vedi `docs/PHASE_02_DATA_MODEL.md`)
- Migrations, models, factory, seeder per Sites, Rooms, Racks, Equipment, Interfaces, Connections
- Policies + global scope tenant
- Test isolamento tenant

**FASE 3 — CRUD Livewire** (vedi `docs/PHASE_03_CRUD.md`)
- Componenti Livewire per liste e form
- Validazione, autorizzazione, audit log

**FASE 4 — Vista rack SVG** (vedi `docs/PHASE_04_RACK_VIEW.md`)
- Component Blade per rack elevation
- Drag & drop equipment dentro rack
- Drawer dettaglio equipment

**FASE 5 — Vista topologica Cytoscape** (vedi `docs/PHASE_05_TOPOLOGY.md`)
- Endpoint che restituisce grafo JSON
- Componente Alpine + Cytoscape.js
- Filtri, layout switching, drill-down

**FASE 6 — Export & Import** (vedi `docs/PHASE_06_EXPORT.md`)
- PDF/PNG via Browsershot
- CSV import/export equipment con validazione

**FASE 7 — API REST** (vedi `docs/PHASE_07_API.md`)
- Sanctum, controllers API, resources, OpenAPI

**FASE 8 — Polish** (vedi `docs/PHASE_08_POLISH.md`)
- Dashboard, ricerca globale, notifiche, dark mode

## Regole di lavoro per Claude Code

1. **Procedi una fase alla volta**: completa la fase X e fermati per chiedere conferma prima di passare alla X+1.
2. **Test prima di dichiarare fatto**: ogni fase deve passare i suoi test.
3. **Migrations sempre reversibili**: ogni `up()` ha il suo `down()` corretto.
4. **Mai hardcodare il tenant_id**: usa sempre il trait/scope.
5. **Mai bypassare le Policy**: niente `->where('user_id', auth()->id())` sparso nei controller.
6. **Commit atomici**: ogni step logico = un commit con messaggio convenzionale (`feat:`, `fix:`, `chore:`, `test:`).
7. **Documenta le scelte non ovvie**: commenta solo il "perché", non il "cosa".
8. **Chiedi se non chiaro**: meglio una domanda in più che una refactor in meno.

## File di riferimento

- `docs/BRAND.md` — nome, palette colori, tipografia, tono di voce
- `docs/DATA_MODEL.md` — dettaglio campo per campo di tutte le tabelle
- `docs/PHASE_XX_*.md` — task list per ogni fase
- `docs/UI_UX.md` — wireframe testuali e flussi
- `README.md` — quick start per dev

## Quick start

```bash
# Prerequisiti: Docker Desktop attivo
git clone <repo> trama && cd trama
cp .env.example .env
docker compose up -d
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec app npm install && npm run build
# App su http://localhost:8081
# Login demo: admin@demo.test / password
```
