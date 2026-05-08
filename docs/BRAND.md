# Trama Network — Brand & Identity

## Il nome

**Trama Network** (forma estesa) — abbreviabile in **Trama** nei contesti informali e nell'UI.

### Etimologia e significato

In italiano *trama* significa:
- L'insieme dei fili che, intrecciandosi con l'ordito, forma un tessuto.
- La struttura interna che tiene insieme un'opera (la trama di un romanzo, di un film).
- Per estensione: l'intelaiatura nascosta che fa funzionare un sistema.

Per un software che documenta e visualizza impianti di rete — un intreccio fisico di cavi, dispositivi
e connessioni che insieme formano un sistema funzionante — il nome è perfettamente calzante.

## Convenzioni d'uso

| Contesto                          | Forma                |
|-----------------------------------|----------------------|
| Titolo prodotto, marketing, brand | **Trama Network**    |
| UI (header, login, footer)        | **Trama**            |
| Email, comunicazioni              | **Trama Network**    |
| Codice (namespace, DB, container) | `trama` (lowercase)  |
| File / repository                 | `trama`              |
| Variabile `APP_NAME` in .env      | `Trama Network`      |
| Subject delle email               | `[Trama] ...`        |
| Path di storage e log             | `/storage/trama/...` |

## Tone of voice

- **Italiano** come lingua primaria dell'interfaccia (utenti italiani, system integrator italiani).
- Tono **professionale ma diretto**: niente formule pompose tipo "La invitiamo cortesemente a...",
  preferire "Conferma" / "Annulla" / "Salvato".
- **Chiarezza > brevità eccessiva**: meglio "Aggiungi un dispositivo al rack" che "Nuovo".
- **Niente gergo tecnico inutile** dove un termine comune basta. Ma rispetto del lessico del dominio:
  *patch panel*, *rack*, *VLAN*, *uplink*, *trunk* sono termini che si lasciano in inglese
  perché sono lessico standard del settore.

## Identità visiva (linee guida)

### Logo (proposta)
Un'idea per il logo: tre linee orizzontali parallele intrecciate da un filo verticale che le attraversa,
a evocare contemporaneamente:
- Le U di un rack (linee orizzontali)
- I cavi che corrono (filo verticale)
- L'intreccio di una trama tessile

Forma sintetica, monocromatica, riutilizzabile come favicon a 16×16.

### Palette colori

```
Primary (brand):        indigo-600   #4f46e5   ← accent principale, link, CTA
Primary dark:           indigo-800   #3730a3   ← hover, dark mode accent
Background light:       slate-50     #f8fafc
Background dark:        slate-900    #0f172a
Text light theme:       slate-900    #0f172a
Text dark theme:        slate-100    #f1f5f9
Border / dividers:      slate-200    #e2e8f0  (light) / slate-700 #334155 (dark)
Success:                emerald-600  #059669
Warning:                amber-500    #f59e0b
Danger:                 red-600      #dc2626
Info:                   sky-600      #0284c7
```

### Colori semantici per i tipi di dispositivo
(Già definiti negli enum, ribaditi qui per coerenza)

```
switch:           cyan-600     #0891b2
router:           violet-600   #7c3aed
firewall:         red-600      #dc2626
access_point:     emerald-600  #059669
controller:       amber-600    #d97706
patch_panel:      slate-500    #64748b
server:           blue-600     #2563eb
ups / pdu:        yellow-600   #ca8a04
media_converter:  fuchsia-600  #c026d3
nas:              teal-600     #0d9488
kvm:              orange-600   #ea580c
other:            gray-500     #6b7280
```

### Colori per i media di connessione

```
copper (rame):    slate-400    #94a3b8   solid
fiber (fibra):    orange-400   #fb923c   solid
wireless:         blue-500     #3b82f6   dashed
virtual:          purple-500   #a855f7   dotted
```

### Tipografia

- **UI**: Inter (Google Fonts), fallback `system-ui, -apple-system, sans-serif`
- **Monospace** (per IP, MAC, seriali): JetBrains Mono, fallback `ui-monospace, monospace`

### Iconografia

- **Heroicons** (outline + solid) per UI generale
- **Lucide** opzionalmente per varietà
- **Icone custom SVG** per i tipi di dispositivo (vedi `resources/svg/equipment/`)

## Asset path

```
resources/
├── images/
│   ├── logo.svg              # logo principale
│   ├── logo-mark.svg         # solo simbolo (favicon, app icon)
│   ├── logo-dark.svg         # variante dark mode
│   └── og-image.png          # social preview 1200×630
├── svg/
│   └── equipment/            # icone dispositivi per Cytoscape
│       ├── switch.svg
│       ├── router.svg
│       ├── firewall.svg
│       └── ...
```

## Slogan / tagline (proposte, per V1 marketing)

- "L'intreccio della tua rete, finalmente chiaro."
- "Documenta, visualizza, governa la tua infrastruttura di rete."
- "La rete che vedi, la rete che gestisci."

> Per la fase di sviluppo non sono richiesti — annotati qui come riferimento futuro.
