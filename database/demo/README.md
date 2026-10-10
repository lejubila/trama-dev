# Dati della demo

Mettere qui uno o più archivi `.zip` esportati da *Clienti → Esporta dati*.
Con `DEMO_MODE=true` il comando `php artisan demo:reset` (eseguito ogni giorno
dallo scheduler) cancella tutti i dati e reimporta ogni archivio di questa
cartella come cliente. Senza archivi vengono usati i seeder dimostrativi.

Le icone globali non fanno parte degli archivi: a ogni reset vengono
ripristinate quelle di `database/icons/` (aggiornabili con
`php artisan icons:defaults --export`).
