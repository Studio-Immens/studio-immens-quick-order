# Editor mappa sala (Disposizione) con piani, zone e tavoli

Data: 2026-11-18
Stato: approvato (design)
Ambito: plugin Free `studio-immens-quick-order` (solo frontend operatore)
Riferimenti: `2026-11-17-ordini-tavolo-sicuri-stile-stampe.md` (sessioni tavolo, badge autore, stampa reparti)

## 1. Obiettivi

1. Trasformare la vista "Disposizione" in un editor di sala usabile da desktop e tablet: zoom/pan, drag, resize, forme e sedie.
2. Rappresentare piu' piani (es. Piano terra, Primo piano) navigabili come pagine, ognuno con il proprio canvas.
3. Disegnare e gestire le zone (sale) dal frontend, con perimetro, colore e immagine di sfondo.
4. Gestire piani e zone dalla stessa interfaccia di modifica, senza passare dal backend WordPress.
5. Non regredire i flussi esistenti: tap su tavolo = finestra ordine, drag solo in modifica, stato live (colori, badge, totali).

## 2. Stato attuale rilevante

- `public/template/manage-tables.php:6` `SIQO_manage_table()` rende griglia e piano; le stesse card `.fo_table_cell` vengono spostate in `[data-plan-area]` (`:275` `setView`), quindi la mappa e' gia' una vista della griglia.
- Posizione salvata: `_siqo_plan_position` = `{x,y}` (`manage-tables.php:157`), size fissa da `data-plan-w="180"` / `data-plan-h="120"` (`:184`), drag con snap 10px e salvataggio immediato (`:386-448`).
- Endpoint esistente `siqo_save_table_layout` (`includes/class-siqo-management.php:882`): nonce `SIQO_insert_post_ajax_nonce`, gate `SIQO_Management::can('manage_tables')` (`:126`), salva solo `x/y`.
- Zone = taxonomy `siqo_zones` su `siqo_tables` (`public/studio-immens-quick-order-public.php:1162`), oggi solo etichetta stampata nella card (`manage-tables.php:196`).
- Stato tavolo: meta `status_table_<ID>` (`manage-tables.php:153`) + taxonomy `siqo_table_status`; classi colore `var(--fo-tab-1/2)`.
- Il fix UX appena rilasciato ha introdotto tap-per-aprire e soppressione del click post-drag (`manage-tables.php:363-384`): da preservare.

## 3. Modello dati

### 3.1 Tavolo (`siqo_tables`)

| Meta | Formato | Default |
|---|---|---|
| `_siqo_plan_position` | `{x,y,w,h}` interi px, snap 10 | `w=180,h=120` se assenti (retrocompatibile) |
| `_siqo_plan_shape` | `rect` \| `square` \| `round` | `rect` |
| `_siqo_plan_seats` | intero 0-20 | `0` (sedie nascoste) |
| termine `siqo_floors` | un solo termine | nessuno → piano virtuale "Piano terra" |

Le coordinate sono **relative al piano**, mai al viewport. Lo zoom non modifica mai i valori salvati.

### 3.2 Piano (taxonomy `siqo_floors`)

- Taxonomy non pubblica, associata a `siqo_tables`, gerarchica disattivata, creazione/rinomina/eliminazione anche dal frontend (Sezione 5).
- Ordine di visualizzazione = `menu_order` del term.
- Term meta `_siqo_floor_layout` = `{w,h}` del canvas (default `1200x800`).
- Il piano virtuale "Piano terra" non e' un term: rappresenta i tavoli senza `siqo_floors` e usa il canvas di default. Alla prima modifica di layout di quel piano viene creato il term reale.
- Eliminando un piano, le sue zone vengono eliminate solo dopo conferma esplicita (il dialog di spostamento riguarda i tavoli).

### 3.3 Zona (taxonomy `siqo_zones`)

| Meta | Formato | Note |
|---|---|---|
| `_siqo_zone_floor` | term id `siqo_floors` | una zona appartiene a un solo piano |
| `_siqo_zone_bounds` | `{x,y,w,h}` | assente = zona non disegnata |
| `_siqo_zone_bg` | attachment ID | opzionale |
| `_siqo_zone_color` | hex | fallback di sfondo |

Se una sala esiste su due piani si creano due termini distinti: comportamento prevedibile e nessuna ambiguita' di coordinate.

### 3.4 Canvas

- Dimensioni per piano in `_siqo_floor_layout`; il canvas cresce automaticamente se un elemento esce dal bordo (`w = max(x + w) + 40`), mai si riduce sotto il default.
- Nessuna migrazione dati: i tavoli esistenti senza `w/h` continuano a rendersi a 180x120 e senza sedie.

## 4. Endpoint e permessi

Tutti `wp_ajax_*`, nonce `SIQO_insert_post_ajax_nonce`, gate `SIQO_Management::can('manage_tables')` (403 altrimenti), risposta JSON uniforme `{success:true,data:...}` / `{success:false,message:'...'}` con status HTTP coerente.

