# asi-castello
Project Work ITS - Gruppo Castello - Club auto d'epoca



# Club Auto d'Epoca - Gestione Online

Questo progetto realizza un'applicazione web dedicata alla gestione completa delle attività di un club di auto d'epoca. Il sito permette ai soci di gestire le proprie informazioni, le auto possedute, iscriversi agli eventi e interagire con il club.

## Descrizione del Progetto

Il sito web mira a digitalizzare e semplificare la gestione di un club di auto d'epoca, offrendo funzionalità per:

* Anagrafica soci e gestione tesseramenti.
* Catalogo delle auto dei soci, incluse certificazioni.
* Organizzazione e gestione di manifestazioni (raduni).
* Iscrizione online alle manifestazioni da parte dei soci (ed eventualmente non soci).
* Autenticazione sicura degli utenti.

## Funzionalità Principali

### Gestione Soci
* Registrazione anagrafica soci (nome, cognome, codice fiscale).
* Gestione della tessera del club (con validità annuale).
* Registrazione della tessera ASI (Automotoclub Storico Italiano).
* **Requisito:** Ogni socio deve possedere almeno un'auto d'epoca per potersi iscrivere.

### Gestione Auto
* Associazione di una o più auto a ciascun socio.
* Memorizzazione dei dettagli di ogni auto:
    * Marca
    * Modello
    * Colore
    * Cilindrata
    * Anno di immatricolazione
    * Targa
* Indicazione dell'eventuale certificazione ASI (Targa d'Oro).

### Gestione Manifestazioni (Raduni)
* Creazione e gestione di eventi (raduni, visite culturali, gite).
* Definizione dei dettagli dell'evento:
    * Titolo
    * Data di inizio
    * Programma della giornata (luogo ritrovo, orari, percorso, visita, pranzo).
    * Stima numero auto/partecipanti.
* Invio automatico (o manuale) di email di annuncio ai soci (almeno 1 mese prima).
* Sistema di iscrizione online per i soci:
    * Selezione dell'auto con cui partecipare.
    * Indicazione del numero di persone presenti (per prenotazione pranzo).
    * Gestione della quota di partecipazione (es. per il pranzo).
* Chiusura automatica delle iscrizioni 5 giorni prima dell'evento.

### Autenticazione Utenti
* Accesso riservato tramite login e password per tutti gli utenti registrati (soci e potenziali non soci).
* Attualmente non sono previsti ruoli utente differenziati.

## Funzionalità Facoltative / Avanzate

Queste funzionalità aggiungono livelli di sicurezza e flessibilità all'applicazione.

### Partecipazione Non Soci
* Possibilità per i non soci di iscriversi alle manifestazioni, a condizione che posseggano un'auto d'epoca.

### Registrazione Sicura Utenti
* **Registrazione:**
    * Utilizzo di coppia Email / Password.
    * Validazione formale dell'indirizzo Email.
    * **Requisiti Password:** Almeno una lettera minuscola, una maiuscola, un numero, un carattere speciale.
    * Visualizzazione di un **Captcha** per prevenire bot.
    * Invio **Email di Conferma** con link di attivazione univoco.
    * Salvataggio sicuro di Email e Password nel database (formato **criptato**).

### Login Sicuro
* **Tracciamento Tentativi:** Registrazione su database di ogni tentativo di login (indirizzo IP, Data/Ora, Esito: successo/fallimento).
* **Timeout Form:** Reset automatico del form di login con messaggio all'utente se non viene premuto il pulsante "Login" entro 30 secondi.

### Modifica Password Sicura
* **Requisiti Nuova Password:** Almeno una lettera minuscola, una maiuscola, un numero, un carattere speciale.
* Visualizzazione di un **Captcha**.
* Invio **Email di Conferma** con link per validare la modifica della password.

### Iscrizione Raduno con OTP
* Richiesta di inserimento di un codice **OTP (One-Time Password)**, inviato via email all'utente, per confermare definitivamente l'iscrizione a un raduno.

### Gestione Blacklist
* Implementazione di tabelle nel database per la gestione di:
    * **IP Bannati:** Indirizzi IP bloccati, impossibilitati a effettuare login o registrazione.
    * **Email Bannate:** Indirizzi email bloccati, impossibilitati a effettuare la registrazione.

## Stack Tecnologico (Da definire)

* **Linguaggio Backend:**  PHP
* **Database:** MySQL
* **Frontend:** HTML, CSS, JavaScript, Bootstrap
* **Altro:** Servizio Email, reCAPTCHA

## Installazione 

```bash
## Requisiti

- Docker
- Docker Compose

## Installazione rapida

1. Clona questo repository:
```bash
git clone https://github.com/username/nome-repository.git
cd nome-repository
```

2. Avvia i container:
```bash
docker-compose up -d
```

3. Accedi all'applicazione:
   - Web app: http://localhost
   - phpMyAdmin: http://localhost:8080
     - Username: appuser
     - Password: apppassword

## Configurazione

L'applicazione è preconfigurata per funzionare con Docker, non è necessaria alcuna configurazione aggiuntiva.

### Credenziali Database

- **Database**: loginsystem
- **Utente**: appuser
- **Password**: apppassword
- **Root Password**: rootpassword

## Sviluppo

Per modificare i file dell'applicazione, modifica i file nella directory `app/`. I cambiamenti saranno immediatamente visibili grazie al volume configurato in Docker Compose.

## Risoluzione problemi

- Se incontri errori durante il primo avvio, prova a ricostruire i container:
```bash
docker-compose down
docker-compose up --build -d
```
