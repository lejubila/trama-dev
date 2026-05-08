# Da dare a Claude Code come PRIMO messaggio

Copia incolla questo prompt come prima istruzione a Claude Code una volta aperto il progetto:

---

```
Ciao! Stiamo per costruire Trama Network, un'applicazione Laravel + Livewire per la gestione
di impianti di rete multi-cliente.

Per prima cosa, leggi attentamente questi file in questo ordine:
1. CLAUDE.md (panoramica completa, stack, architettura, regole di lavoro)
2. README.md (quick start)
3. docs/BRAND.md (nome, palette, tono di voce)
4. docs/DATA_MODEL.md (schema DB dettagliato)
5. docs/UI_UX.md (wireframe e flussi UI)
6. docs/PHASE_00_SETUP.md (la fase con cui inizieremo)

Poi fai un breve recap a parole tue di:
- cosa stiamo costruendo
- lo stack scelto
- la roadmap a fasi
- cosa farai nella FASE 0

Quando sei pronto, procedi con la FASE 0 seguendo passo passo i task elencati in
PHASE_00_SETUP.md. Fermati al "Definition of Done" della fase per chiedermi conferma
prima di proseguire alla fase successiva.

Regole di lavoro:
- Una fase alla volta. Stop e conferma prima di passare alla successiva.
- Test obbligatori prima di dichiarare fatta una fase.
- Commit atomici con messaggi convenzionali.
- Mai bypassare il tenant scope.
- Se qualcosa non è chiaro nella documentazione, chiedimi.

Inizia.
```

---

## Suggerimenti per le interazioni successive

Tra una fase e l'altra, tipicamente dirai:

> "Procedi alla FASE 1."

oppure se vuoi rivedere prima:

> "Mostrami un riassunto di cosa hai fatto in FASE 0 e poi procedi con la FASE 1."

Se Claude Code prende una direzione che non ti convince:

> "Stop. Riguardiamo l'approccio sul punto X. Secondo te le alternative sono Y o Z?"

Se vuoi aggiungere requisiti emersi in corso d'opera, **NON modificare le fasi già completate**.
Aggiorna `CLAUDE.md` e/o crea un `docs/PHASE_XX_extras.md` da pianificare per dopo.
