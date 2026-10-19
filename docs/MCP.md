# Server MCP di Trama Network

Trama include un server **MCP** ([Model Context Protocol](https://modelcontextprotocol.io)).
Permette a un assistente AI di consultare e modificare l'impianto di rete dei clienti
parlando in linguaggio naturale. Funziona con Claude Code, Claude Desktop, Cursor,
VS Code e qualsiasi client MCP.

Esempi di richieste:

> «Quali porte libere ha lo switch SW-CORE del cliente Rossetti?»
>
> «Nel RACK-01 monta un nuovo switch da 1U nella prima U libera, aggiungi 24 porte
> rame e collega la porta 24 alla porta internal1 del firewall FW-01.»
>
> «Crea la rete Wi‑Fi Ospiti sulla VLAN 30, trasmessa da tutti gli access point.»

- **Endpoint:** `https://<tuo-host>/mcp` (Streamable HTTP)
- **Autenticazione:** token API di Trama, header `Authorization: Bearer <token>`
- **Regole:** le stesse dell'[API REST](API.md). Ogni tool passa per l'API, quindi
  valgono permessi del token, limite per cliente, ruoli, validazioni e registro attività.

## 1. Crea il token

Solo un **admin** può farlo.

1. In Trama apri **Token API** e clicca **Crea token**.
2. Spunta **Accesso MCP (assistenti AI)**: senza questo permesso l'endpoint `/mcp`
   risponde `403`.
3. Scegli cosa l'AI può fare con la tabella dei permessi. Per iniziare in sicurezza
   usa **Sola lettura**, poi aggiungi le scritture che ti servono.
4. Se vuoi, limita il token a uno o più **clienti**. Con un solo cliente l'AI non
   deve nemmeno indicarlo.
5. Imposta una **scadenza** e copia il token, che viene mostrato una sola volta.

L'AI vede **solo i tool consentiti dal token**. Per esempio, con la sola lettura i
tool di creazione ed eliminazione non le vengono nemmeno proposti.

## 2. Collega il client

### Claude Code

```bash
claude mcp add --transport http trama https://<tuo-host>/mcp \
  --header "Authorization: Bearer <token>"
```

Poi, in una sessione di Claude Code: *«Usa Trama per elencare i clienti»*. Con
`/mcp` vedi lo stato della connessione.

### Claude Desktop

Claude Desktop collega i server remoti tramite il ponte `mcp-remote`. Serve Node.js
20 o superiore. In `claude_desktop_config.json` (*Impostazioni → Sviluppatore →
Modifica configurazione*):

```json
{
  "mcpServers": {
    "trama": {
      "command": "npx",
      "args": ["-y", "mcp-remote", "https://<tuo-host>/mcp",
               "--header", "Authorization:${TRAMA_AUTH}"],
      "env": { "TRAMA_AUTH": "Bearer <token>" }
    }
  }
}
```

Riavvia Claude Desktop: i tool di Trama compaiono nel menu degli strumenti.

### Cursor

In `~/.cursor/mcp.json`, oppure in `.cursor/mcp.json` nel progetto:

```json
{
  "mcpServers": {
    "trama": {
      "url": "https://<tuo-host>/mcp",
      "headers": { "Authorization": "Bearer <token>" }
    }
  }
}
```

### VS Code (GitHub Copilot)

In `.vscode/mcp.json`:

```json
{
  "servers": {
    "trama": {
      "type": "http",
      "url": "https://<tuo-host>/mcp",
      "headers": { "Authorization": "Bearer <token>" }
    }
  }
}
```

### Prova manuale

[MCP Inspector](https://github.com/modelcontextprotocol/inspector), con Node.js 22
o superiore:

```bash
npx @modelcontextprotocol/inspector --cli https://<tuo-host>/mcp --transport http \
  --header "Authorization: Bearer <token>" --method tools/list
```

Oppure con `curl`:

```bash
curl -s https://<tuo-host>/mcp \
  -H "Authorization: Bearer <token>" \
  -H "Accept: application/json, text/event-stream" \
  -H "Content-Type: application/json" \
  -H "MCP-Protocol-Version: 2025-06-18" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"list_tenants","arguments":{}}}'
```

> **claude.ai (web) e ChatGPT** richiedono l'autenticazione OAuth per i connettori
> personalizzati. Non è ancora supportata: per ora usa un client che accetta header
> personalizzati, come quelli sopra.

## 3. Tool disponibili

Tutti i tool che lavorano sui dati accettano `tenant_id`, l'id del cliente letto da
`list_tenants`. Il parametro si può omettere solo se il token accede a un unico cliente.

### Consultazione

| Tool | Cosa fa | Permesso |
|---|---|---|
| `list_tenants` | Clienti accessibili con il token | `tenants:read` |
| `search` | Ricerca libera: apparati (nome, seriale, asset tag), interfacce (nome, IP, MAC), sedi, etichette dei cavi | `equipment:read` |
| `list_sites` | Sedi del cliente | `sites:read` |
| `list_rooms` | Locali di una sede | `rooms:read` |
| `list_racks` | Rack di un locale | `racks:read` |
| `get_rack` | Rack con apparati montati (U, lato) e **U libere** davanti e dietro | `racks:read` (+ `equipment:read` per gli apparati) |
| `list_equipment` | Apparati, con filtri per tipo, stato, sede, locale (nei rack o fuori), rack, hypervisor (`host_equipment_id`) e testo | `equipment:read` |
| `get_equipment` | Dettaglio di un apparato con tutte le porte e **a cosa è collegata ciascuna**. Per le interfacce virtuali mostra la NIC fisica che le porta (`backed_by`); per le NIC fisiche, le vNIC che portano (`carries`); per un hypervisor, le sue `virtual_machines` | `equipment:read` (+ `connections:read` per i collegamenti) |
| `list_connections` | Cavi con i due estremi, filtrabili per apparato e stato | `connections:read` |
| `get_topology` | Topologia compatta: apparati con posizione (sede / locale / rack) e collegamenti | `topology:read` |
| `list_wifi_networks` | Reti Wi‑Fi con VLAN, sicurezza, access point e numero di client | `wifi:read` |
| `get_wifi_network` | Dettaglio di una rete Wi‑Fi con access point e client | `wifi:read` |
| `list_vpns` | VPN site-to-site e remote access | `vpn:read` |
| `get_vpn` | Dettaglio di una VPN, con i client se remote access | `vpn:read` |

### Modifica

| Tool | Cosa fa | Permesso |
|---|---|---|
| `create_site` | Nuova sede | `sites:create` |
| `create_room` | Nuovo locale in una sede | `rooms:create` |
| `create_rack` | Nuovo rack in un locale | `racks:create` |
| `create_equipment` | Nuovo apparato, montato in un rack (`rack_id`, `u_start`, `u_height`, `side`), collocato in un locale senza armadio (`room_id`) o senza collocazione. Una VM richiede `host_equipment_id` (il suo hypervisor) | `equipment:create` |
| `update_equipment` | Modifica un apparato (solo i campi indicati). Lo sposta in un rack oppure, con `room_id`, in un locale fuori dagli armadi | `equipment:update` |
| `delete_equipment` | Elimina un apparato ⚠️ | `equipment:delete` |
| `add_interface` | Nuova porta su un apparato (stato `up` e PoE `none` di default). Per una vNIC, `backed_by_interface_id` indica la NIC fisica dell'host che la porta | `interfaces:create` |
| `update_interface` | Modifica una porta (VLAN, IP, velocità…) | `interfaces:update` |
| `delete_interface` | Elimina una porta ⚠️ | `interfaces:delete` |
| `connect_interfaces` | Registra un cavo tra due porte | `connections:create` |
| `disconnect_interfaces` | Rimuove un cavo ⚠️ | `connections:delete` |
| `create_wifi_network` | Nuova rete Wi‑Fi con i suoi access point | `wifi:create` |
| `update_wifi_network` | Modifica una rete Wi‑Fi | `wifi:update` |
| `delete_wifi_network` | Elimina una rete Wi‑Fi ⚠️ | `wifi:delete` |
| `attach_wifi_client` | Associa un dispositivo client a una rete Wi‑Fi. Se manca, crea l'interfaccia `wlanN` | `wifi:update` |
| `detach_wifi_client` | Rimuove un client da una rete Wi‑Fi ⚠️ | `wifi:update` |
| `create_vpn_site_to_site` | Nuova VPN tra due porte di firewall | `vpn:create` |
| `create_vpn_remote_access` | Nuova VPN remote access su una porta di firewall | `vpn:create` |
| `attach_vpn_client` | Collega un client a una VPN remote access. Se manca, crea l'interfaccia `vpnN` | `vpn:update` |
| `detach_vpn_client` | Rimuove un client da una VPN ⚠️ | `vpn:update` |
| `delete_vpn` | Elimina una VPN ⚠️ | `vpn:delete` |

I tool segnati con ⚠️ sono dichiarati *distruttivi* (annotazione MCP `destructiveHint`):
i client che la supportano chiedono conferma prima di eseguirli. In ogni caso le
istruzioni del server chiedono all'AI di farsi confermare eliminazioni e scollegamenti.

## 4. Sicurezza

- Il token agisce con i permessi dell'admin che lo ha creato, **ristretti** dai
  permessi e dai clienti scelti per il token. L'AI non può fare nulla che il token
  non consenta.
- Ogni modifica passa dall'API REST e finisce nel **registro attività** di Trama, a
  nome dell'utente proprietario del token.
- Limite: 120 chiamate MCP al minuto per utente. Ogni tool usa internamente l'API,
  che ha un suo limite di 60 richieste al minuto.
- In **modalità demo** l'endpoint `/mcp`, l'API REST e la gestione dei token sono
  disattivati.
- Per revocare l'accesso all'AI basta revocare il token, oppure togliergli il permesso
  *Accesso MCP*, dalla pagina **Token API**.

## Per sviluppatori

- **Server:** `app/Mcp/Servers/TramaServer.php`, con le istruzioni per il modello e
  l'elenco dei tool. La rotta è in `routes/ai.php` (pacchetto `laravel/mcp`).
- **Tool:** `app/Mcp/Tools/*`, che estendono `App\Mcp\TramaTool`.
  - La proprietà `$ability` decide quando il tool è visibile.
  - Il metodo `tenant()` risolve il cliente e attiva lo scope.
  - Il metodo `api()` chiama l'API REST tramite `App\Mcp\ApiBridge`: richiesta
    interna con lo stesso token, risposta semplificata, errori 422/403/404 resi leggibili.
- Per aggiungere un tool:
  1. crea la classe in `app/Mcp/Tools`, preferibilmente sopra un endpoint REST
     esistente;
  2. aggiungila a `$tools` nel server;
  3. scrivi un test in `tests/Feature/Mcp`.