| Azione | Payload | Esito |
|---|---|---|
| `siqo_save_table_layout` (esteso) | `floor_id`, `canvas{w,h}`, `tables[{id,x,y,w,h,shape,seats,zone_id}]`, `zones[{id,x,y,w,h}]` | salva tutto in una transazione logica; risponde `{saved, floor_id}` |
| `siqo_floor_create` | `name`, `copy_from` (opzionale) | crea term + layout; `copy_from` duplica solo il canvas |
| `siqo_floor_update` | `id`, `name`, `order` | rinomina e riordina |
| `siqo_floor_delete` | `id`, `move_to` (opzionale) | se ci sono tavoli e manca `move_to` → 409 con elenco tavoli; con `move_to` sposta i tavoli e le zone vengono eliminate |
| `siqo_zone_create` | `floor_id`, `name`, `x,y,w,h` | crea term, assegna piano e bounds |
| `siqo_zone_update` | `id`, `name?`, `color?`, `bg?` | aggiorna i meta indicati |
| `siqo_zone_delete` | `id` | elimina term; i tavoli restano senza zona |
| `siqo_zone_bg_upload` | `zone_id`, `file` | `media_handle_upload`; richiede `upload_files` **o** `manage_tables`; MIME immagine, max 2 MB |

Validazioni: `absint` su tutti gli id, whitelist su `shape`, clamp `seats` 0-20, dimensioni minime 80x60, posizioni clampate al canvas, `sanitize_text_field` sui nomi (non vuoti, max 60 char), verifica che il termine appartenga alla taxonomy corretta e che il tavolo sia `siqo_tables`; `zone_id` deve essere un termine `siqo_zones` con `_siqo_zone_floor` uguale al piano in salvataggio (altrimenti viene azzerato).

Il salvataggio valida l'intero payload prima di scrivere: se anche un solo elemento non e' valido l'endpoint risponde 400 senza toccare il database.

## 5. Interfaccia

### 5.1 Barra

`Griglia | Disposizione` (ultima vista in `localStorage`), `Modifica disposizione`, `Gestisci`, zoom `- 100% +` con `Adatta`, tab dei piani con contatori liberi/occupati.

### 5.2 Tab piani

- Nome + badge `liberi/occupati`; tab attiva evidenziata; scroll orizzontale su schermi stretti.
- Desktop: frecce `‹ ›` e `Alt+←/→`; touch: swipe orizzontale fuori dal canvas in modifica.
- Cambio piano = rimontaggio del canvas con transizione breve (fade + slide 12px), nessun reload; gli aggiornamenti live di stato restano attivi anche sui piani non visibili.
- Piano e zoom ricordati in `localStorage`; se il piano salvato non esiste piu' si ricade sul primo.

### 5.3 Pannello Gestisci

Due liste nella stessa barra, aperte da `Gestisci` (solo in modifica):

- **Piani**: `+ Nuovo piano`, rinomina inline, `↑/↓`, elimina (con dialog di destinazione tavoli se non vuoto).
- **Zone del piano corrente**: `+ Nuova zona` (crea e centra un rettangolo da 240x160 da posizionare), rinomina inline, colore, sfondo (upload o scelta da media library), elimina con conferma.

### 5.4 Ispettori

- **Tavolo** (click in modifica): forma, sedie `- N +`, zona (select). Nessuna eliminazione tavolo.
- **Zona** (click sul corpo): nome, colore, sfondo, elimina.
- Su viewport < 1024px gli ispettori sono bottom-sheet; sopra, pannello flottante ancorato in alto a destra del canvas.

## 6. Interazioni

### 6.1 Zoom e pan

- Zoom 50-200% a passi 25%, via pulsanti, `Ctrl/⌘ + rotella`, pinch a due dita; `Adatta` calcola il fit del contenuto.
- Implementazione: `transform: scale()` su un wrapper interno; coordinate salvate sempre in px del piano (`clientX` viene riconvertito dividendo per lo zoom).
- Pan: drag su area vuota in modifica, barra spazio + drag da desktop, due dita su touch.

### 6.2 Drag, resize, forme

- Drag con snap 10px e clamp al canvas; se il centro del tavolo entra in una zona il tavolo cambia zona (evidenziazione della zona sotto).
- Handle `SE`, `E`, `S` (28px touch, 24px mouse); minimo 80x60; `square` e `round` bloccano le proporzioni (lato = min tra w/h); `rect` libero.
- Sedie ridisegnate a ogni resize: rettangolo → distribuite sui quattro lati; rotondo → sul cerchio; pallini 10px con `aria-hidden`.

### 6.3 Zone

- Disegno trascinando sull'area vuota in modifica → creazione immediata con nome inline (default `Zona N`), poi spostabile/ridimensionabile come un tavolo.
- Strato zone dietro i tavoli; sovrapposizioni ammesse, l'ultima disegnata sopra.
- Sfondo immagine con `background-size: cover`, opacita' regolabile via colore sovrapposto; senza immagine si usa `_siqo_zone_color`.

