# FASE 0 — Setup ambiente

> Obiettivo: avere Laravel 11 funzionante dentro Docker con tutti i servizi up.

## Prerequisiti verificati
- [ ] `docker compose version` mostra Compose v2+
- [ ] Tutte le porte richieste (8080, 5432, 6379, 1025, 8025) sono libere

## Task

### 1. Verifica struttura iniziale
Devono esistere i file seguenti (già consegnati):
- `docker-compose.yml`
- `docker/app/Dockerfile`
- `docker/nginx/default.conf`
- `.env.example`
- `.gitignore`
- `README.md`
- `CLAUDE.md`
- `docs/`

### 2. Crea il progetto Laravel
Il progetto **non è ancora stato installato**. Eseguire:

```bash
docker compose build app
docker compose run --rm app composer create-project laravel/laravel:^11.0 /tmp/laravel
# Poi sposta i file Laravel nella root mantenendo i file già presenti
docker compose run --rm app sh -c "cp -rn /tmp/laravel/. /var/www/html/ && rm -rf /tmp/laravel"
```

In alternativa più pulita: installare Laravel direttamente nella root vuota (escludendo i file già presenti).

```bash
docker compose run --rm app sh -c "
  composer create-project laravel/laravel:^11.0 /tmp/skel &&
  rsync -a --ignore-existing /tmp/skel/ /var/www/html/ &&
  rm -rf /tmp/skel
"
```

### 3. Avvia stack
```bash
docker compose up -d
docker compose exec app composer install
cp .env.example .env  # se non già fatto
docker compose exec app php artisan key:generate
```

### 4. Configura il file `config/database.php`
Default è già pgsql via env. Verifica solo che `DB_CONNECTION=pgsql` e gli altri valori in `.env` corrispondano al servizio `postgres` di compose.

### 5. Test connessione DB
```bash
docker compose exec app php artisan migrate
```
Devono passare le migration di default (users, cache, jobs).

### 6. Setup Vite + Tailwind
Laravel 11 viene già con Vite + Tailwind. Verificare `package.json` e `vite.config.js`.
```bash
docker compose exec app npm install
docker compose exec app npm run build
```

### 7. Pacchetti Composer da installare
Eseguire UNA SOLA volta in coda:
```bash
docker compose exec app composer require \
  livewire/livewire:^3.5 \
  spatie/laravel-permission:^6.10 \
  owen-it/laravel-auditing:^13.6 \
  spatie/browsershot:^4.2 \
  spatie/laravel-data:^4.10 \
  laravel/sanctum:^4.0 \
  darkaonline/l5-swagger:^8.6 \
  league/csv:^9.16

docker compose exec app composer require --dev \
  pestphp/pest:^3.0 \
  pestphp/pest-plugin-laravel:^3.0 \
  larastan/larastan:^3.0 \
  laravel/pint:^1.18
```

### 8. Pubblica config & migrations dei pacchetti
```bash
docker compose exec app php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
docker compose exec app php artisan vendor:publish --provider="OwenIt\Auditing\AuditingServiceProvider" --tag="config"
docker compose exec app php artisan vendor:publish --provider="OwenIt\Auditing\AuditingServiceProvider" --tag="migrations"
docker compose exec app php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
docker compose exec app php artisan livewire:publish --config
docker compose exec app php artisan vendor:publish --provider="L5Swagger\L5SwaggerServiceProvider"
```

### 9. Inizializza Pest
```bash
docker compose exec app ./vendor/bin/pest --init
```

### 10. Configura Pint e PHPStan
Crea `pint.json` con preset Laravel.
Crea `phpstan.neon` con livello 6 e include il bootstrap di larastan.

### 11. Smoke test
```bash
docker compose exec app php artisan test
```
Devono passare i test boilerplate.

Apri http://localhost:8080 → deve apparire la pagina di benvenuto Laravel.

## Definition of Done

- [ ] `docker compose ps` mostra tutti i container "running" e healthy
- [ ] http://localhost:8080 → welcome Laravel
- [ ] http://localhost:8025 → Mailpit UI
- [ ] `php artisan test` verde
- [ ] `php artisan migrate:status` elenca migrations base
- [ ] Pacchetti installati senza errori
- [ ] Commit: `chore: phase 0 — environment ready`

➡️ Procedi alla **FASE 1** quando tutti i checkpoint sono verdi.
