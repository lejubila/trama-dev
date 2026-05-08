# FASE 8 — Polish

> Obiettivo: rifinire l'app per renderla piacevole da usare, aggiungere dashboard,
> ricerca globale, dark mode, notifiche, performance.

## Dashboard tenant

Pagina `/dashboard` con widget:
- KPI: # sedi, # rack, # dispositivi (per tipo, stacked bar), # connessioni, % interfacce up
- Mappa sedi (semplice lista raggruppata, opzionale Leaflet con `position_lat/lng` se popolato)
- Salute: dispositivi in stato `maintenance`/`inactive`, interfacce `down`
- Ultime modifiche (ultimi 10 audit log)
- Quick actions: "+ Dispositivo", "+ Connessione", "Vai alla topologia"

Componente `App\Livewire\Dashboard\Index`. Cache i KPI per 60 secondi (`Cache::remember`).

## Ricerca globale

Topbar `App\Livewire\GlobalSearch`:
- Input con debounce 300ms
- Cerca su Equipment.name/serial/asset_tag, NetworkInterface.name/ip/mac, Site.name, Connection.cable_label
- Risultati raggruppati per tipo, max 5 per gruppo
- Click → naviga al record
- Keyboard nav (↑↓ Enter)

Per performance, indici GIN PostgreSQL su colonne text + uso di `ilike`. Se serve full-text avanzato, valutare `pg_trgm` o `Laravel Scout` con MeiliSearch (V2).

## Dark mode

- Toggle in topbar
- Persisti preferenza in `localStorage` + colonna user `preferences` json
- Tailwind `dark:` su tutti i componenti già fatti
- Verifica leggibilità di SVG rack e topologia in entrambi i temi

## Notifiche

Sistema toast già fatto in fase 3. Aggiungiamo:
- Notifiche persistenti (`notifications` table di Laravel) per:
  - Export pronto
  - Import completato
  - Mention in audit (V2)
- Badge nel topbar con conteggio non-letti
- Pagina `/notifications` con storico

## Performance

### Eager loading
Verificare con Laravel Debugbar (dev only) che non ci siano N+1 query nei seguenti punti:
- Dashboard
- Topologia (TopologyService deve eager-loadare interfaces + connections)
- Lista equipment con relazione rack/room/site

### Cache
- Topology graph: cache per `(tenant_id, filters)` con TTL 5 min, invalidato su create/update/delete di Equipment/Connection
- Counters dashboard: cache 60 secondi

### DB
- Verificare indici su:
  - `equipment(tenant_id, type)`
  - `interfaces(equipment_id)`
  - `connections(tenant_id, status)`
  - `audits(auditable_type, auditable_id)`

## Logging e monitoring

- Configurare canale `daily` con retention 14 giorni
- Log strutturati JSON per produzione (`LOG_STACK` con channel `papertrail`/`stack`)
- Telescope (dev only) per debug richieste:
```bash
docker compose exec app composer require laravel/telescope --dev
docker compose exec app php artisan telescope:install
docker compose exec app php artisan migrate
```

## Sicurezza

- Headers di sicurezza (HSTS, X-Frame-Options, CSP) in middleware globale
- Sanctum: cookie SameSite=lax in dev, strict in prod
- CSRF: già attivo via Livewire
- SQL injection: Eloquent + parameter binding ovunque
- File upload: validazione MIME + size + storage isolato
- Audit dei tentativi di accesso falliti: limita brute force con `RateLimiter`

## Backup

Pacchetto `spatie/laravel-backup`:
```bash
docker compose exec app composer require spatie/laravel-backup
```
Configura backup giornaliero di DB + storage in `storage/app/backups/`.
Comando `php artisan backup:run` da schedulare nel scheduler container.

## i18n

L'app è in italiano di default (utenti italiani principalmente). Predisposta per i18n:
- File `lang/it.json` e `lang/en.json` con tutte le stringhe UI
- Helper `__('key')` ovunque, niente stringhe hardcoded nelle Blade
- Switch lingua nel menu utente

## Documentazione utente

`docs/USER_GUIDE.md` con screenshot e spiegazioni per:
- Come si crea un cliente
- Come si modella la prima sede
- Come si disegna una connessione
- Come si esporta un PDF
- Glossario

## Pulizia finale

- [ ] `./vendor/bin/pint` zero warning
- [ ] `./vendor/bin/phpstan analyse` zero error a livello 6
- [ ] Coverage Pest > 70% (controllare con `--coverage`)
- [ ] `npm run build` produce assets minimizzati
- [ ] README aggiornato con tutte le feature finali
- [ ] CHANGELOG.md popolato per tutte le fasi
- [ ] LICENSE file presente

## Definition of Done

- [ ] Dashboard funzionante e veloce (< 500ms)
- [ ] Ricerca globale rapida e accurata
- [ ] Dark mode senza glitch visivi
- [ ] Notifiche persistenti operative
- [ ] Backup schedulato e testato
- [ ] App passa security audit base (composer audit, npm audit)
- [ ] Documentazione utente completa
- [ ] Commit: `feat: phase 8 — polish & finalize`

🎉 **Progetto completo!**
