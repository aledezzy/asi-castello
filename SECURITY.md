## Security.md

### Project Work: Gestione di un sito di auto d'epoca

### Gruppo Castello

### Introduzione

Questo documento descrive le misure di sicurezza adottate per il progetto Club Auto d'Epoca


# Politica di Sicurezza per CLUB AUTO D'EPOCA

La sicurezza del progetto "CLUB AUTO D'EPOCA" è una priorità assoluta. Ci impegniamo a proteggere i dati dei nostri soci e utenti e a mantenere la piattaforma sicura. Questa pagina descrive le nostre pratiche di sicurezza e come segnalare eventuali vulnerabilità.

## Segnalazione di una Vulnerabilità

Apprezziamo l'aiuto della comunità nel mantenere sicuro il nostro progetto. Se scopri una vulnerabilità di sicurezza, ti preghiamo di segnalarla in modo responsabile **direttamente agli sviluppatori o ai maintainer del progetto** tramite un canale privato.

**Si prega di NON aprire un'issue pubblica su GitHub per segnalare vulnerabilità di sicurezza.**

Per segnalare una vulnerabilità, invia un'email a:

`alessandrodezuani@outlook.it`


Nella tua email, includi quante più informazioni possibili per aiutarci a capire e riprodurre il problema, come:

* Una descrizione dettagliata della vulnerabilità.
* I passi per riprodurla.
* Eventuali screenshot o video (se pertinenti e non contengono dati sensibili).
* Informazioni sul tuo ambiente (browser, sistema operativo, ecc.).

Ci impegniamo a rispondere alle segnalazioni di sicurezza in modo tempestivo.

## Pratiche di Sicurezza Implementate

Il progetto "CLUB AUTO D'EPOCA" adotta le seguenti pratiche per garantire la sicurezza:

### 1. Gestione delle Credenziali Utente

* **Hashing delle Password:** Le password degli utenti (soci e non soci registrati) vengono salvate nel database utilizzando algoritmi di hashing robusti e non reversibili (come indicato dall'uso di `$2y$` nello schema `users`, che suggerisce bcrypt o simile). Le password non vengono mai memorizzate in testo chiaro.
* **Login Sicuro:**
    * Ogni tentativo di login viene loggato nella tabella `login_attempts`, registrando l'indirizzo IP, il timestamp e l'esito (successo/fallimento). Questo aiuta a monitorare e rilevare attività sospette.
    * Sebbene non sia esplicitamente implementato un blocco dopo N tentativi falliti in un breve periodo (come da requisiti iniziali), la funzionalità di logging consente l'implementazione di tale logica a livello applicativo o di firewall.
    * È previsto un timeout per la sessione di login per prevenire attacchi di tipo "timing".
* **Registrazione Utente:**
    * Richiede una Email formalmente valida e una Password con requisiti di complessità (lettera, numero, maiuscola, carattere speciale).
    * Include la visualizzazione di un Captcha per mitigare registrazioni automatizzate/bot.
    * Prevede l'invio di una mail di conferma con link di attivazione per verificare l'indirizzo email.
    * Email e password vengono salvate criptate (hashing per password, potenzialmente criptazione per email sensibili se necessario, sebbene l'hashing per la sola password sia lo standard).
* **Modifica Password:**
    * Applica gli stessi requisiti di complessità per la nuova password.
    * Include la visualizzazione di un Captcha.
    * Prevede l'invio di una mail di conferma modifica con link di conferma.
* **OTP per Iscrizione Raduno:** L'iscrizione finale a un raduno richiede la conferma tramite One-Time Password (OTP) inviata via email, aggiungendo un ulteriore livello di verifica per le azioni critiche.

### 2. Protezione dei Dati Sensibili

* **Dati Soci/Auto:** Informazioni come Codice Fiscale, numeri di tessera, numero di telaio (VIN) e targa sono considerati sensibili. L'accesso a questi dati è limitato tramite il sistema di autenticazione (login). Se esistono ruoli (`admin` vs utente normale), l'autorizzazione garantirà che solo gli utenti autorizzati possano accedere a specifici insiemi di dati.
* **Dati di Pagamento:** Viene gestito solo lo stato del pagamento della quota pranzo ("In attesa", "Pagato", ecc.). I dettagli di pagamento sensibili (es. carte di credito) *non* vengono gestiti o memorizzati dalla piattaforma web, ma gestiti offline dal segretario, riducendo drasticamente il rischio legato a questi dati.

### 3. Gestione di Blacklist e Mitigazione Abusi

* Vengono utilizzate tabelle dedicate (`banned_ips`, `banned_emails`) per bloccare gli accessi e le registrazioni da indirizzi IP o email noti per attività malevole.
* Gli indirizzi IP bannati non possono effettuare né la registrazione né il login.
* Gli indirizzi email bannati non possono essere utilizzati per la registrazione.

### 4. Gestione dei File Caricati (Foto Auto)

* Il caricamento delle foto delle auto richiede un'attenta validazione per prevenire l'upload di file malevoli (es. script eseguibili). È fondamentale implementare controlli sul tipo di file, dimensione e utilizzare pratiche sicure per la memorizzazione (es. rinominare i file, salvarli al di fuori della webroot, servire i file statici in modo sicuro).

### 5. Validazione e Sanificazione Input

* Tutti gli input forniti dagli utenti (nei form di registrazione, login, aggiornamento dati, iscrizione raduni, ecc.) devono essere opportunamente validati e sanificati lato server per prevenire attacchi comuni come SQL Injection, Cross-Site Scripting (XSS) e altri attacchi basati sull'iniezione di codice/dati.

### 6. Sicurezza dell'Infrastruttura e del Deploy (Docker)

* Il `Dockerfile` fornito dimostra l'attenzione nell'utilizzare immagini base aggiornate (`php:8.4-apache-bullseye`).
* Include comandi (`apt-get update && apt-get upgrade -y`) per aggiornare i pacchetti del sistema operativo all'interno del container, applicando le patch di sicurezza più recenti alle dipendenze a livello di sistema.
* Configura correttamente i permessi sui file dell'applicazione (`chown -R www-data:www-data /var/www/html/`), essenziale per prevenire vulnerabilità legate ai permessi sui file.
* È **essenziale** che il deploy finale avvenga tramite HTTPS per criptare il traffico tra il browser dell'utente e il server, proteggendo credenziali e dati sensibili durante la trasmissione. La configurazione SSL/TLS deve essere robusta.

### 7. Aggiornamento delle Dipendenze

* Mantenere aggiornato il codice dell'applicazione (PHP, eventuali framework o librerie utilizzate) e le dipendenze a livello di sistema operativo (tramite `apt-get upgrade` nel Dockerfile o gestione del server) è cruciale per proteggersi da vulnerabilità note.

### 8. Separazione dei Ruoli (Amministrazione)

* L'esistenza della tabella `admin` suggerisce un'area di amministrazione. È fondamentale che l'accesso all'area amministrativa sia strettamente controllato, con autenticazione separata o basata sui ruoli, e che le funzionalità amministrative (gestione soci, auto, manifestazioni, blacklist) implementino controlli di autorizzazione robusti per prevenire accessi non autorizzati a funzioni privilegiate.

## Ambito

Questa politica di sicurezza si applica al codice sorgente del progetto "CLUB AUTO D'EPOCA" ospitato in questo repository GitHub e all'applicazione web derivata.

---

Grazie per aver letto la nostra politica di sicurezza. Ci impegniamo a migliorare continuamente la sicurezza del nostro progetto.