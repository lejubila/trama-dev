# Changelog

Tutti i commit di prodotto raggruppati per fase. Le fasi di sviluppo sono
descritte in `docs/PHASE_*.md`.

## [phase-8] — 2026-05-09 — Polish & finalize

- Dashboard ricca: KPI sedi/rack/dispositivi/connessioni/% interfacce up,
  breakdown per tipo, salute (dispositivi non attivi + interfacce down),
  mappa sedi, ultime modifiche. Cache 60s per ridurre query.
- Ricerca globale in topbar: input debounced (300ms), risultati raggruppati
  per equipment/interfacce/sedi/connessioni. ilike + LIMIT 5 per gruppo,
  scoping al tenant attivo.
- Dark mode: toggle three-state (light/system/dark), preferenza persistita
  su `users.preferences` (jsonb), FOUC-safe via classe early sul `<html>`.
  `tailwind.config.js` impostato su `darkMode: 'class'`.
- Notifiche persistenti via canale `database` di Laravel: bell topbar con
  badge unread, dropdown con ultimi 8, pagina `/notifications` con storico
  paginato. `ImportEquipmentCsv` dispatcha `ImportCompleted` al termine.
- Mini-mappa room: `<x-room-map>` SVG che dispone i rack secondo le
  coordinate `position_x`/`position_y` con auto-fit del viewBox, mostrata
  sotto la pagina sede.
- Audit `composer audit` e `npm audit` puliti (1 abandoned transitive
  `doctrine/annotations` da `l5-swagger`, non azionabile).
- Test: 12 nuovi (Dashboard, GlobalSearch, ThemeToggle, Notifications);
  171 totali, tutti verdi.

## [phase-7] — 2026-05-09 — REST API

REST `/api/v1` autenticata via Sanctum + header `X-Tenant-Id`. Resources
JSON:API-ish, FormRequest condivisi tra Store/Update con `MakesOptionalOnUpdate`,
controller thin, integrazione `RackPlacementService` e `ConnectionService`
sull'API boundary. Token management endpoint + UI a
`/settings/api-tokens`. Rate limit 60/min keyed user.id. Swagger UI su
`/api/documentation`. Postman collection minimale in `docs/postman/`.
43 nuovi test (147 totali).

## [phase-6] — 2026-05-09 — Export/Import (CSV, PDF rack)

- Export CSV equipment via League\Csv, scoping per tenant.
- Export PDF rack: render del Blade `exports.rack-print` direttamente con
  `Browsershot::html()` (no signed URL roundtrip). Puppeteer installato come
  dev dependency con `PUPPETEER_SKIP_CHROMIUM_DOWNLOAD=true`.
- Import CSV equipment con preview → validation → transaction; site/room/rack
  auto-created se mancano. Tabella `imports` traccia lo storico con summary
  jsonb. `ImportEquipmentCsv` invia notifica all'utente.
- Cleanup file export schedulato `exports:cleanup --hours=24` ogni 03:00.
- 7 nuovi test (104 totali).

## [phase-5] — 2026-05-09 — Topology view (Cytoscape.js)

Vista `/topology`: grafo Cytoscape con cose-bilkent/dagre/breadthfirst/circle/grid.
Filtri sede/tipo/VLAN/status reattivi via Livewire+Alpine `$watch`. Drill-down:
tap node → drawer Equipment (riusa quello di FASE 4); double-tap → racks/{id}.
Mini-mappa via cytoscape-navigator. Export PNG client-side. 9 nuovi test
(97 totali).

## [phase-4] — 2026-05-09 — Rack elevation view

`<x-rack-elevation />` SVG con frame, U numbering bottom_up/top_down (label
flip, geometria invariante), equipment colorati per tipo, mini pallini
interfaccia. Drag &amp; drop con snap-to-U via `getScreenCTM` + soglia
anti-click 4px. Backend valida overlap/overflow/locked tramite
`RackPlacementService` e dispatcha toast errore. Drawer Equipment
(`equipment-clicked` listener) con tab Generale/Interfacce/Connessioni/Audit.
Flag `equipment.locked`. 8 nuovi test (88 totali).

## [phase-3] — 2026-05-09 — Livewire CRUD

CRUD via UI per Sites/Rooms (inline)/Racks/Equipment+Interfaces (inline)
/Connections (wizard 3-step)/Tags/Audit. Layout app con sidebar+toaster,
ogni save dispatcha evento `toast`. Audit Trail con filtro modello/evento.
Audit console abilitato in test (`AUDIT_CONSOLE=true`) per testare la
trail senza HTTP. 30 nuovi test (80 totali).

## [phase-2] — 2026-05-09 — Core data model

10 migrations (sites/rooms/racks/equipment/interfaces/connections/link_groups
/link_group_connection/tags/taggables) con FK e indici, vincolo `connections`
unique parziale active via due `CREATE UNIQUE INDEX ... WHERE status='active'`.
Enum dedicati (`EquipmentType`, `InterfaceMedia`, `ConnectionStatus`, ecc.).
Trait `TenantAuditable` snapshotta `tenant_id` su ogni audit row. Policy
condivise via `ChecksTenantMembership`. RackPlacementService, ConnectionService,
TopologyService. DemoDataSeeder costruisce ACME (17 equipment, 13 cavi) +
Beta (5 equipment, 3 cavi). 18 nuovi test (50 totali).

## [phase-1] — 2026-05-09 — Auth &amp; multi-tenancy

Breeze livewire stack. Migrations tenants/tenant_user/current_tenant_id.
TenantContext, TenantScope global scope, trait BelongsToTenant. SetCurrentTenant
middleware, switch route `/tenant/switch/{tenant}`. Spatie permission
`teams=true` con `team_foreign_key=tenant_id`. Audits column `tenant_id`.
Seeder demo: ACME Spa + Beta Srl, 3 utenti (admin/tecnico/cliente).
DB di test separato `trama_test`. 6 nuovi test (32 totali).

## [phase-0] — 2026-05-09 — Environment ready

Laravel 11.31, Docker Compose (app/nginx/postgres/redis/mailpit/scheduler/queue),
porta web :8081 (8080 occupata sull'host), host UID/GID nel build args
dell'immagine app, no named volume su storage. Pacchetti runtime + dev
installati. Pint + PHPStan livello 6 attivi.

## Cronologia commit

Vedi `git log --oneline` per la lista completa dei commit. Ogni fase ha
un commit dedicato con il riepilogo dei cambiamenti.
