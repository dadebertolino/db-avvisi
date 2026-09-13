# DB Avvisi

Bacheca avvisi interna per WordPress: ogni utente che ha effettuato l'accesso può **pubblicare avvisi con allegati**, tutti gli utenti loggati li **leggono dalla bacheca**, e gli amministratori hanno una pagina di **gestione con le statistiche di chi ha caricato cosa**.

Nato per il sito dell'**IIS Cigna-Baruffi-Garelli** di Mondovì, ma non contiene nulla di specifico per quella scuola: funziona su qualsiasi installazione WordPress.

- **Autore:** Davide Bertolino — [davidebertolino.it](https://www.davidebertolino.it)
- **Licenza:** GPL v2 o successiva
- **Requisiti:** WordPress 5.8+, PHP 7.4+
- **Niente registrazioni, niente nag, niente trucchi.**

## Cosa fa

Il plugin aggiunge un menu **Avvisi** in bacheca, subito sotto la voce Bacheca, con tre pagine:

1. **Tutti gli avvisi** — elenco a schede di ciò che è stato pubblicato, con ricerca, filtro per categoria, filtro "solo i miei" e apertura del singolo avviso. Visibile a **tutti gli utenti loggati**.
2. **Nuovo avviso** — form di pubblicazione con titolo, testo (editor), categoria, data di scadenza facoltativa e allegati multipli. Aperto per impostazione predefinita a **ogni utente loggato** (restringibile dalle Impostazioni); ciascuno può poi modificare o eliminare i propri avvisi.
3. **Gestione e statistiche** — riservata agli amministratori, con tre schede:
   - **Avvisi**: tabella di tutti gli avvisi (compresi nascosti e scaduti), filtri per stato/categoria/autore, azioni singole (nascondi, fissa in alto, modifica, elimina) e azioni di gruppo.
   - **Statistiche**: numeri generali, tabella *chi ha caricato cosa* (avvisi, allegati, spazio occupato, download, letture, ultimo avviso per ogni utente), andamento mensile, distribuzione per categoria, allegati più scaricati, avvisi più letti, esportazione CSV.
   - **Impostazioni**: chi può pubblicare, categorie, limiti sugli allegati, estensioni ammesse, avvisi per pagina, scadenza proposta, email di notifica.

In più, un widget **"Avvisi recenti"** nella home della bacheca mostra gli ultimi cinque avvisi a chi entra nel sito.

## Chi può fare cosa

| Azione | Chi |
|--------|-----|
| Vedere gli avvisi e scaricare gli allegati | Qualsiasi utente **loggato** |
| Pubblicare un avviso | Qualsiasi utente **loggato** (predefinito), oppure solo da Collaboratore / Autore / Editore in su, a scelta nelle **Impostazioni** |
| Modificare / eliminare un avviso | L'**autore** dell'avviso e gli amministratori |
| Nascondere, fissare in alto, azioni di gruppo | Solo **amministratori** |
| Statistiche, impostazioni, export CSV | Solo **amministratori** |

Chi non ha effettuato l'accesso non vede il menu, non raggiunge le pagine e non scarica gli allegati.

I permessi sono modificabili via filtro senza toccare il codice del plugin:

```php
// Esempio: consentire la pubblicazione solo a chi può scrivere articoli.
add_filter( 'dbav_can_create', function () {
	return current_user_can( 'edit_posts' );
} );

// Esempio: affidare la gestione a un ruolo diverso da amministratore.
add_filter( 'dbav_manage_capability', function () {
	return 'edit_others_posts';
} );
```

Filtri disponibili: `dbav_can_view`, `dbav_can_create`, `dbav_can_manage`, `dbav_can_edit`, `dbav_manage_capability`.
Azioni disponibili: `dbav_avviso_created`, `dbav_avviso_updated`, `dbav_avviso_deleted`.

## Allegati protetti

Gli allegati **non finiscono nella Libreria media**: vengono salvati in `wp-content/uploads/db-avvisi/AAAA/MM/` con un nome casuale, e si scaricano solo attraverso un endpoint che verifica l'accesso. Un link copiato e incollato fuori dal sito non funziona per chi non è loggato.

La cartella è protetta su tre fronti:

- `.htaccess` con `Require all denied` (Apache);
- `web.config` con `<deny users="*" />` (IIS);
- nome del file casuale di 24 caratteri, così l'indirizzo non è indovinabile.

**Su Nginx `.htaccess` viene ignorato.** La scheda Impostazioni esegue un controllo reale — prova a scaricare un file di prova dall'esterno — e segnala in rosso se la cartella risulta raggiungibile. In quel caso va aggiunta una regola lato server, per esempio:

```nginx
location ^~ /wp-content/uploads/db-avvisi/ {
	deny all;
	return 404;
}
```

Le estensioni eseguibili (`php`, `js`, `html`, `svg`, `exe`, `sh`…) sono **sempre** rifiutate, anche se qualcuno le aggiunge a mano nell'elenco delle estensioni ammesse.

## Installazione

1. Scarica lo ZIP del plugin.
2. In WordPress: **Plugin → Aggiungi nuovo → Carica plugin** e seleziona lo ZIP.
3. Attiva. Le tabelle e la cartella protetta vengono create in automatico.
4. Facoltativo: apri **Avvisi → Gestione e statistiche → Impostazioni** per adattare categorie e limiti.

Gli aggiornamenti successivi arrivano da soli nel pannello plugin tramite GitHub Releases.

## Dati e privacy

- Il plugin registra l'**autore** di ogni avviso (ID utente WordPress), il testo, gli allegati, il numero di letture dell'avviso e il numero di download di ogni allegato.
- Le letture e i download sono **contatori aggregati**: non viene memorizzato chi ha letto o chi ha scaricato, né alcun indirizzo IP.
- Non ci sono servizi esterni, CDN, tracker o chiamate di rete, salvo il controllo aggiornamenti verso GitHub e il test di protezione della cartella allegati (una richiesta al proprio sito).
- La disinstallazione dal pannello plugin rimuove tabelle, opzioni e **tutti** gli allegati caricati.

## Struttura

```
db-avvisi/
├── db-avvisi.php               Bootstrap, costanti, funzioni di permesso
├── uninstall.php               Pulizia completa alla disinstallazione
├── inc/
│   ├── class-settings.php      Opzioni e loro sanificazione
│   ├── class-db.php            Tabelle e query
│   ├── class-files.php         Upload, protezione cartella, download controllato
│   ├── class-stats.php         Aggregazioni ed export CSV
│   ├── class-actions.php       Handler admin-post (salva, elimina, modera)
│   ├── class-admin.php         Menu, asset, rendering pagine
│   ├── class-dashboard-widget.php
│   └── class-updater.php       Aggiornamenti da GitHub Releases
├── templates/admin/            list, single, form, manage, stats, settings
└── assets/                     db-admin-ui.css + CSS/JS del plugin
```

Tabelle create: `wp_dbav_avvisi` e `wp_dbav_files`.

## Accessibilità

Etichette esplicite su ogni campo, `screen-reader-text` dove l'etichetta è visivamente ridondante, `aria-label` sui pulsanti a sola icona, focus visibile ereditato dal design system, nessun contenuto che si muove da solo. La tabella di gestione resta navigabile da tastiera e le conferme di eliminazione sono dialoghi nativi del browser.

## Changelog

### 1.1.0
- Nuova impostazione **Chi può pubblicare**: tutti gli utenti loggati (predefinito, come prima), oppure solo da Collaboratore, Autore o Editore in su.
- Sicurezza: il filtro `dbav_can_view` ora vale anche per il download degli allegati (prima un utente loggato senza permesso poteva comunque scaricarli).
- Statistiche: corretto l'andamento mensile, che in alcuni giorni di fine mese saltava il mese corrente e mostrava un mese futuro.

### 1.0.0
- Prima versione: elenco avvisi, pubblicazione con allegati, vista singola, area di gestione con moderazione, statistiche per autore ed export CSV, widget in bacheca, allegati protetti fuori dalla Libreria media, aggiornamenti da GitHub.
