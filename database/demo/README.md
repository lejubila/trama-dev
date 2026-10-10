# Dati della demo

Mettere qui uno o più archivi `.zip` esportati da *Clienti → Esporta dati*.
Con `DEMO_MODE=true` il comando `php artisan demo:reset` (eseguito ogni giorno
dallo scheduler) cancella tutti i dati e reimporta ogni archivio di questa
cartella come cliente. Senza archivi viene creato il cliente demo con
`DemoCustomerSeeder` (senza snapshot e documenti, che richiedono un browser).

Le icone globali non fanno parte degli archivi: a ogni reset vengono
ripristinate quelle di `database/icons/` (aggiornabili con
`php artisan icons:defaults --export`).

## `rossetti-partners.zip`

Cliente di fantasia "Studio Rossetti & Partners": una sede con CED (rack con
patch panel, router, firewall, switch, hypervisor Proxmox con 4 VM, NAS, UPS)
e un ufficio open space (PC, notebook, access point, multifunzione, TV, prese
a muro), più reti Wi‑Fi, VPN, tag, 5 snapshot della topologia e la
documentazione in italiano e in inglese.

Per rigenerarlo su un'installazione di sviluppo:

1. `php artisan db:seed --class=DemoCustomerSeeder` (crea il cliente; fallisce
   se esiste già con slug `rossetti-partners`).
2. Dall'interfaccia, sul nuovo cliente: salvare gli snapshot della topologia e
   creare i documenti (*Documentazione → Nuovo*).
3. *Clienti → Esporta dati* e salvare lo ZIP qui come `rossetti-partners.zip`.
