# Ordini tavolo sicuri, stile esteso, scontrini e stampa reparti

Data: 2026-11-17
Stato: approvato (design)
Ambito: plugin Free `studio-immens-quick-order` + Pro `studio-immens-quick-order-pro`
Riferimenti: `2026-11-14-quick-order-management-suite-design.md` (brand system, shell, dashboard, printers, receipts)

## 1. Obiettivi

1. Impedire ordini al tavolo non autorizzati ("furbetti") senza aumentare l'attrito per cliente e operatore.
2. Rendere le impostazioni di stile più complete e applicabili con preset.
3. Intestazioni scontrini precompilate e modificabili, per tipo documento.
4. Stampare le comande direttamente da "Ordini reparti" con collegamento reparto -> stampante configurabile da quella pagina.
5. Mostrare in "Ordini reparti" chi ha inserito l'ordine (cliente o operatore) con nome e immagine profilo.

## 2. Stato attuale rilevante

- `SIQO_create_post_QR_code()` (`public/studio-immens-quick-order-public.php:1822`) genera un QR che punta a `?p=<ID post tavolo>`: nessun token, tavoli enumerabili.
- `public/template/front-order-ajax.php:51` legge `tab_hs` ma non lo valida mai.
- `FO_submit_order_ajax` (`public/studio-immens-quick-order-public.php:3458`) e' registrato anche `nopriv`: il blocco login e' solo UI (`SIQO_soft_access_denied()`), non enforcement server.
- Gli ordini salvano gia' `created_via` (`menu_ajax` / `tab_ajax`), meta `user_id`, `_customer_user`, tavolo in `Table_cpt`: autore ricostruibile ma non normalizzato.
- `public/station-orders.php`: card ordine con stati per reparto (`_siqo_station_status`), AJAX `SIQO_station_orders` / `SIQO_station_update_status`. Manca autore e stampa.
- Pro `SIQO_Printers`: tabelle `siqo_printers`, `siqo_print_jobs`, coda cron `siqo_print_queue_event`, hook su `siqo_submit_order_ajax`. `SIQO_Print_Rules` mappa **categoria prodotto -> stampante** (non reparto).
- `SIQO_Management::can()`: `manage_tables` = worker, `manage_printers` = supervisor, `manage_settings` = manager.
- `management-settings.php` ha gia' `receipt_head` / `receipt_foot` in `SIQO_Management::get_brand()`: base da estendere.
- `admin/pages/style.php` usa `SIQO_new_style_setting('--siqo-*')` + `SIQO_customized_css()`.

## 3. Modello di sicurezza ordini al tavolo

Modello scelto: **sessione armata dall'operatore + primo ordine confermato + chiave tavolo casuale**.

### 3.1 Identificazione tavolo
- Nuovo post meta `_siqo_table_key` (12 caratteri random `[a-z0-9]`), generato alla creazione del tavolo e backfillato per i tavoli esistenti.
- QR tavolo -> `https://site/quick-menu/?t=<key>`.
- Resolver server-side: `siqo_resolve_table_key($key)` -> post `siqo_tables`; fallback legacy `?p=<ID>` accettato solo se il tavolo ha una sessione attiva (compatibilita' QR gia' stampati) e con redirect alla chiave.
- Nessun ID tavolo esposto nell'URL.

### 3.2 Sessione tavolo
Nuova tabella `{$wpdb->prefix}siqo_table_sessions`:

| colonna | note |
|---|---|
| id | PK |
| table_id | post ID tavolo |
| session_token | 32 hex, univoco per sessione |
| status | `open`, `awaiting_approval`, `closed`, `expired` |
| opened_by | user ID operatore |
| opened_via | `qr_scan` / `list` / `auto` |
| opened_at / expires_at / closed_at | datetime UTC |
| orders_count | contatore |
| last_order_at | datetime UTC |
| guest_label | nome cliente opzionale (facoltativo) |
| meta | JSON (ip_hash, user_agent_hash) |

- TTL default 150 minuti, filtrabile (`siqo_table_session_ttl`).
- Una sola sessione `open` per tavolo: aprirne una nuova chiude la precedente.
- Cron `siqo_table_session_gc` (orario): chiude/espelle sessioni scadute.
- Chiusura automatica: hook pagamento tavolo (`siqo_table_paid`), "Chiudi tutti i tavoli" esistente, chiusura manuale.