### 6.4 Tastiera e puntatori

- Frecce spostano l'elemento selezionato di 10px (`Shift` 50), `Esc` deseleziona, `Tab` cicla gli elementi, `Invio` sul tavolo apre l'ordine solo fuori modifica.
- Fuori modifica il comportamento attuale resta: tap/click apre la finestra ordine, drag disabilitato, click post-drag soppresso.

### 6.5 Regole per dispositivo

| Viewport / puntatore | Modifica | Note |
|---|---|---|
| >= 768px (desktop e tablet) | abilitata | `touch-action: none` sul canvas in modifica; handle touch 28px |
| < 768px (telefono) | nascosta (hint "Modifica da computer o tablet") | sola lettura con pinch-zoom e swipe piani |

## 7. Persistenza e stati

- Auto-save debounce 500ms a operazione conclusa (fine drag/resize, cambio ispettore, disegno/spostamento zona, creazione/rinomina piano o zona, upload sfondo). Cambio piano e cambio zoom **non** salvano.
- Indicatore in barra: `Salvataggio...` → `Salvato 21:04` → timeout 4s; su errore toast + rollback allo snapshot pre-operazione.
- `Ricarica disposizione` rilegge dal server e scarta le modifiche locali.
- Conflitti: se il salvataggio fallisce con 409 (piano eliminato da un'altra sessione) si ricarica l'elenco piani.

## 8. Accessibilita'

- Handle e ispettori con `role`/`aria-label` espliciti ("Ridimensiona tavolo 5", "Sposta zona Sala").
- Focus visibile su tutti i controlli; tastiera completa per spostamento e selezione.
- Zone come regioni etichettate (`role="group"`, `aria-label` = nome zona).
- Stato tavolo mai solo colore: testo `Libero/Occupato/Da incassare` nella card e nell'ispettore.
- Contrasto testo card >= 4.5:1 sui colori `--fo-tab-*` esistenti.

## 9. Errori e casi limite

| Caso | Comportamento |
|---|---|
| Elimina piano con tavoli | 409 + dialog "Sposta i tavoli su 〈piano〉" oppure annulla |
| Elimina zona con tavoli | conferma esplicita; i tavoli restano senza zona |
| Upload sfondo > 2 MB o MIME errato | rifiutato server-side con messaggio |
| Nonce scaduto / 403 | toast "Sessione scaduta, ricarica la pagina" |
| AJAX fallito | rollback allo snapshot + toast, nessuna scrittura parziale |
| Tavolo senza termine piano | compare in "Piano terra" virtuale |
| Zona senza `bounds` | non disegnata, tavoli liberi |

## 10. Fuori scope

- CRUD dei tavoli (creazione, rinomina, eliminazione) dalla mappa.
- Plan view pubblica/cliente e scelta tavolo dal menu.
- Rotazione libera dei tavoli, forme a L/bancone, immagini per singolo tavolo.
- Modifica della disposizione da telefono.

## 11. Test e criteri di accettazione

Nessuna infra di test: verifica con `php -l`, `node --check`, QA browser (Playwright MCP) a 390 / 820 / 1440 px.

1. 390px: Disposizione in sola lettura, swipe tra piani, tap apre ordine.
2. 820px: editing attivo, pinch-zoom, drag tavolo con snap, resize con handle da 28px, ispettore bottom-sheet.
3. 1440px: drag/resize/zoom/pan, frecce da tastiera, `Adatta`.
4. Persistenza: dopo reload posizione, forma, sedie e bounds coincidono; retrocompatibilita' con tavoli senza `w/h`.
5. Piani: crea, rinomina, riordina, elimina vuoto; elimina con tavoli → 409 e spostamento su altro piano.
6. Zone: disegno, rinomina, colore, sfondo, eliminazione con tavoli che restano.
7. Drag di un tavolo dentro una zona cambia zona e viene salvato.
8. Rollback: risposta AJAX forzata a errore → posizioni tornano allo stato precedente.
9. Baseline dati invariata: 34 ordini, 0 sessioni, 0 job di stampa, 0 stampanti.

## 12. Rischi

- **Peso DOM**: le card restano elementi reali; con centinaia di tavoli il transform puo' costare. Mitigazione: nessuna immagine per tavolo, sedie come nodi leggeri, `will-change` solo durante il drag.
- **Coordinate con zoom**: errore di riconversione porterebbe a salvataggi sfasati. Mitigazione: conversione in un'unica funzione `toPlanCoords()`, test dedicato con zoom 50% e 200%.
- **Zone e taxonomy esistente**: zone oggi usate come etichetta; il campo piano e' nuovo e i termini esistenti senza piano non vengono disegnati finche' non assegnati. Mitigazione: assegnazione automatica al piano corrente al primo salvataggio.
