# Trama Network — Logo

Pacchetto completo del logo per l'applicazione web Trama Network. Include la versione vettoriale master, una versione ottimizzata per favicon, una monocromatica, una orizzontale con testo, e i file PNG/ICO pronti all'uso.

## Contenuto del pacchetto

### SVG vettoriali (sorgenti)

| File | Uso |
|------|-----|
| `logo.svg` | Logo master 256×256 — usalo per qualsiasi dimensione ≥ 64 px |
| `logo_favicon.svg` | Versione ottimizzata 64×64 — tratti relativamente più spessi, ottima per 16-48 px |
| `logo_mono.svg` | Versione monocromatica grigio scuro — per stati disabled, stampa, watermark |
| `logo_horizontal.svg` | Logo + testo "Trama Network" affiancato — per header app |

### PNG (bitmap già renderizzati)

| File | Dimensione | Uso |
|------|------------|-----|
| `favicon_16.png` | 16×16 | Favicon tab browser |
| `favicon_32.png` | 32×32 | Favicon retina, bookmark |
| `favicon_48.png` | 48×48 | Windows taskbar |
| `favicon_64.png` | 64×64 | Apple touch icon piccola |
| `favicon_128.png` | 128×128 | Apple touch icon |
| `favicon_256.png` | 256×256 | Apple touch icon retina, PWA |
| `favicon_512.png` | 512×512 | PWA splash, social sharing |
| `logo_512.png` | 512×512 | Stesso del favicon_512 (alias) |
| `logo_1024.png` | 1024×1024 | App store, marketing, alta risoluzione |
| `logo_horizontal.png` | 1520×400 | Logo+testo bitmap (header, email signature) |

### Altri formati

| File | Uso |
|------|-----|
| `favicon.ico` | Multi-size (16+32+48) per compatibilità con browser legacy |
| `logo_preview.html` | Pagina di anteprima e test del logo |

## Integrazione nell'app web (Claude Code)

### 1. Copia i file nella tua app

Suggerimento per app React/Vite/Next.js: metti tutti i file in `public/` (verranno serviti come asset statici).

```
public/
├── logo.svg
├── logo_favicon.svg
├── logo_mono.svg
├── logo_horizontal.svg
├── favicon.ico
├── favicon_16.png
├── favicon_32.png
├── favicon_192.png    (rinomina favicon_128.png se ti serve)
└── favicon_512.png
```

### 2. Configura il `<head>` HTML

Inserisci questi tag in `index.html` (Vite/CRA) o `_document.tsx` (Next.js):

```html
<!-- Favicon principale (SVG con fallback) -->
<link rel="icon" type="image/svg+xml" href="/logo_favicon.svg">
<link rel="icon" type="image/png" sizes="32x32" href="/favicon_32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/favicon_16.png">
<link rel="shortcut icon" href="/favicon.ico">

<!-- Apple touch icon -->
<link rel="apple-touch-icon" sizes="256x256" href="/favicon_256.png">

<!-- Title -->
<title>Trama Network</title>
```

### 3. Usa il logo nei componenti React

```jsx
// Logo solo icona (sidebar, header compatto)
<img src="/logo.svg" alt="Trama Network" width="40" height="40" />

// Logo orizzontale (header pieno)
<img src="/logo_horizontal.svg" alt="Trama Network" style={{ height: 40 }} />

// Componente riutilizzabile
function Logo({ size = 40, horizontal = false }) {
  return (
    <img
      src={horizontal ? "/logo_horizontal.svg" : "/logo.svg"}
      alt="Trama Network"
      style={{ height: size, width: horizontal ? 'auto' : size }}
    />
  );
}
```

### 4. PWA manifest (opzionale)

Se vuoi che l'app sia installabile come PWA, aggiungi un `manifest.json` in `public/`:

```json
{
  "name": "Trama Network",
  "short_name": "Trama",
  "icons": [
    { "src": "/favicon_128.png", "sizes": "128x128", "type": "image/png" },
    { "src": "/favicon_256.png", "sizes": "256x256", "type": "image/png" },
    { "src": "/favicon_512.png", "sizes": "512x512", "type": "image/png" }
  ],
  "theme_color": "#1565C0",
  "background_color": "#F1EFE8",
  "display": "standalone"
}
```

E nel `<head>`:

```html
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#1565C0">
```

## Palette colori del logo

Il logo utilizza 3 colori saturi più una palette grigia coerente con il set icone:

| Colore | Hex | Uso nel logo |
|--------|-----|--------------|
| Blu | `#1565C0` | Linea inferiore (orizzontale) |
| Rosso | `#C62828` | Linea sinistra (diagonale) |
| Viola/Indaco | `#5E35B1` | Linea destra (diagonale) |
| Grigio chiaro | `#E8E6DE` | Top faccia dei nodi |
| Grigio medio | `#B4B2A9` | Fronte dei nodi |
| Grigio scuro | `#888780` | Lato dei nodi |
| Nero soft | `#2C2C2A` | Contorni |

Usa la stessa palette per coerenza visiva nell'intera app.

## Anteprima

Apri `logo_preview.html` in un browser per vedere il logo in vari contesti (favicon nel tab, sidebar scura, dimensioni a confronto) e copiare il codice di integrazione.
