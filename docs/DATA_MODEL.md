# Modello dati — riferimento dettagliato

> Tutte le tabelle (eccetto `users`, `tenants`, `password_reset_tokens`, `personal_access_tokens`,
> `sessions`, `failed_jobs`, `cache`, `jobs`, `audits` quando configurato globalmente)
> hanno una colonna `tenant_id` con foreign key e un global scope Eloquent.

## Convenzioni

- Chiavi primarie: `id` come `bigIncrements` (default Laravel).
- Timestamp: `created_at`, `updated_at` su ogni tabella di dominio.
- Soft delete attivo dove indicato (`deleted_at`).
- I campi enum sono memorizzati come `string` con check applicativo via PHP enum.
- I campi JSON usano il tipo `jsonb` di PostgreSQL.

---

## tenants
Cliente del system integrator.

| Campo       | Tipo            | Note                                       |
|-------------|-----------------|--------------------------------------------|
| id          | bigint PK       |                                            |
| name        | string(150)     | Nome cliente                               |
| slug        | string(80) UNQ  | Identificativo URL-safe                    |
| domain      | string nullable | Per future modalità multi-domain           |
| settings    | jsonb           | Preferenze tenant (logo, colori, ecc.)     |
| created_at  | timestamp       |                                            |
| updated_at  | timestamp       |                                            |

## users
Utente dell'applicazione (può appartenere a più tenant).

| Campo                | Tipo                 | Note                              |
|----------------------|----------------------|-----------------------------------|
| id                   | bigint PK            |                                   |
| name                 | string(150)          |                                   |
| email                | string UNQ           |                                   |
| email_verified_at    | timestamp nullable   |                                   |
| password             | string               | bcrypt                            |
| current_tenant_id    | bigint FK→tenants    | Tenant attivo nella sessione      |
| remember_token       | string nullable      |                                   |
| created_at/updated_at| timestamp            |                                   |

## tenant_user
Pivot utente↔tenant con ruolo.

| Campo      | Tipo                  | Note                                  |
|------------|-----------------------|---------------------------------------|
| tenant_id  | bigint FK             | PK composta (tenant_id, user_id)      |
| user_id    | bigint FK             |                                       |
| role       | string                | enum: admin / tecnico / cliente       |
| created_at | timestamp             |                                       |

> I permessi granulari arrivano da spatie/laravel-permission con team scope = tenant_id.

---

## sites
Sede fisica del cliente.

| Campo      | Tipo               | Note                            |
|------------|--------------------|---------------------------------|
| id         | bigint PK          |                                 |
| tenant_id  | bigint FK          | Indexed                         |
| name       | string(150)        |                                 |
| address    | string(255) nullable |                               |
| latitude   | decimal(10,7) nullable |                             |
| longitude  | decimal(10,7) nullable |                             |
| notes      | text nullable      |                                 |
| timestamps |                    |                                 |
| deleted_at | timestamp nullable | Soft delete                     |

## rooms
Locale tecnico / armadio dentro una sede.

| Campo      | Tipo               | Note                            |
|------------|--------------------|---------------------------------|
| id         | bigint PK          |                                 |
| tenant_id  | bigint FK          |                                 |
| site_id    | bigint FK          |                                 |
| name       | string(150)        |                                 |
| floor      | string(50) nullable| es. "Piano 1", "S1"             |
| notes      | text nullable      |                                 |
| timestamps |                    |                                 |
| deleted_at | timestamp nullable |                                 |

## racks
Rack fisico.

| Campo         | Tipo                | Note                                      |
|---------------|---------------------|-------------------------------------------|
| id            | bigint PK           |                                           |
| tenant_id     | bigint FK           |                                           |
| room_id       | bigint FK           |                                           |
| name          | string(100)         | es. "Rack-A1"                             |
| height_units  | smallint            | default 42                                |
| width_mm      | int nullable        | default 600                               |
| depth_mm      | int nullable        | default 1000                              |
| position_x    | decimal(8,2) nullable | Coord nella mappa stanza (cm)           |
| position_y    | decimal(8,2) nullable |                                         |
| numbering     | string              | enum: bottom_up (default), top_down       |
| notes         | text nullable       |                                           |
| timestamps    |                     |                                           |
| deleted_at    | timestamp nullable  |                                           |

---

## equipment
Qualsiasi apparato (switch, router, FW, AP, controller, server, patch panel, UPS, PDU, ecc.).

| Campo            | Tipo                 | Note                                                |
|------------------|----------------------|-----------------------------------------------------|
| id               | bigint PK            |                                                     |
| tenant_id        | bigint FK            | Indexed                                             |
| rack_id          | bigint FK nullable   | Null se non rack-mounted                            |
| name             | string(150)          | Hostname o etichetta                                |
| type             | string(30)           | EquipmentType enum                                  |
| vendor           | string(80) nullable  |                                                     |
| model            | string(120) nullable |                                                     |
| serial           | string(120) nullable |                                                     |
| firmware         | string(80) nullable  |                                                     |
| asset_tag        | string(80) nullable  | Inventario interno                                  |
| mounted          | boolean              | Default false                                       |
| position_u_start | smallint nullable    | U più bassa occupata (1-based)                      |
| position_u_height| smallint nullable    | Numero di U occupate                                |
| position_orient  | string(10) nullable  | front / rear (per equipment 0U)                     |
| status           | string(20)           | active / inactive / maintenance / decommissioned    |
| management_ip    | inet nullable        | IP di management                                    |
| description      | text nullable        |                                                     |
| custom_fields    | jsonb                | Dati specifici vendor                               |
| timestamps       |                      |                                                     |
| deleted_at       | timestamp nullable   |                                                     |

