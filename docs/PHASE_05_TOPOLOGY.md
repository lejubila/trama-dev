# FASE 5 — Vista Topologica (Cytoscape.js)

> Obiettivo: pagina interattiva che mostra la rete come grafo. Pan, zoom, layout switching,
> filtri, click per drill-down.

## Stack JS

Da installare via npm:
```bash
docker compose exec app npm install \
  cytoscape \
  cytoscape-cose-bilkent \
  cytoscape-dagre \
  cytoscape-navigator \
  cytoscape-popper \
  @popperjs/core
```

Importali in `resources/js/topology.js` e registra le estensioni in `app.js`.

## Endpoint dati

`App\Livewire\Topology\Graph` esporta:
- Metodo Livewire `getGraphData()` che restituisce JSON Cytoscape:
```json
{
  "nodes": [
    {
      "data": {
        "id": "eq-12",
        "label": "CORE-SW1",
        "type": "switch",
        "vendor": "Cisco",
        "model": "C9300",
        "siteId": 1,
        "rackId": 3,
        "status": "active"
      }
    }
  ],
  "edges": [
    {
      "data": {
        "id": "conn-45",
        "source": "eq-12",
        "target": "eq-7",
        "fromIface": "Gi0/1",
        "toIface": "Gi1/24",
        "media": "fiber",
        "speed": 10000,
        "status": "active",
        "label": "10G fibra"
      }
    }
  ]
}
```

Costruito da `App\Services\TopologyService::buildGraph(?int $siteId)`:
- Query `Equipment::with('interfaces.connectionFrom', 'interfaces.connectionTo')`
- Per ogni connection (deduplicata per id) → 1 edge

## Componente Livewire `Topology\Graph`

```php
namespace App\Livewire\Topology;

use App\Services\TopologyService;
use Livewire\Component;

class Graph extends Component
{
    public ?int $siteId = null;
    public string $layout = 'cose-bilkent';
    public array $filterTypes = [];
    public ?int $filterVlan = null;
    public ?string $filterStatus = null;

    public function getGraphData(TopologyService $svc): array
    {
        return $svc->buildGraph(
            siteId: $this->siteId,
            types: $this->filterTypes,
            vlan: $this->filterVlan,
            status: $this->filterStatus,
        );
    }

    public function render()
    {
        return view('livewire.topology.graph');
    }
}
```

## View Blade + Alpine

`resources/views/livewire/topology/graph.blade.php`:

```blade
<div class="h-full flex flex-col">
    <div class="toolbar flex gap-2 p-2 bg-slate-100 dark:bg-slate-800">
        {{-- Site selector --}}
        <select wire:model.live="siteId" class="...">
            <option value="">Tutte le sedi</option>
            @foreach ($sites as $s)
                <option value="{{ $s->id }}">{{ $s->name }}</option>
            @endforeach
        </select>

        {{-- Type filter --}}
        <select wire:model.live="filterTypes" multiple class="...">
            @foreach (\App\Enums\EquipmentType::cases() as $t)
                <option value="{{ $t->value }}">{{ $t->label() }}</option>
            @endforeach
        </select>

        {{-- Layout selector --}}
        <select wire:model.live="layout" class="...">
            <option value="cose-bilkent">Force-directed</option>
            <option value="dagre">Gerarchico</option>
            <option value="breadthfirst">Albero</option>
            <option value="circle">Circolare</option>
            <option value="grid">Griglia</option>
        </select>

        <button x-on:click="cy.fit()" class="...">⛶ Fit</button>
        <button x-on:click="cy.zoom(cy.zoom() * 1.2)" class="...">+</button>
        <button x-on:click="cy.zoom(cy.zoom() / 1.2)" class="...">−</button>
        <button x-on:click="exportPNG()" class="...">PNG</button>
    </div>

    <div id="cy" class="flex-1"
         x-data="topologyGraph({
            initial: $wire.getGraphData(),
            layout: '{{ $layout }}',
         })"
         x-init="init()"
         wire:ignore>
    </div>
</div>
```

`wire:ignore` è **fondamentale**: impedisce a Livewire di toccare il DOM gestito da Cytoscape.

## Alpine factory `topologyGraph`

`resources/js/alpine/topology-graph.js`:

```js
import cytoscape from 'cytoscape';
import coseBilkent from 'cytoscape-cose-bilkent';
import dagre from 'cytoscape-dagre';

cytoscape.use(coseBilkent);
cytoscape.use(dagre);

export default function topologyGraph({ initial, layout }) {
    return {
        cy: null,

        async init() {
            const data = await initial;  // Livewire returns a promise
            this.cy = cytoscape({
                container: this.$el,
                elements: [...data.nodes, ...data.edges],
                style: this.styleSheet(),
                layout: { name: layout, animate: true },
                wheelSensitivity: 0.2,
            });

            this.bindEvents();
            window.cy = this.cy; // expose for toolbar buttons (debug)
        },

        styleSheet() {
            return [
                {
                    selector: 'node',
                    style: {
                        'label': 'data(label)',
                        'background-image': 'data(iconUrl)',
                        'background-fit': 'contain',
                        'background-color': '#fff',
                        'border-width': 2,
                        'border-color': ele => this.colorForType(ele.data('type')),
                        'width': 60, 'height': 60,
                        'font-size': 11,
                        'text-valign': 'bottom',
                        'text-margin-y': 6,
                    }
                },
                {
                    selector: 'edge',
                    style: {
                        'line-color': ele => this.colorForMedia(ele.data('media')),
                        'line-style': ele => ele.data('media') === 'wireless' ? 'dashed' : 'solid',
                        'width': ele => Math.max(1, Math.log10(ele.data('speed') || 100) - 1),
                        'curve-style': 'bezier',
                        'target-arrow-shape': 'none',
                        'label': 'data(label)',
                        'font-size': 9,
                        'text-rotation': 'autorotate',
                    }
                },
                {
                    selector: 'node:selected',
                    style: { 'border-width': 4, 'border-color': '#6366f1' }
                },
                {
                    selector: 'edge:selected',
                    style: { 'line-color': '#6366f1', 'width': 4 }
                },
            ];
        },

        bindEvents() {
            this.cy.on('tap', 'node', evt => {
                const id = evt.target.data('id').replace('eq-', '');
                Livewire.dispatch('equipment-clicked', { id: parseInt(id) });
            });

            this.cy.on('dbltap', 'node', evt => {
                const rackId = evt.target.data('rackId');
                if (rackId) window.location.href = `/racks/${rackId}`;
            });

            this.cy.on('tap', 'edge', evt => {
                const id = evt.target.data('id').replace('conn-', '');
                Livewire.dispatch('connection-clicked', { id: parseInt(id) });
            });
        },

        colorForType(type) {
            const map = {
                switch: '#0891b2', router: '#7c3aed', firewall: '#dc2626',
                access_point: '#059669', controller: '#d97706',
                patch_panel: '#64748b', server: '#2563eb',
                ups: '#ca8a04', pdu: '#ca8a04',
            };
            return map[type] ?? '#6b7280';
        },

        colorForMedia(media) {
            return { copper: '#94a3b8', fiber: '#fb923c', wireless: '#3b82f6', virtual: '#a855f7' }[media] ?? '#94a3b8';
        },

        exportPNG() {
            const png = this.cy.png({ full: true, scale: 2, bg: '#fff' });
            const a = document.createElement('a');
            a.href = png; a.download = `topology-${Date.now()}.png`; a.click();
        }
    };
}
```

Registralo in `app.js`:
```js
import topologyGraph from './alpine/topology-graph';
window.topologyGraph = topologyGraph;
```

## Re-render su cambio layout/filtri

Quando Livewire aggiorna `$layout` o `$filterTypes`, Alpine deve:
- Rifetchare i dati (`$wire.getGraphData()`)
- Sostituire `cy.elements()` e rilanciare il layout

Hook con `$watch('$wire.layout', () => updateLayout())` dentro l'Alpine component.

## Highlight connessioni di un nodo

Click su nodo:
- Mostra connessioni dirette evidenziate
- Sfuma il resto: `cy.elements().not(node.closedNeighborhood()).addClass('faded')`
- Stile classe `faded`: `opacity: 0.2`

## Mini-mappa

Estensione `cytoscape-navigator`. Container HTML separato, posizionato bottom-right con `absolute`.

## Drill-down completo

Da pagina topologia:
- Click nodo → drawer con dettaglio (riusa `Equipment\Drawer` di fase 4)
- Doppio click → naviga alla vista rack del dispositivo
- Da drawer, click su interfaccia connessa → naviga al dispositivo dall'altra parte

## Performance

Per tenant grandi (>500 nodi):
- Lazy render: carica prima i nodi del livello "core" (router/firewall/switch core)
- Bottone "Espandi" per aggiungere AP, end-devices
- Disabilita label automatiche sotto un certo zoom level

## Test

```php
it('returns correct graph for current tenant')
it('filters nodes by equipment type')
it('filters by site')
it('does not include nodes from other tenants')
```

Test JS opzionali con Playwright (V2).

## Definition of Done

- [ ] Pagina `/topology` mostra grafo correttamente per il tenant corrente
- [ ] Pan/zoom fluidi
- [ ] Tutti i layout funzionano
- [ ] Filtri (sede, tipo, status) reattivi
- [ ] Click nodo → drawer dettaglio aperto
- [ ] Doppio click → naviga al rack
- [ ] Export PNG funziona
- [ ] Mini-mappa visibile
- [ ] Test verdi
- [ ] Commit: `feat: phase 5 — topology view`

➡️ Procedi alla **FASE 6**.
