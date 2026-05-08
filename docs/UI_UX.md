# UI / UX — wireframe testuali e flussi

## Layout globale

```
┌──────────────────────────────────────────────────────────────────┐
│ [Logo Trama Network]  [Selettore tenant▼]   Cerca…   [User▼] [Tema🌗] │
├──────────┬───────────────────────────────────────────────────────┤
│          │                                                       │
│  Sidebar │   Contenuto principale                                │
│          │                                                       │
│ Dashboard│                                                       │
│ Sedi     │                                                       │
│ Rack     │                                                       │
│ Disposit.│                                                       │
│ Conness. │                                                       │
│ Topologia│                                                       │
│ ────     │                                                       │
│ Utenti   │  (solo admin)                                         │
│ Audit    │                                                       │
│ Settings │                                                       │
│          │                                                       │
└──────────┴───────────────────────────────────────────────────────┘
```

Il **selettore tenant** è il primo elemento della topbar dopo il logo: l'utente sceglie il
cliente sul quale sta lavorando. Cambia `current_tenant_id` sull'utente e ricarica la pagina.

## Pagine principali

### 1. Dashboard tenant

```
┌─ Riepilogo cliente "ACME Spa" ─────────────────────────────────┐
│  [3] Sedi   [12] Rack   [87] Dispositivi   [134] Connessioni  │
└────────────────────────────────────────────────────────────────┘
┌─ Mappa sedi ───────────────────────┐ ┌─ Ultime modifiche ─────┐
│                                    │ │ 2h fa: agg. switch SW1 │
│  📍 Milano (5 rack, 30 dev)        │ │ 1d fa: nuovo rack R-B  │
│  📍 Torino (4 rack, 25 dev)        │ │ 2d fa: nuova conness.  │
│  📍 Roma   (3 rack, 32 dev)        │ │ ...                    │
└────────────────────────────────────┘ └────────────────────────┘
┌─ Salute impianto (banner) ─────────────────────────────────────┐
│ ⚠ 3 interfacce in stato "down" — clicca per dettagli           │
└────────────────────────────────────────────────────────────────┘
```

### 2. Lista Sedi

Tabella con: Nome, Indirizzo, # Rack, # Dispositivi, Azioni (Apri / Modifica / Elimina).
Bottone "Nuova sede" in alto a destra.

### 3. Dettaglio Sede

Tabs: **Locali** | **Mappa** | **Note**
- **Locali**: lista delle stanze con # rack
- **Mappa**: planimetria opzionale (V2 — per ora omettere)

### 4. Dettaglio Rack — VISTA RACK ELEVATION ⭐

```
┌─ Rack-A1 (42U, Locale CED — Sede Milano) ────────────────[…]──┐
│                                                                │
│ ┌──────────────┬────────────────────────────────────────────┐ │
│ │              │  42 ▒▒▒▒▒▒▒▒▒▒ (vuoto)                    │ │
│ │   FRONT      │  41 ▒▒▒▒▒▒▒▒▒▒                            │ │
│ │              │  40 ┌──────────────────────────────────┐  │ │
│ │   [SVG       │  39 │  CORE-SW1   Cisco Catalyst 9300  │  │ │
│ │    elevation │  38 │  [████████████████████████████]  │  │ │
│ │    interactive] │  37 └──────────────────────────────────┘ │ │
│ │              │  36 ┌──────────────────────────────────┐  │ │
│ │              │  35 │  PP-A1  Patch Panel 24p Cat6     │  │ │
│ │              │  34 └──────────────────────────────────┘  │ │
│ │              │  ... (altre U)                            │ │
│ │              │   2 ┌──────────────────────────────────┐  │ │
│ │              │   1 │  UPS-A1  APC Smart-UPS 1500VA    │  │ │
│ │              │     └──────────────────────────────────┘  │ │
│ └──────────────┴────────────────────────────────────────────┘ │
│                                                                │
│ [+ Aggiungi dispositivo]   [Vista posteriore]  [Esporta PDF] │
└────────────────────────────────────────────────────────────────┘
```

**Comportamento:**
- Ogni dispositivo è un rettangolo SVG cliccabile, alto N U, colorato per tipo.
- Hover → tooltip con vendor/model/serial.
- Click → apre **drawer laterale** con dettaglio dispositivo (tab Generale / Interfacce / Connessioni / Note).
- Drag verticale → sposta il dispositivo a un'altra U (verifica conflitti via Livewire).
- Gli slot vuoti hanno un piccolo "+" che apre il modale di creazione preimpostato a quella U.
- Bottone "Vista posteriore" per dispositivi con due lati (es. switch core con porte rear).

### 5. Drawer dettaglio Equipment

Tabs:
- **Generale**: tutti i campi del model con form Livewire inline.
- **Interfacce**: tabella delle porte con stato, VLAN, IP. Bottone "+ Nuova interfaccia".
  Click su interfaccia → modale di edit. Click su "Connetti" → wizard di connessione.
