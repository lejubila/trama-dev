# Trama Network — Set Icone

Set di **25 icone isometriche** per dispositivi di rete, pensate per essere utilizzate su planimetrie 2D e diagrammi di topologia.

## Contenuto

### Infrastruttura
| File | Oggetto | Note |
|------|---------|------|
| `rack.svg` | Rack (armadio di rete) | — |
| `patch_panel.svg` | Patch Panel | — |
| `presa_rete.svg` | Presa di rete a muro | Placca doppia con 2 porte RJ45 |

### Networking
| File | Oggetto | Simbolo |
|------|---------|---------|
| `switch.svg` | Switch | Frecce incrociate (forwarding) |
| `router.svg` | Router | Nuvola (Internet/WAN) |
| `access_point.svg` | Access Point | Onde radio concentriche |
| `controller.svg` | Controller | Ingranaggio (management) |
| `media_converter.svg` | Media Converter | Connettore fibra ↔ RJ45 |

### Security
| File | Oggetto | Simbolo |
|------|---------|---------|
| `firewall.svg` | Firewall | Fiamma |

### Compute & Storage
| File | Oggetto | Simbolo |
|------|---------|---------|
| `server.svg` | Server | — |
| `nas.svg` | NAS | Cilindri hard disk |
| `kvm.svg` | KVM | Mini-monitor |

### Power
| File | Oggetto | Simbolo |
|------|---------|---------|
| `ups.svg` | UPS | Fulmine giallo |
| `pdu.svg` | PDU | Presa elettrica |

### Videosorveglianza
| File | Oggetto | Simbolo |
|------|---------|---------|
| `telecamera.svg` | Telecamera bullet | Lente blu + cono visione |
| `nvr.svg` | NVR | REC rosso + play blu |

### Comunicazioni
| File | Oggetto | Simbolo |
|------|---------|---------|
| `centralino.svg` | Centralino telefonico | Cornetta orizzontale blu |
| `citofono.svg` | Citofono | Casa verde |

### Controllo Accessi
| File | Oggetto | Simbolo |
|------|---------|---------|
| `controllo_accessi.svg` | Terminale controllo accessi | Lucchetto giallo |

### Endpoint
| File | Oggetto | Simbolo |
|------|---------|---------|
| `computer.svg` | Computer (postazione) | Monitor + tastiera |
| `notebook.svg` | Notebook | Clamshell aperto con tastiera |
| `tv.svg` | TV | Schermo blu + piedistallo |
| `stampante.svg` | Stampante | Foglio in uscita |
| `iot.svg` | Dispositivo IoT | Chip viola + onde wireless |

### Altro
| File | Oggetto | Simbolo |
|------|---------|---------|
| `generica.svg` | Generica | Punto interrogativo |

## Specifiche tecniche

- **Formato**: SVG vettoriale
- **ViewBox**: 128 × 128 px
- **Stile**: isometrico (vista 3/4), tratto sottile, palette grigia con accenti di colore per simboli identificativi
- **Compatibilità**: tutti i moderni browser, software di design (Figma, Sketch, Inkscape, Adobe Illustrator), e qualsiasi framework web (React, Vue, Angular)

## Come usarle

### In HTML
```html
<img src="presa_rete.svg" alt="Presa di rete" width="48" height="48">
```

### In React (componente generico)

```jsx
const DEVICE_ICONS = {
  // Infrastruttura
  rack: '/icons/rack.svg',
  patch_panel: '/icons/patch_panel.svg',
  presa_rete: '/icons/presa_rete.svg',
  // Networking
  switch: '/icons/switch.svg',
  router: '/icons/router.svg',
  access_point: '/icons/access_point.svg',
  controller: '/icons/controller.svg',
  media_converter: '/icons/media_converter.svg',
  // Security
  firewall: '/icons/firewall.svg',
  // Compute & Storage
  server: '/icons/server.svg',
  nas: '/icons/nas.svg',
  kvm: '/icons/kvm.svg',
  // Power
  ups: '/icons/ups.svg',
  pdu: '/icons/pdu.svg',
  // Videosorveglianza
  telecamera: '/icons/telecamera.svg',
  nvr: '/icons/nvr.svg',
  // Comunicazioni
  centralino: '/icons/centralino.svg',
  citofono: '/icons/citofono.svg',
  // Controllo Accessi
  controllo_accessi: '/icons/controllo_accessi.svg',
  // Endpoint
  computer: '/icons/computer.svg',
  notebook: '/icons/notebook.svg',
  tv: '/icons/tv.svg',
  stampante: '/icons/stampante.svg',
  iot: '/icons/iot.svg',
  // Altro
  generica: '/icons/generica.svg',
};

function DeviceIcon({ type, size = 48 }) {
  const src = DEVICE_ICONS[type] || DEVICE_ICONS.generica;
  return <img src={src} alt={type} width={size} height={size} />;
}
```

## Anteprima

Apri `anteprima.html` in un browser per vedere tutte le icone affiancate, organizzate per categoria, con uno slider per testarne la leggibilità a diverse dimensioni (da 24 px a 160 px).

## Personalizzazione

Le icone sono SVG aperti:
- Cambia i colori modificando gli attributi `fill` e `stroke`
- Scala a qualsiasi dimensione senza perdita di qualità
- Aggiungi classi CSS per gestire stati (es. `.offline { opacity: 0.4; }`)