### 3.3 Apertura sessione (operatore, telefono/tablet)
Due vie, entrambe disponibili:
1. **Scansione QR tavolo** con la fotocamera del telefono: il QR, se l'utente e' un operatore loggato, apre `?t=<key>&arm=1`; pagina di conferma con un solo bottone grande "Apri tavolo 7".
2. **Lista tavoli** nella vista "Ordini reparti": tap su "Apri tavolo".

Entrambe richiedono `SIQO_Management::can('manage_tables')` + nonce. Feedback immediato: toast "Tavolo 7 attivo fino alle 22:40" e badge verde nella lista.

### 3.4 Enforcement server
- `FO_submit_order_ajax`: accetta solo se
  - esiste una sessione `open` o `awaiting_approval` per il tavolo risolto da `t`/`tab_id`;
  - `orders_count < 20`, ultimo ordine > 60s fa, totale sessione < 500 EUR (filtri `siqo_order_rate_limit`, `siqo_order_max_total`);
  - nonce valido.
  In caso contrario `403` con messaggio "Tavolo non attivo, chiama un operatore".
- Gli ordini inviati dal pannello operatore (`manage_tables`) restano permessi con `can('manage_tables')`, marcati `operator`.
- `SIQO_flash_tab_order_ajax` mantiene il comportamento attuale per l'operatore (non e' un canale cliente).
- Login cliente **non richiesto**: identita' = sessione tavolo. L'account resta opzionale (creazione al pagamento).

### 3.5 Approvazione
- Primo ordine della sessione: `_siqo_approval = pending`, il tavolo passa a `awaiting_approval`; in "Ordini reparti" la card mostra un banner "Da confermare" con due bottoni "Conferma" / "Rifiuta" (endpoint `siqo_approve_order`, cap `manage_tables`).
- Ordini successivi della stessa sessione: `auto_approved`.
- Rifiuto: ordine -> `cancelled` con nota, sessione torna `open`, contatore rate limit non scalato.
- Nella card non confermata non parte nessuna stampa di reparto (la stampa avviene alla conferma).

### 3.6 Tracciamento autore
Meta ordine normalizzati (scritti in `SIQO_flash_tab_order_ajax` e nel path operatore):
- `_siqo_inserted_by` (user ID, 0 se cliente guest)
- `_siqo_order_source` (`customer_session` | `operator`)
- `_siqo_session_id`
- `_siqo_approval` (`pending` | `auto_approved` | `approved` | `rejected`)

## 4. Impostazioni di stile estese

Ampliamento dei token in `admin/pages/style.php` + generazione CSS invariata.

Nuovi gruppi:
1. **Tipografia**: famiglia (system/serif/sans + Google Fonts opzionale), scala base, pesi titoli/corpo, altezza riga, spaziatura lettere.
2. **Forma e profondita'**: raggio base/grande, spessore bordi, ombra card, ombra overlay.
3. **Spaziatura e layout**: padding card, gap griglia, larghezza massima contenuto, densita' (`comfortable` / `compact`).
4. **Componenti**: bottone primario/secondario (raggio, padding, hover), badge, tab, input.
5. **Tema chiaro/scuro**: `auto` / `light` / `dark` con override manuale dei 3 colori di superficie.

Preset (una click, poi rifinibili): `Bistrot caldo`, `Minimal chiaro`, `Scuro elegante`.
Ogni preset scrive l'intero set di token; l'utente puo' sovrascrivere singoli valori (flag `_siqo_style_preset` + diff).
Anteprima live nel pannello tramite iframe `srcdoc` con le variabili applicate (nessuna chiamata server).

## 5. Intestazioni scontrini

Estensione di `SIQO_Management::get_brand()` / sezione `receipts`:

Campi per documento (`receipt`, `station_ticket`, `pre_bill`):
- `head_line_1..3` (default: nome locale, indirizzo, P.IVA/CF + telefono)
- `logo_id` (media attachment, default logo brand)
- `footer_text` (default legale + ringraziamento)
- `show_logo`, `show_vat`, `show_order_number`, `show_author`
- `paper_width` (58 / 80 mm)
- `receipt_prefix` + numerazione progressiva (opzione separata per tipo)

Migrazione: `receipt_head` / `receipt_foot` esistenti diventano i default di `receipt`.
Editor con anteprima (stessa resa HTML usata da `SIQO_Documents`), nessuna nuova dipendenza.

## 6. Stampa da "Ordini reparti"