- **Connessioni**: tabella con interfaccia locale → interfaccia remota (link cliccabile per saltare al dispositivo dall'altra parte).
- **Audit**: storico delle modifiche.

### 6. Vista Topologica ⭐

```
┌─ Topologia — Sede Milano ──────────────────────────────────────┐
│ Filtri: [Tipo▼] [VLAN▼] [Stato▼]  Layout: [cose▼]  [🔍][↻][PNG]│
├────────────────────────────────────────────────────────────────┤
│                                                                │
│              ┌─────┐                                           │
│              │ FW1 │                                           │
│              └──┬──┘                                           │
│                 │ (1Gb fibra)                                  │
│              ┌──┴──┐                                           │
│              │ R1  │                                           │
│              └──┬──┘                                           │
│         ┌───────┼───────┐                                      │
│       ┌─┴─┐   ┌─┴─┐   ┌─┴─┐                                   │
│       │SW1│   │SW2│   │SW3│                                   │
│       └─┬─┘   └─┬─┘   └─┬─┘                                   │
│         │       │       │                                      │
│       ┌─┴─┐   ┌─┴─┐   ┌─┴─┐                                   │
│       │AP1│   │AP2│   │AP3│                                   │
│       └───┘   └───┘   └───┘                                   │
│                                                                │
│  Mini-map (in basso a destra)                                  │
└────────────────────────────────────────────────────────────────┘
```

**Comportamento (Cytoscape.js):**
- Nodi con icona SVG specifica per `EquipmentType` (router, switch, FW, AP, ...).
- Edge con stile per media: rame solido grigio, fibra solida arancione, wireless tratteggiato blu.
- Click nodo → highlight delle connessioni dirette + drawer dettaglio.
- Doppio click → naviga al rack dove il dispositivo è montato (vista rack).
- Click su edge → mostra dettaglio cavo (tipo, lunghezza, etichetta).
- Pulsanti zoom +/-, fit-to-screen, reset.
- Layout switcher: `cose-bilkent` (default), `dagre` (gerarchico top-down), `breadthfirst`, `circle`, `grid`.
- Filtri toggle: nascondono nodi/archi senza riposizionarli (animazione fade).
- Mini-map opzionale (extension `cytoscape-navigator`).
- "Esporta PNG" → `cy.png({full: true, scale: 2})` poi download.
- "Esporta PDF" → server-side via Browsershot della pagina con grafo già renderizzato.

### 7. Wizard creazione connessione

Step 1: scegli interfaccia A (autocomplete tipo "SW1 — Gi0/1")
Step 2: scegli interfaccia B (autocomplete, esclude interfacce già connesse)
Step 3: tipo cavo, lunghezza, etichetta, note
Conferma → crea record `connections`, redirect alla topologia con highlight del nuovo edge.

### 8. Lista Dispositivi (cross-rack)

Tabella sortabile/filtrabile. Filtri: tenant (auto), tipo, sede, rack, status, vendor.
Ricerca testuale full-text su name/model/serial.
Bulk actions: export CSV, change status, delete.

### 9. Import CSV

- Form upload CSV
- Preview prime 10 righe con mapping colonne → campi
- Validazione riga per riga, report errori
- Import in transazione, rollback se errori
- Modello CSV scaricabile

### 10. Audit log

Lista cronologica di tutte le modifiche con: data, utente, azione, modello, diff (vecchio→nuovo).
Filtri per utente, modello, periodo.

## Componenti riutilizzabili (Blade)

- `<x-tenant-selector />` — dropdown tenant in topbar
- `<x-rack-elevation :rack="$rack" />` — SVG rack elevation
- `<x-equipment-card :equipment="$eq" />` — card riassuntiva
- `<x-interface-row :interface="$if" />` — riga tabella interfaccia
- `<x-status-badge :status="$status" />` — badge colorato
- `<x-icon-equipment :type="$type" />` — icona SVG per tipo
- `<x-data-table />` — wrapper tabella con sort/filter standard
- `<x-modal />`, `<x-drawer />`, `<x-button />` — primitive UI

## Theming

- Default light + dark mode (toggle)
- Palette base Tailwind: `slate` per UI, `indigo` accent
- Colore per `EquipmentType`:
  - switch: `cyan-600`
  - router: `violet-600`
  - firewall: `red-600`
  - access_point: `emerald-600`
  - controller: `amber-600`
  - patch_panel: `slate-500`
  - server: `blue-600`
  - ups/pdu: `yellow-600`
  - other: `gray-500`

## Responsiveness

- Desktop-first (è uno strumento da scrivania), ma layout fluido fino a tablet (768px).
- Su mobile mostriamo solo viste read-only (lista dispositivi, dettaglio).
- Editor rack e topologia richiedono viewport ≥ 1024px (avviso altrimenti).
