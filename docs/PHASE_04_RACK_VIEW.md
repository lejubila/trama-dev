# FASE 4 — Vista Rack Elevation

> Obiettivo: ogni rack è disegnato come SVG verticale "rack elevation". L'utente vede tutti
> gli equipment posizionati alle U corrette, può cliccarli, riposizionarli, aggiungerne di nuovi.

## Componente principale

`App\Livewire\Racks\Elevation` — montato dentro `Racks\Show`.

**Stato Livewire:**
- `Rack $rack`
- Emette eventi: `equipment-clicked`, `equipment-moved`, `slot-clicked`

**View Blade:** `resources/views/livewire/racks/elevation.blade.php`

## Geometria SVG

```
Costanti (in resources/views/components/rack-elevation.blade.php):
  RACK_FRAME_WIDTH = 600 px (larghezza disegno)
  U_HEIGHT_PX      = 24  px (altezza di 1 U)
  PADDING_LEFT     = 60  px (per la numerazione U)
  PADDING_TOP      = 20
  
Altezza totale SVG: PADDING_TOP*2 + (rack.height_units * U_HEIGHT_PX)
```

Le U sono numerate dal basso (default `bottom_up`). Per `top_down` ribalta la numerazione
ma NON il rendering (il rack si disegna sempre con U1 in basso a meno che il flag dica altrimenti).

## Anatomia del disegno

```
┌─────────────────────────────────────┐  ← cornice rack
│ 42                                  │  ← numerazione + slot vuoto cliccabile (+)
│ 41                                  │
│ 40 ┌─────────────────────────────┐  │
│ 39 │  CORE-SW1  (Cisco C9300)    │  │  ← rettangolo equipment (4U)
│ 38 │  ●●●●●●●● ●●●●●●●● ●●●●     │  │     pallini = interfacce (con stato)
│ 37 └─────────────────────────────┘  │
│ ...
│  1 ┌─────────────────────────────┐  │
│    │  UPS-A1                     │  │
│    └─────────────────────────────┘  │
└─────────────────────────────────────┘
```

## Component Blade `<x-rack-elevation />`

Argomenti: `:rack`, `:interactive=true`.

**Render server-side** (no JS necessario per la vista base):
```blade
@props(['rack', 'interactive' => true])

@php
    $uPx = 24;
    $width = 600;
    $height = $rack->height_units * $uPx + 40;
    $equipment = $rack->equipment()->with('interfaces')->get();
@endphp

<svg viewBox="0 0 {{ $width }} {{ $height }}" class="rack-elevation">
    {{-- frame --}}
    <rect x="50" y="20" width="540" height="{{ $rack->height_units * $uPx }}" 
          fill="none" stroke="currentColor" stroke-width="2"/>

    {{-- U numbering + empty slot click handlers --}}
    @for ($u = 1; $u <= $rack->height_units; $u++)
        @php $y = 20 + ($rack->height_units - $u) * $uPx; @endphp
        <text x="40" y="{{ $y + 16 }}" text-anchor="end" class="u-label">{{ $u }}</text>
        @if ($interactive)
            <rect x="55" y="{{ $y }}" width="530" height="{{ $uPx }}"
                  class="u-slot" data-u="{{ $u }}"
                  wire:click="$dispatch('slot-clicked', { u: {{ $u }} })"/>
        @endif
    @endfor

    {{-- equipment rectangles --}}
    @foreach ($equipment as $eq)
        @php
            if (! $eq->mounted) continue;
            $startY = 20 + ($rack->height_units - $eq->position_u_start - $eq->position_u_height + 1) * $uPx;
            $h = $eq->position_u_height * $uPx;
            $color = $eq->type->color();
        @endphp
        <g class="equipment-block" 
           data-id="{{ $eq->id }}"
           wire:click="$dispatch('equipment-clicked', { id: {{ $eq->id }} })">
            <rect x="55" y="{{ $startY }}" width="530" height="{{ $h }}"
                  fill="rgb(var(--color-{{ $color }}-100))" 
                  stroke="rgb(var(--color-{{ $color }}-600))" stroke-width="1.5"/>
            <text x="65" y="{{ $startY + 18 }}" class="eq-name">{{ $eq->name }}</text>
            <text x="65" y="{{ $startY + 36 }}" class="eq-meta">
                {{ $eq->vendor }} {{ $eq->model }}
            </text>
            {{-- mini-interfaces dots --}}
            @foreach ($eq->interfaces->take(24) as $i => $if)
                <circle cx="{{ 65 + $i * 12 }}" cy="{{ $startY + $h - 12 }}" r="3"
                        fill="{{ $if->status === 'up' ? 'green' : 'gray' }}"/>
            @endforeach
        </g>
    @endforeach
</svg>
```

## Drag & drop riposizionamento

Wrapper Alpine.js `x-data="rackDnD({ rackId: {{ $rack->id }} })"`:
- `mousedown` su `.equipment-block` → memorizza posizione iniziale e id
- `mousemove` → traccia posizione, calcola U candidata
- `mouseup` → se U candidata diversa e valida → chiama `Livewire.dispatch('moveEquipment', { id, newStartU })`
- Server: `RackElevation::moveEquipment($id, $newStartU)` → usa `RackPlacementService` per validare
  → aggiorna o aggiunge errore + toast

File: `resources/js/alpine/rack-dnd.js`

## Drawer dettaglio equipment

Quando si clicca un equipment:
- Si apre un drawer laterale (componente `Equipment\Drawer`) con i dati completi
- Il drawer è un componente Livewire indipendente, ascolta `equipment-clicked` via Livewire event
- Tab: Generale | Interfacce | Connessioni | Note | Audit

```php
// Equipment\Drawer
#[On('equipment-clicked')]
public function loadEquipment(int $id): void
{
    $this->equipment = Equipment::with('interfaces.connectionFrom', 'interfaces.connectionTo')->findOrFail($id);
    $this->open = true;
}
```

## Slot vuoto → wizard di aggiunta

Click su slot vuoto → emette `slot-clicked` con `u`. Il componente parent apre
`Equipment\Form` modal con `position_u_start = u`, `rack_id` precompilato, l'utente sceglie
solo `position_u_height` e i dati anagrafici.

## Vista posteriore

Toggle "Front / Rear". Filtra `equipment` per `position_orient`:
- Front view: mostra equipment senza `position_orient` o con `front`
- Rear view: mostra equipment con `position_orient = rear`

Patch panel passanti possono apparire da entrambi i lati.

## Mini-mappa room

(Bonus opzionale fase 4) Componente che disegna i rack della stanza dall'alto come rettangoli
con `position_x`/`position_y`. Click su rack → naviga al rack.

## Test

```php
it('renders rack elevation with all mounted equipment')
it('clicking empty slot dispatches slot-clicked event with U')
it('moving equipment to occupied U returns validation error')
it('moving equipment to free valid U updates position_u_start')
it('drawer loads correct equipment on click event')
```

## Definition of Done

- [ ] `<x-rack-elevation />` riusabile e responsive
- [ ] Drag & drop funzionante con feedback visivo (ghost durante drag, snap-to-U)
- [ ] Conflitti rilevati e mostrati come toast errore
- [ ] Drawer apre/chiude con tab funzionanti
- [ ] Numerazione U bottom_up e top_down entrambe corrette
- [ ] Pinning (lock equipment per non spostarlo per errore) — flag `locked` su equipment, gestire
- [ ] Test fase 4 verdi
- [ ] Commit: `feat: phase 4 — rack elevation view`

➡️ Procedi alla **FASE 5**.