### EquipmentType (PHP enum)
`switch`, `router`, `firewall`, `access_point`, `controller`, `patch_panel`, `server`,
`ups`, `pdu`, `media_converter`, `nas`, `kvm`, `other`

### Vincoli applicativi
- Se `mounted = true`, allora `rack_id`, `position_u_start`, `position_u_height` obbligatori.
- Verifica overlap U dentro lo stesso rack.
- `position_u_start + position_u_height - 1 <= rack.height_units`.

---

## interfaces
Interfaccia di un equipment (porta fisica o logica).

> Nota: il model PHP si chiama `NetworkInterface` (perché `Interface` è keyword PHP).
> La tabella si chiama `interfaces`.

| Campo          | Tipo                | Note                                                  |
|----------------|---------------------|-------------------------------------------------------|
| id             | bigint PK           |                                                       |
| tenant_id      | bigint FK           |                                                       |
| equipment_id   | bigint FK           |                                                       |
| name           | string(80)          | es. "Gi0/1", "eth0", "Port 24", "WAN1"                |
| type           | string(20)          | InterfaceType enum                                    |
| index          | int                 | Ordine di rendering                                   |
| speed_mbps     | int nullable        | 100, 1000, 2500, 10000, 25000, 40000, 100000          |
| media          | string(20)          | copper / fiber / wireless / virtual                   |
| connector      | string(20) nullable | RJ45 / SFP / SFP+ / SFP28 / QSFP / QSFP28 / LC / SC   |
| vlan_mode      | string(20) nullable | none / access / trunk / hybrid                        |
| vlan_default   | smallint nullable   | VLAN di accesso o native                              |
| vlans_allowed  | jsonb nullable      | array di VLAN ID per trunk                            |
| ip_address     | string(45) nullable | IPv4/IPv6 con CIDR                                    |
| mac_address    | string(17) nullable | Formato AA:BB:CC:DD:EE:FF                             |
| status         | string(15)          | up / down / admin_down / unknown                      |
| poe            | string(10)          | none / pse / pd                                       |
| description    | string(255) nullable|                                                       |
| custom_fields  | jsonb               |                                                       |
| timestamps     |                     |                                                       |

UNIQUE: `(equipment_id, name)`

### InterfaceType
`ethernet`, `fiber`, `wireless`, `console`, `management`, `power`, `keystone`, `virtual`, `loopback`

---

## connections
Cavo fisico tra due interfacce.

| Campo               | Tipo                | Note                                          |
|---------------------|---------------------|-----------------------------------------------|
| id                  | bigint PK           |                                               |
| tenant_id           | bigint FK           |                                               |
| from_interface_id   | bigint FK           | UNIQUE (insieme a status='active')            |
| to_interface_id     | bigint FK           | UNIQUE (insieme a status='active')            |
| cable_type          | string(30)          | utp_cat6 / utp_cat6a / stp / fiber_om3 / ...  |
| cable_length_m      | decimal(6,2) nullable |                                             |
| cable_label         | string(80) nullable | Etichetta fisica del cavo                     |
| color               | string(20) nullable |                                               |
| status              | string(15)          | active / planned / decommissioned             |
| notes               | text nullable       |                                               |
| established_at      | date nullable       |                                               |
| timestamps          |                     |                                               |

### Vincoli applicativi
- `from_interface_id != to_interface_id`
- Le due interfacce devono appartenere allo stesso tenant
- Per `status=active`: una stessa interfaccia non può comparire in due connessioni
  (vincolo enforced via unique partial index PostgreSQL).

```sql
CREATE UNIQUE INDEX connections_from_active_unique
    ON connections (from_interface_id) WHERE status = 'active';
CREATE UNIQUE INDEX connections_to_active_unique
    ON connections (to_interface_id) WHERE status = 'active';
```

---

## link_groups
Aggregazione logica di connessioni (LACP / static LAG).

| Campo      | Tipo            | Note                                |
|------------|-----------------|-------------------------------------|
| id         | bigint PK       |                                     |
| tenant_id  | bigint FK       |                                     |
| name       | string(100)     | es. "Po1", "bond0"                  |
| mode       | string(15)      | lacp / static                       |
| notes      | text nullable   |                                     |
| timestamps |                 |                                     |

## link_group_connection (pivot)
| link_group_id | bigint FK |
| connection_id | bigint FK |

---

## tags
Tag polimorfico per equipment, connections, sites.

| Campo      | Tipo            | Note                |
|------------|-----------------|---------------------|
| id         | bigint PK       |                     |
| tenant_id  | bigint FK       |                     |
| name       | string(50)      |                     |
| color      | string(7)       | #RRGGBB             |
| timestamps |                 |                     |

UNIQUE: `(tenant_id, name)`

## taggables (pivot polimorfico)
| tag_id        | bigint FK              |
| taggable_id   | bigint                 |
| taggable_type | string                 |

---

## audits
Tabella gestita da `owen-it/laravel-auditing`. Schema standard del package; aggiungiamo
`tenant_id` come colonna extra in `auditable_meta`.

---

## Relazioni Eloquent — riassunto

```
Tenant 1—N Site
Site 1—N Room
Room 1—N Rack
Rack 1—N Equipment
Equipment 1—N NetworkInterface
NetworkInterface 1—1 Connection (as from)
NetworkInterface 1—1 Connection (as to)
LinkGroup N—N Connection (via link_group_connection)
Equipment N—N Tag (polimorfico via taggables)
Connection N—N Tag (polimorfico via taggables)
User N—N Tenant (via tenant_user, con campo role)
```
