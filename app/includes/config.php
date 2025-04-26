<?php
// --- Impostazioni Database ---
define('DB_SERVER','db');
define('DB_USER','root');
define('DB_PASS' ,'paolino53');
define('DB_NAME', 'loginsystem');
define('DB_PORT', 3306); // Aggiungi la porta se non è la predefinita 3306

// --- Chiavi Google reCAPTCHA v2 Checkbox ---
define('RECAPTCHA_SITE_KEY', '6Ld5eyErAAAAAN3fWiUC1f53DDe9pAnZRXvZ8bF-'); // Sostituisci con la tua Site Key
define('RECAPTCHA_SECRET_KEY', '6Ld5eyErAAAAAJXol4GEEqDvbzoBnTArf8xjx-CS'); // Sostituisci con la tua Secret Key

// --- Impostazioni PHPMailer (SMTP) ---
define('SMTP_HOST', 'smtp.gmail.com');          // Host SMTP (es. 'smtp.gmail.com' o il tuo)
define('SMTP_AUTH', true);                     // Abilita autenticazione SMTP (true/false)
define('SMTP_USER', 'dezuani.fotovoltaico@gmail.com'); // Username SMTP (la tua email)
define('SMTP_PASS', 'ymzf ceed cgvr tpga');          // Password SMTP (la tua password o App Password)
define('SMTP_SECURE', 'tls');                  // Tipo di crittografia (tls o ssl) - Usa PHPMailer::ENCRYPTION_STARTTLS o PHPMailer::ENCRYPTION_SMTPS
define('SMTP_PORT', 587);                      // Porta TCP per la connessione (587 per TLS, 465 per SSL)
define('MAIL_CHARSET', 'UTF-8');               // Set di caratteri per l'email

// --- Impostazioni Mittente Email ---
define('MAIL_FROM', 'dezuani.fotovoltaico@gmail.com'); // Indirizzo email mittente
define('MAIL_FROM_NAME', 'Asi-Castello');         // Nome mittente

// --- URL Base Applicazione (per link nelle email) ---
define('APP_BASE_URL', 'http://127.0.0.1/'); // Modifica con il tuo URL reale!


// --- Connessione al Database ---
$con = mysqli_connect(DB_SERVER, DB_USER, DB_PASS, DB_NAME, DB_PORT);

// Check connection
if (mysqli_connect_errno())
{
    // Logga l'errore invece di mostrarlo direttamente
    error_log("Failed to connect to MySQL: " . mysqli_connect_error());
    // Mostra un messaggio generico all'utente o gestisci l'errore in modo appropriato
    die("Errore di connessione al database. Si prega di riprovare più tardi.");
}

// Imposta il charset della connessione (consigliato)
mysqli_set_charset($con, "utf8mb4");

?>