### 6.1 Collegamento reparto -> stampante
- Term meta `_siqo_printer_id` sulla tassonomia `siqo_preparation_points`.
- Risoluzione stampante: reparto -> `_siqo_printer_id`; fallback `SIQO_Print_Rules` (categoria -> stampante); fallback nessuna stampa.
- Configurazione in due punti: sheet rapido nell'header di "Ordini reparti" (select reparto + select stampante) e sezione completa in `Impostazioni > Stampanti`. Cap: `manage_printers`.

### 6.2 Azione stampa
- Bottone "Stampa" su ogni card ordine (cap `manage_tables`, cosi' anche i worker possono stampare).
- Endpoint `siqo_station_print`: crea job in `siqo_print_jobs` per ogni reparto presente nell'ordine con stampante collegata; risposta con esito per reparto.
- Se Pro assente o nessuna stampante: fallback `window.print()` su template comanda dedicato (CSS `@media print`).
- Anti-duplicato: se esiste un job `pending|processing|done` per (ordine, reparto) nelle ultime 24h, il bottone diventa "Ristampa" e lo dichiara.
- Ordini `pending` di approvazione: stampa consentita solo dopo conferma (sezione 3.5).

## 7. Autore ordine in UI

Componente riutilizzabile `siqo_render_author_badge($order)`:
- avatar (`get_avatar_url`, 28px) + nome + tipo;
- cliente guest: icona tavolo + `Cliente (Tavolo 7)`;
- operatore: avatar reale + `Operatore: <nome>`;
- usato in: card "Ordini reparti", comanda stampata, scontrino (se `show_author`), dettaglio ordine in `gestione`.

## 8. Ripartizione Free / Pro

| Area | Free | Pro |
|---|---|---|
| Sessione tavolo, chiave QR, enforcement, approvazione | si | — |
| Tracciamento autore + badge | si | — |
| Stile esteso + preset | si | — |
| Intestazioni scontrini | si | — |
| Mapping reparto -> stampante, coda job | fallback browser print | si |
| Sheet rapido configurazione in Ordini reparti | si (rimanda a Pro se assente) | si |

## 9. File impattati

Free:
- `public/studio-immens-quick-order-public.php` (resolver chiave, enforcement submit, meta autore)
- `public/station-orders.php` (badge autore, bottone stampa, banner approvazione, sheet mapping)
- `public/template/front-order-ajax.php` (risoluzione `?t=`)
- `includes/class-siqo-meta-db.php` (nuova tabella, bump versione)
- `includes/class-siqo-table-sessions.php` (nuovo)
- `includes/class-siqo-documents.php` (autore, campi intestazione)
- `admin/pages/style.php`, `admin/pages/management-settings.php`
- `public/js/siqo-station-orders.js`, `public/css/siqo-station-orders.css`

Pro:
- `includes/class-siqo-printers.php` (endpoint stampa reparto, mapping term meta)
- `includes/class-siqo-print-rules.php` (fallback invariato)

## 10. Rischi e mitigazioni

| Rischio | Mitigazione |
|---|---|
| Operatore dimentica di chiudere i tavoli | chiusura su pagamento, "chiudi tutti", GC oraria, badge sessione in scadenza |
| Cliente non capisce "tavolo non attivo" | messaggio esplicito + bottone "Chiama operatore" (notifica in Ordini reparti) |
| QR gia' stampati non funzionanti | retrocompatibilita' `?p=` con sessione attiva + rigenerazione PDF QR per tavolo |
| Doppia stampa comande | dedupe (ordine, reparto) 24h |
| Preset sovrascrivono personalizzazioni | preset scrive token, diff utente preservato |
| Rate limit blocca ordini legittimi | soglie filtrabili, contatore per sessione non per utente |

## 11. QA manuale

1. Tavolo senza sessione: ordine da telefono -> 403 e nessun ordine creato.
2. Apertura da scansione QR operatore -> badge attivo, TTL corretto.
3. Apertura da lista -> idem.
4. Primo ordine -> in attesa, nessuna stampa; conferma -> stampa parte.
5. Secondo ordine -> auto, stampa immediata.
6. Due ordini in 30s -> il secondo bloccato con messaggio.
7. Pagamento tavolo -> sessione chiusa, nuovo ordine bloccato.
8. Rifiuto ordine -> ordine cancellato, sessione riaperta.
9. Badge autore corretto per cliente guest, cliente loggato, operatore.
10. Preset stile: applicazione, override singolo token, persistenza.
11. Scontrino: intestazione default da brand, modifica, anteprima, PDF/stampa.
12. Stampa reparto con Pro, senza Pro (fallback), ristampa (dedupe).
13. Ruoli: worker apre tavolo e stampa ma non configura stampanti; supervisor configura; manager tutto.
