# API REST di Trama Network

Trama espone un'API REST JSON sotto il prefisso **`/api/v1`**. Permette di leggere
e modificare i dati documentati nell'applicazione da script, sistemi di
monitoraggio, strumenti di provisioning o assistenti AI.

- **URL base:** `https://<tuo-host>/api/v1` (in sviluppo `http://localhost:8081/api/v1`)
- **Formato:** JSON in ingresso e in uscita. Invia sempre `Accept: application/json`.
- **Autenticazione:** token personale (Laravel Sanctum) nell'header `Authorization: Bearer <token>`.
- **Cliente:** header `X-Tenant-Id: <id>` obbligatorio su quasi tutte le rotte.
- **Limite:** 60 richieste al minuto per utente.
- **Collezione Postman:** [`docs/postman/trama.json`](postman/trama.json).
- **Assistenti AI:** per collegare Claude, Cursor o un altro client MCP vedi [MCP.md](MCP.md).

## Indice

- [Token di accesso](#token-di-accesso)
- [Permessi dei token](#permessi-dei-token)
- [Header e cliente attivo](#header-e-cliente-attivo)
- [Risposte, paginazione ed errori](#risposte-paginazione-ed-errori)
- [Endpoint](#endpoint)
  - [Clienti](#clienti)
  - [Sedi](#sedi)
  - [Locali](#locali)
  - [Rack](#rack)
  - [Apparati](#apparati)
  - [Interfacce](#interfacce)
  - [Connessioni](#connessioni)
  - [Topologia](#topologia)
  - [Reti Wi-Fi](#reti-wi-fi)
  - [VPN site-to-site](#vpn-site-to-site)
  - [VPN remote access](#vpn-remote-access)
  - [Gestione dei token via API](#gestione-dei-token-via-api)
- [Valori ammessi](#valori-ammessi)
- [Esempio completo](#esempio-completo)

---

## Token di accesso

Solo gli utenti **admin** possono creare token.

1. Accedi a Trama come admin e apri **Token API** nella barra laterale (sezione *Organizzazione*).
2. Clicca **Crea token**. Inserisci:
   - il **nome**;
   - i **permessi**: una tabella risorsa × azione con i preset *Sola lettura*, *Tutto*, *Nessuno*;
   - i **clienti** accessibili: tutti oppure solo quelli selezionati;
   - una **scadenza** facoltativa.
3. Copia subito il token: viene mostrato **una sola volta**.

Dalla stessa pagina puoi modificare in seguito permessi, clienti e scadenza di un
token, con **Modifica permessi**, oppure revocarlo.

Il token agisce con i permessi dell'admin che lo ha creato, **ristretti** a quelli
scelti per il token. Le regole di Trama (policy, isolamento tra clienti) valgono
sempre.

> In **modalità demo** l'API è disattivata: tutte le rotte `/api/v1/*`, la documentazione
> Swagger e la pagina **Token API** rispondono `404`.

## Permessi dei token

Ogni permesso ha la forma `risorsa:azione`. Una richiesta senza il permesso
necessario riceve `403` con il permesso mancante:

```json
{ "error": "Token lacks ability: equipment:delete" }
```

| Risorsa | `read` | `create` | `update` | `delete` | Rotte coperte |
|---|:-:|:-:|:-:|:-:|---|
| `tenants` | ✓ | | | | `GET /tenants` |
| `sites` | ✓ | ✓ | ✓ | ✓ | `/sites` |
| `rooms` | ✓ | ✓ | ✓ | ✓ | `/sites/{site}/rooms` |
| `racks` | ✓ | ✓ | ✓ | ✓ | `/rooms/{room}/racks`, `/racks/{rack}` |
| `equipment` | ✓ | ✓ | ✓ | ✓ | `/equipment` |
| `interfaces` | ✓ | ✓ | ✓ | ✓ | `/equipment/{id}/interfaces`, `/interfaces/{id}` |
| `connections` | ✓ | ✓ | ✓ | ✓ | `/connections` |
| `topology` | ✓ | | | | `GET /topology` |
| `wifi` | ✓ | ✓ | ✓ | ✓ | `/wifi-networks` e i relativi client |
| `vpn` | ✓ | ✓ | ✓ | ✓ | `/vpn/site-to-site`, `/vpn/remote-access` e i relativi client |

Regole:
- `read` copre l'elenco e il dettaglio, cioè `GET`. Le altre azioni corrispondono a
  `POST` (`create`), `PUT`/`PATCH` (`update`) e `DELETE` (`delete`).
- **Associare o rimuovere un client** da una rete Wi-Fi o da una VPN remote access
  è una modifica della rete o della VPN, quindi richiede `wifi:update` o `vpn:update`.
- Concedere un'azione di scrittura concede automaticamente anche `read` sulla
  stessa risorsa.
- Ci sono due permessi speciali, non inclusi in nessun preset:
  - `tokens:manage` consente di gestire i token via API (vedi [sotto](#gestione-dei-token-via-api));
  - `mcp:access` consente di usare il token con il [server MCP](MCP.md).
- Una rotta nuova senza un permesso associato è **negata** per default.

## Header e cliente attivo

| Header | Obbligatorio | Note |
|---|---|---|
| `Authorization: Bearer <token>` | sempre | Token assente, errato o scaduto → `401` |
| `Accept: application/json` | consigliato | Garantisce errori in JSON |
| `Content-Type: application/json` | con un corpo | `POST`, `PUT`, `PATCH` |
| `X-Tenant-Id: <id>` | tutte le rotte tranne `/tenants` e `/tokens` | Cliente su cui operare |

I dati sono sempre filtrati sul cliente indicato da `X-Tenant-Id`. Se l'header
manca la risposta è `400`. La risposta è `403` (`Forbidden tenant`) quando:
- il cliente non esiste;
- l'utente non vi ha accesso (gli utenti *cliente* vedono solo i clienti assegnati);
- il token è limitato ad altri clienti.

Usa `GET /tenants` per sapere quali id puoi usare.

## Risposte, paginazione ed errori

Le risorse seguono una struttura in stile JSON:API:

```json
{
  "data": {
    "id": 42,
    "type": "equipment",
    "attributes": { "name": "SW-CORE-01", "kind": "switch", "...": "..." },
    "relationships": { "interfaces": [ ... ] },
    "links": { "self": "https://host/api/v1/equipment/42" }
  }
}
```

Le liste sono paginate. Il parametro `per_page` (default 50, massimo 200) vale dove
indicato. Le risposte includono `links` (`first`, `last`, `prev`, `next`) e `meta`
(`current_page`, `last_page`, `per_page`, `total`). Per le pagine successive usa
`?page=N`.

| Codice | Significato |
|---|---|
| `200` | OK |
| `201` | Risorsa creata |
| `204` | Eliminata (nessun contenuto) |
| `400` | Manca `X-Tenant-Id` |
| `401` | Token mancante, non valido o scaduto |
| `403` | Permesso del token mancante, cliente non consentito o azione non permessa dal ruolo |
| `404` | Risorsa inesistente o di un altro cliente |
| `422` | Dati non validi. Il corpo contiene `message` ed `errors` per campo |
| `429` | Limite di richieste superato |

Esempio di `422`:

```json
{
  "message": "Not an interface of a firewall (1055)",
  "errors": { "firewall_interface_id": ["Not an interface of a firewall (1055)"] }
}
```

Le eliminazioni di sedi, locali, rack, apparati, reti Wi-Fi e VPN sono *soft delete*: i dati
restano nel database e nel registro attività.

---

## Endpoint

Nelle tabelle seguenti i campi segnati con **\*** sono obbligatori in creazione. In
`PUT`/`PATCH` tutti i campi sono facoltativi: si inviano solo quelli da cambiare.

### Clienti

| Metodo | Percorso | Permesso | Descrizione |
|---|---|---|---|
| `GET` | `/tenants` | `tenants:read` | Clienti accessibili a questo token |

Non richiede `X-Tenant-Id`. Restituisce `id`, `attributes.name`, `attributes.slug`.

### Sedi

| Metodo | Percorso | Permesso |
|---|---|---|
| `GET` | `/sites?q=&per_page=` | `sites:read` |
| `POST` | `/sites` | `sites:create` |
| `GET` | `/sites/{site}` | `sites:read` |
| `PUT/PATCH` | `/sites/{site}` | `sites:update` |
| `DELETE` | `/sites/{site}` | `sites:delete` |

| Campo | Tipo | Note |
|---|---|---|
| `name`\* | string ≤150 | |
| `address` | string ≤255 | |
| `latitude` / `longitude` | numero | −90…90 / −180…180 |
| `notes` | string | |

Filtro: `q` (nome contiene).

### Locali

| Metodo | Percorso | Permesso |
|---|---|---|
| `GET` | `/sites/{site}/rooms` | `rooms:read` |
| `POST` | `/sites/{site}/rooms` | `rooms:create` |
| `GET` | `/sites/{site}/rooms/{room}` | `rooms:read` |
| `PUT/PATCH` | `/sites/{site}/rooms/{room}` | `rooms:update` |
| `DELETE` | `/sites/{site}/rooms/{room}` | `rooms:delete` |

| Campo | Tipo | Note |
|---|---|---|
| `site_id`\* | int | Sede del locale |
| `name`\* | string ≤150 | |
| `floor` | string ≤50 | |
| `notes` | string | |

### Rack

| Metodo | Percorso | Permesso |
|---|---|---|
| `GET` | `/rooms/{room}/racks` | `racks:read` |
| `POST` | `/rooms/{room}/racks` | `racks:create` |
| `GET` | `/racks/{rack}` | `racks:read` |
| `PUT/PATCH` | `/racks/{rack}` | `racks:update` |
| `DELETE` | `/racks/{rack}` | `racks:delete` |

| Campo | Tipo | Note |
|---|---|---|
| `room_id`\* | int | |
| `name`\* | string ≤100 | |
| `height_units`\* | int 1…60 | Altezza in U |
| `width_mm` / `depth_mm` | int | 100…1500 / 100…2000 |
| `numbering` | `bottom_up` \| `top_down` | Numerazione delle U |
| `position_x` / `position_y` | numero 0…999.99 | Posizione nella planimetria del locale |
| `notes` | string | |

### Apparati

| Metodo | Percorso | Permesso |
|---|---|---|
| `GET` | `/equipment` | `equipment:read` |
| `POST` | `/equipment` | `equipment:create` |
| `GET` | `/equipment/{equipment}` | `equipment:read` (include le interfacce e il rack) |
| `PUT/PATCH` | `/equipment/{equipment}` | `equipment:update` |
| `DELETE` | `/equipment/{equipment}` | `equipment:delete` |

Filtri: `q` (nome, seriale o modello contiene), `type`, `site_id`, `room_id`
(apparati del locale, nei rack o fuori), `rack_id`, `host_equipment_id` (le VM di un
hypervisor), `status`, `per_page`.

| Campo | Tipo | Note |
|---|---|---|
| `name`\* | string ≤150 | |
| `type`\* | enum | Vedi [tipi di apparato](#valori-ammessi). Nella risposta si chiama `kind` |
| `vendor`, `model`, `serial`, `firmware`, `asset_tag` | string | |
| `status` | enum | `active`, `inactive`, `maintenance`, `decommissioned` |
| `management_ip` | IP | |
| `description` | string | |
| `rack_id` | int | Rack in cui è montato |
| `room_id` | int | Locale. Per un apparato in rack è quello del rack; da solo colloca l'apparato nel locale **senza armadio** |
| `mounted` | bool | Con `true` sono obbligatori `rack_id`, `position_u_start` e `position_u_height` |
| `position_u_start` / `position_u_height` | int 1…60 | Prima U e numero di U occupate |
| `position_orient` | `front` \| `rear` | |
| `locked` | bool | Impedisce lo spostamento dalla vista rack |
| `host_equipment_id` | int | Solo per `virtual_machine`, dove è obbligatorio: l'hypervisor su cui gira la VM |

Se la posizione è occupata o esce dal rack, la risposta è `422` su `position_u_start`.

**Apparato in un locale senza armadio** (switch a muro, firewall da scrivania, NAS…):
- Passa `room_id` senza `mounted: true`, per esempio `{"name":"SW-WALL","type":"switch","room_id":7}`.
  L'apparato non è montato e non occupa U.
- Con un `PATCH` di `room_id` su un apparato montato in un rack, l'apparato esce dal
  rack e passa nel locale. Fa eccezione il caso in cui il locale indicato è quello del
  rack: allora non cambia nulla.
- Per un apparato montato, `room_id` viene preso dal rack. Se lo indichi insieme a
  `mounted: true` deve coincidere con il locale del rack, altrimenti la risposta è `422`.

**Macchine virtuali:**
- Una `virtual_machine` deve avere come `host_equipment_id` un apparato di tipo
  `hypervisor` dello stesso cliente.
- Non si monta in un rack: con `mounted: true` la risposta è `422`. Se il locale non è
  indicato, la VM eredita quello dell'host.
- Se la VM passa su un altro host, le sue vNIC perdono il backing (vedi
  [Interfacce](#interfacce)), perché le NIC fisiche del vecchio host non la portano più.

### Interfacce

| Metodo | Percorso | Permesso |
|---|---|---|
| `GET` | `/equipment/{equipment}/interfaces` | `interfaces:read` |
| `POST` | `/equipment/{equipment}/interfaces` | `interfaces:create` |
| `GET` | `/interfaces/{interface}` | `interfaces:read` |
| `PUT/PATCH` | `/interfaces/{interface}` | `interfaces:update` |
| `DELETE` | `/interfaces/{interface}` | `interfaces:delete` |

| Campo | Tipo | Note |
|---|---|---|
| `equipment_id`\* | int | |
| `name`\* | string ≤80 | Es. `Gi1/0/1`, `eth0`, `wlan0` |
| `type`\* | enum | Nella risposta si chiama `kind` |
| `media`\* | enum | `copper`, `fiber`, `wireless`, `virtual` |
| `status`\* | enum | `up`, `down`, `admin_down`, `unknown` |
| `poe`\* | enum | `none`, `pse`, `pd` |
| `index` | int ≥0 | Ordine |
| `speed_mbps` | int | |
| `connector` | string ≤20 | Es. `RJ45`, `SFP+`, `LC` |
| `vlan_mode` | enum | `none`, `access`, `trunk`, `hybrid`, `transparent` |
| `vlan_default` | int 1…4094 | VLAN nativa / di accesso |
| `vlans_allowed` | int[] | VLAN consentite sul trunk |
| `ip_address` | string | Anche in CIDR, es. `10.0.0.1/24` |
| `mac_address` | string ≤17 | |
| `description` | string ≤255 | |
| `backed_by_interface_id` | int \| null | Solo per `type: virtual`: la porta fisica che porta il traffico. Vedi sotto |

**Backing vNIC → NIC fisica:**
- Un'interfaccia `virtual` può indicare in `backed_by_interface_id` la porta fisica che
  ne porta il traffico:
  - per una **vNIC di VM** è una NIC dell'hypervisor host;
  - per una **sotto-interfaccia VLAN** (firewall, router, server) è una porta dello
    stesso apparato.
- La porta deve essere di tipo `ethernet`, `fiber` o `wireless`. Più vNIC possono
  condividere la stessa NIC fisica.
- Non è un cavo, quindi non crea una connessione. In topologia appare come
  collegamento tratteggiato tra VM e hypervisor.
- `null` toglie il backing. Se l'interfaccia smette di essere `virtual`, il backing
  viene tolto automaticamente.
- Una NIC che non appartiene all'host, che non è fisica, o il backing di
  un'interfaccia non virtuale danno `422` su `backed_by_interface_id`.

```bash
# vNIC net0 della VM 42, portata dalla NIC eno1 (id 310) dell'hypervisor
curl -X POST https://<host>/api/v1/equipment/42/interfaces \
  -H "Authorization: Bearer <token>" -H "X-Tenant-Id: 1" -H "Content-Type: application/json" \
  -d '{"equipment_id":42,"name":"net0","type":"virtual","media":"virtual","status":"up","poe":"none","backed_by_interface_id":310}'
```

### Connessioni

Rappresentano i cavi fisici tra due interfacce. Un'interfaccia può avere una sola
connessione attiva.

| Metodo | Percorso | Permesso |
|---|---|---|
| `GET` | `/connections?status=&per_page=` | `connections:read` |
| `POST` | `/connections` | `connections:create` |
| `GET` | `/connections/{connection}` | `connections:read` |
| `PUT/PATCH` | `/connections/{connection}` | `connections:update` |
| `DELETE` | `/connections/{connection}` | `connections:delete` |

| Campo | Tipo | Note |
|---|---|---|
| `from_interface_id`\* | int | Diversa da `to_interface_id` |
| `to_interface_id`\* | int | |
| `cable_type`\* | string ≤30 | Es. `cat6`, `om4`, `dac` |
| `cable_length_m` | numero | |
| `cable_label` | string ≤80 | |
| `color` | string ≤20 | |
| `status` | enum | `active`, `planned`, `decommissioned` |
| `notes` | string | |
| `established_at` | data | |

In `PUT`/`PATCH` si modificano solo i dati del cavo. Per cambiare gli estremi,
elimina la connessione e creane una nuova.

### Topologia

| Metodo | Percorso | Permesso |
|---|---|---|
| `GET` | `/topology` | `topology:read` |

Filtri:
- `site_id`;
- `types`, cioè i tipi di apparato separati da virgola, es. `switch,router,firewall`;
- `vlan`;
- `status`.

Restituisce il grafo nel formato di Cytoscape.js:

```json
{
  "data": {
    "nodes": [ { "data": { "id": "eq-12", "label": "SW-CORE-01", "type": "switch", "parent": "rack-3", "...": "..." } } ],
    "edges": [ { "data": { "id": "cn-7", "source": "eq-12", "target": "eq-15", "media": "fiber",
                            "fromIface": "Te1/1/1", "toIface": "Te1/1/2", "status": "active", "...": "..." } } ]
  }
}
```

Oltre ai nodi degli apparati, il grafo contiene nodi contenitore per sedi, locali
e rack (campo `kind`), collegati tramite `parent`.

### Reti Wi-Fi

| Metodo | Percorso | Permesso |
|---|---|---|
| `GET` | `/wifi-networks?q=&site_id=&per_page=` | `wifi:read` |
| `POST` | `/wifi-networks` | `wifi:create` |
| `GET` | `/wifi-networks/{id}` | `wifi:read` (include access point e client) |
| `PUT/PATCH` | `/wifi-networks/{id}` | `wifi:update` |
| `DELETE` | `/wifi-networks/{id}` | `wifi:delete` |
| `GET` | `/wifi-networks/{id}/clients` | `wifi:read` |
| `POST` | `/wifi-networks/{id}/clients` | `wifi:update` |
| `DELETE` | `/wifi-networks/{id}/clients/{client}` | `wifi:update` |

**Rete:**

| Campo | Tipo | Note |
|---|---|---|
| `ssid`\* | string ≤64 | |
| `site_id` | int | Sede della rete |
| `security_type` | string ≤32 | Es. `wpa2`, `wpa3`, `wpa2_ent`, `open` |
| `vlan_id` | int 1…4094 | |
| `hidden_ssid` | bool | |
| `notes` | string | |
| `broadcaster_interface_ids` | int[] | Interfacce che trasmettono l'SSID |

`broadcaster_interface_ids` accetta solo **interfacce wireless di access point o
controller** del cliente; altrimenti la risposta è `422`. In modifica, se il campo
non viene inviato, gli access point restano invariati. Se viene inviato, sostituisce
l'elenco.

**Associare un client:**

| Campo | Tipo | Note |
|---|---|---|
| `equipment_id`\* | int | Apparato client (PC, telefono, stampante…) |
| `interface_id` | int | Interfaccia wireless del client. Se omessa, ne viene creata una (`wlan0`, `wlan1`, …) |
| `preferred_broadcaster_interface_id` | int | Access point preferito, scelto tra quelli della rete |

Lo stesso apparato non può essere associato due volte alla stessa rete (`422`).

### VPN site-to-site

| Metodo | Percorso | Permesso |
|---|---|---|
| `GET` | `/vpn/site-to-site?q=&protocol=&per_page=` | `vpn:read` |
| `POST` | `/vpn/site-to-site` | `vpn:create` |
| `GET` | `/vpn/site-to-site/{id}` | `vpn:read` |
| `PUT/PATCH` | `/vpn/site-to-site/{id}` | `vpn:update` |
| `DELETE` | `/vpn/site-to-site/{id}` | `vpn:delete` |

| Campo | Tipo | Note |
|---|---|---|
| `name`\* | string ≤100 | |
| `protocol`\* | enum | Vedi [protocolli VPN](#valori-ammessi) |
| `endpoint_a_interface_id`\* | int | Interfaccia di un **firewall** |
| `endpoint_b_interface_id`\* | int | Interfaccia di un **firewall**, diversa da A |
| `routed_vlans_a` / `routed_vlans_b` | int[] | VLAN instradate da ciascun lato |
| `routed_networks_a` / `routed_networks_b` | string[] | Reti IPv4 in CIDR, es. `192.168.10.0/24` |
| `notes` | string | |

### VPN remote access

| Metodo | Percorso | Permesso |
|---|---|---|
| `GET` | `/vpn/remote-access?q=&protocol=&per_page=` | `vpn:read` |
| `POST` | `/vpn/remote-access` | `vpn:create` |
| `GET` | `/vpn/remote-access/{id}` | `vpn:read` (include i client) |
| `PUT/PATCH` | `/vpn/remote-access/{id}` | `vpn:update` |
| `DELETE` | `/vpn/remote-access/{id}` | `vpn:delete` |
| `GET` | `/vpn/remote-access/{id}/clients` | `vpn:read` |
| `POST` | `/vpn/remote-access/{id}/clients` | `vpn:update` |
| `DELETE` | `/vpn/remote-access/{id}/clients/{client}` | `vpn:update` |

**VPN:**

| Campo | Tipo | Note |
|---|---|---|
| `name`\* | string ≤100 | |
| `protocol`\* | enum | Vedi [protocolli VPN](#valori-ammessi) |
| `firewall_interface_id`\* | int | Interfaccia di un **firewall** su cui termina la VPN |
| `routing_mode`\* | `routed` \| `bridged` | |
| `client_network_cidr` | string | Rete assegnata ai client, es. `10.8.0.0/24` |
| `routed_vlans` | int[] | VLAN raggiungibili dai client |
| `notes` | string | |

**Collegare un client:**

| Campo | Tipo | Note |
|---|---|---|
| `equipment_id`\* | int | Apparato client |
| `interface_id` | int | Interfaccia **virtuale** del client. Se omessa, ne viene creata una (`vpn0`, `vpn1`, …) |
| `username` | string ≤255 | Utente VPN |

### Gestione dei token via API

Queste rotte non richiedono `X-Tenant-Id`. Per usarle servono **due condizioni**:
- il proprietario del token è un **admin**;
- il token ha il permesso `tokens:manage`.

| Metodo | Percorso | Descrizione |
|---|---|---|
| `GET` | `/tokens` | Token dell'utente: permessi, clienti, scadenza, ultimo uso |
| `POST` | `/tokens` | Crea un token. Il valore in chiaro (`plain_text`) è restituito una sola volta |
| `DELETE` | `/tokens/{id}` | Revoca un token |

| Campo (`POST`) | Tipo | Note |
|---|---|---|
| `name`\* | string ≤80 | |
| `abilities` | string[] | Permessi `risorsa:azione`. Se omesso: sola lettura |
| `tenant_ids` | int[] \| null | `null` = tutti i clienti |
| `expires_at` | data futura | |

Un token può creare solo token **non più ampi di se stesso**. I permessi e i
clienti richiesti devono essere un sottoinsieme dei suoi, altrimenti la risposta
è `422`.

---

## Valori ammessi

| Campo | Valori |
|---|---|
| Tipo di apparato (`type`) | `switch`, `router`, `firewall`, `access_point`, `controller`, `media_converter`, `patch_panel`, `wall_outlet`, `server`, `nas`, `hypervisor`, `virtual_machine`, `ups`, `pdu`, `kvm`, `phone_system`, `intercom`, `access_control`, `nvr`, `camera`, `computer`, `notebook`, `tv`, `printer`, `iot_device`, `other` |
| Stato apparato | `active`, `inactive`, `maintenance`, `decommissioned` |
| Tipo di interfaccia | `ethernet`, `fiber`, `wireless`, `console`, `management`, `power`, `keystone`, `virtual`, `loopback` |
| Supporto interfaccia (`media`) | `copper`, `fiber`, `wireless`, `virtual` |
| Stato interfaccia | `up`, `down`, `admin_down`, `unknown` |
| PoE | `none`, `pse`, `pd` |
| Modalità VLAN | `none`, `access`, `trunk`, `hybrid`, `transparent` |
| Stato connessione | `active`, `planned`, `decommissioned` |
| Protocollo VPN | `wireguard`, `openvpn`, `ipsec`, `l2tp`, `pptp`, `ssl_vpn`, `gre`, `other` |
| Instradamento VPN remote access | `routed`, `bridged` |

## Esempio completo

Un token con i permessi `tenants:read`, `equipment:read`, `equipment:create`,
`interfaces:create` e `wifi:*` crea un access point, gli aggiunge la radio e
pubblica una rete Wi-Fi:

```bash
TOKEN="1|abc..."
BASE="https://trama.example.com/api/v1"
H=(-H "Authorization: Bearer $TOKEN" -H "Accept: application/json" -H "Content-Type: application/json")

# 1. Quale cliente?
curl -s "${H[@]}" "$BASE/tenants"
TENANT=3

# 2. Access point
AP=$(curl -s "${H[@]}" -H "X-Tenant-Id: $TENANT" -d '{"name":"AP-PIANO1","type":"access_point"}' \
  "$BASE/equipment" | jq .data.id)

# 3. Interfaccia radio
RADIO=$(curl -s "${H[@]}" -H "X-Tenant-Id: $TENANT" \
  -d "{\"equipment_id\":$AP,\"name\":\"wlan0\",\"type\":\"wireless\",\"media\":\"wireless\",\"status\":\"up\",\"poe\":\"none\"}" \
  "$BASE/equipment/$AP/interfaces" | jq .data.id)

# 4. Rete Wi-Fi trasmessa dall'AP
curl -s "${H[@]}" -H "X-Tenant-Id: $TENANT" \
  -d "{\"ssid\":\"Ufficio\",\"security_type\":\"wpa3\",\"vlan_id\":20,\"broadcaster_interface_ids\":[$RADIO]}" \
  "$BASE/wifi-networks"
```

## Swagger / OpenAPI

L'interfaccia Swagger è disponibile su `/api/documentation`, generata da
`darkaonline/l5-swagger` con `php artisan l5-swagger:generate`. Al momento le
annotazioni OpenAPI coprono solo una parte degli endpoint: questo documento è il
riferimento completo.
