<?php
session_start();
// Usa il file di configurazione corretto che definisce $con
require_once 'includes/config.php';
// Includi PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php'; // Assicurati che il percorso sia corretto

// Verifica se l'utente è loggato usando $_SESSION['id']
if (!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit;
}

$id_utente_loggato = $_SESSION['id'];
$messaggio = ''; // Per mostrare messaggi di successo o errore
$errore_campi = []; // Per evidenziare campi non validi nel form
$messaggio_tipo = 'info'; // Per lo stile del messaggio (success, danger, info)

// --- Recupero Info Utente Loggato (Controllo Attivazione ed Email) ---
$stmt_user = mysqli_prepare($con, "SELECT is_active, id_socio, email, fname FROM users WHERE id = ?");
$is_user_active = false;
$id_socio_utente = null; // ID socio se l'utente è anche socio
$email_utente = null;
$nome_utente = null;

if ($stmt_user) {
    mysqli_stmt_bind_param($stmt_user, "i", $id_utente_loggato);
    mysqli_stmt_execute($stmt_user);
    $result_user = mysqli_stmt_get_result($stmt_user);
    if (mysqli_num_rows($result_user) > 0) {
        $user_data = mysqli_fetch_assoc($result_user);
        $is_user_active = (bool)$user_data['is_active'];
        $id_socio_utente = $user_data['id_socio']; // Può essere NULL
        $email_utente = $user_data['email'];
        $nome_utente = $user_data['fname'];
    }
    mysqli_stmt_close($stmt_user);
} else {
    // Gestisci errore preparazione query utente
    error_log("Errore preparazione query utente: " . mysqli_error($con));
    die("Errore nel controllo utente. Si prega di riprovare più tardi.");
}

// Se manca l'email dell'utente, non può procedere
if (empty($email_utente)) {
     die("Errore: Email utente non trovata. Impossibile procedere con l'iscrizione.");
}

// --- Recupero Auto del Socio (se applicabile) ---
$auto_socio = [];
if ($id_socio_utente !== null) {
    $stmt_auto = mysqli_prepare($con, "SELECT id, marca, modello, targa FROM auto WHERE id_socio = ? ORDER BY marca, modello");
    if ($stmt_auto) {
        mysqli_stmt_bind_param($stmt_auto, "i", $id_socio_utente);
        mysqli_stmt_execute($stmt_auto);
        $result_auto = mysqli_stmt_get_result($stmt_auto);
        while ($row_auto = mysqli_fetch_assoc($result_auto)) {
            $auto_socio[] = $row_auto;
        }
        mysqli_stmt_close($stmt_auto);
    } else {
        $messaggio = "Errore nel recupero auto del socio: " . mysqli_error($con);
        $messaggio_tipo = 'danger';
        error_log($messaggio); // Logga l'errore
    }
}


// --- Gestione ISCRIZIONE PRELIMINARE (quando il form viene inviato) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['iscriviti'])) {

    // Recupera e pulisci i dati dal form
    $id_manifestazione_selezionata = filter_input(INPUT_POST, 'id_manifestazione', FILTER_VALIDATE_INT);
    $numero_partecipanti = filter_input(INPUT_POST, 'numero_partecipanti', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $car_marca = trim($_POST['car_marca'] ?? '');
    $car_modello = trim($_POST['car_modello'] ?? '');
    $car_targa = trim(strtoupper($_POST['car_targa'] ?? ''));
    $note_iscrizione = trim($_POST['note_iscrizione'] ?? '');
    $id_auto_socio_selezionata = filter_input(INPUT_POST, 'id_auto_socio', FILTER_VALIDATE_INT);

    // Validazione di base
    if (!$id_manifestazione_selezionata) $errore_campi['generale'] = "Manifestazione non valida.";
    if (!$numero_partecipanti) $errore_campi['numero_partecipanti'] = "Numero partecipanti non valido (minimo 1).";
    if (empty($car_marca)) $errore_campi['car_marca'] = "Marca auto obbligatoria.";
    if (empty($car_modello)) $errore_campi['car_modello'] = "Modello auto obbligatorio.";
    if (empty($car_targa)) $errore_campi['car_targa'] = "Targa auto obbligatoria.";

    if (empty($errore_campi)) {
        if (!$is_user_active) {
            $messaggio = "Il tuo account non è attivo. Contatta l'amministrazione.";
            $messaggio_tipo = 'danger';
        } else {
            // 1. Verifica se la manifestazione esiste e se le iscrizioni sono aperte
            $stmt_check_manif = mysqli_prepare($con, "SELECT data_chiusura_iscrizioni, titolo FROM manifestazioni WHERE id = ?"); // Prendi anche il titolo
            if (!$stmt_check_manif) {
                 $messaggio = "Errore preparazione query verifica manifestazione: " . mysqli_error($con);
                 $messaggio_tipo = 'danger';
                 error_log($messaggio);
            } else {
                mysqli_stmt_bind_param($stmt_check_manif, "i", $id_manifestazione_selezionata);
                mysqli_stmt_execute($stmt_check_manif);
                $result_check_manif = mysqli_stmt_get_result($stmt_check_manif);

                if (mysqli_num_rows($result_check_manif) > 0) {
                    $manifestazione = mysqli_fetch_assoc($result_check_manif);
                    $titolo_manifestazione = $manifestazione['titolo']; // Salva il titolo
                    mysqli_stmt_close($stmt_check_manif);

                    $data_chiusura = new DateTime($manifestazione['data_chiusura_iscrizioni']);
                    $ora_attuale = new DateTime();

                    if ($ora_attuale <= $data_chiusura) {
                        // 2. Verifica se l'utente ha già un'iscrizione CONFERMATA o PENDENTE per questa manifestazione
                        $stmt_gia_iscritto = mysqli_prepare($con, "SELECT id, otp_confirmed FROM iscrizioni_manifestazioni WHERE id_user = ? AND id_manifestazione = ?");
                        if (!$stmt_gia_iscritto) {
                            $messaggio = "Errore preparazione query 'già iscritto': " . mysqli_error($con);
                            $messaggio_tipo = 'danger';
                            error_log($messaggio);
                        } else {
                            mysqli_stmt_bind_param($stmt_gia_iscritto, "ii", $id_utente_loggato, $id_manifestazione_selezionata);
                            mysqli_stmt_execute($stmt_gia_iscritto);
                            $result_gia_iscritto = mysqli_stmt_get_result($stmt_gia_iscritto);
                            $iscrizione_esistente = mysqli_fetch_assoc($result_gia_iscritto);
                            mysqli_stmt_close($stmt_gia_iscritto);

                            if ($iscrizione_esistente) {
                                if ($iscrizione_esistente['otp_confirmed'] == 1) {
                                    $messaggio = "Sei già iscritto e confermato a questa manifestazione.";
                                    $messaggio_tipo = 'warning';
                                } else {
                                    // Iscrizione pendente, forse re-inviare OTP o reindirizzare alla conferma?
                                    // Per ora, reindirizziamo alla pagina di conferma esistente
                                    $id_iscrizione_pendente = $iscrizione_esistente['id'];
                                    header("Location: conferma_iscrizione.php?iscrizione_id=" . $id_iscrizione_pendente . "&pending=1");
                                    exit;
                                }
                            } else {
                                // Nessuna iscrizione esistente, procedi con la creazione preliminare e invio OTP

                                try {
                                    // 3. Genera OTP e hash
                                    $otp_plain = sprintf("%06d", random_int(0, 999999));
                                    $otp_hash = password_hash($otp_plain, PASSWORD_DEFAULT);
                                    $otp_expiry = date("Y-m-d H:i:s", time() + 900); // Scadenza tra 15 minuti

                                    // 4. Inserisci iscrizione preliminare nel DB
                                    $stmt_insert = mysqli_prepare($con,
                                        "INSERT INTO iscrizioni_manifestazioni
                                        (id_manifestazione, id_user, numero_partecipanti, data_iscrizione, stato_pagamento,
                                         car_marca, car_modello, car_targa, id_auto_socio, note_iscrizione,
                                         otp_codice, otp_expires, otp_confirmed)
                                        VALUES (?, ?, ?, NOW(), 'In attesa', ?, ?, ?, ?, ?, ?, ?, 0)" // otp_confirmed = 0
                                    );

                                    $id_auto_da_inserire = ($id_auto_socio_selezionata > 0) ? $id_auto_socio_selezionata : null;

                                    if (!$stmt_insert) {
                                        throw new Exception("Errore preparazione query inserimento: " . mysqli_error($con));
                                    }

                                    mysqli_stmt_bind_param(
                                        $stmt_insert,
                                        "iiisssisss", // Aggiunti 'sss' per otp_hash, otp_expiry
                                        $id_manifestazione_selezionata,
                                        $id_utente_loggato,
                                        $numero_partecipanti,
                                        $car_marca,
                                        $car_modello,
                                        $car_targa,
                                        $id_auto_da_inserire,
                                        $note_iscrizione,
                                        $otp_hash, // Salva l'hash
                                        $otp_expiry
                                    );

                                    if (mysqli_stmt_execute($stmt_insert)) {
                                        $id_nuova_iscrizione = mysqli_insert_id($con); // Ottieni l'ID della nuova iscrizione
                                        mysqli_stmt_close($stmt_insert);

                                        // 5. Invia l'email con l'OTP
                                        $mail = new PHPMailer(true);
                                        try {
                                            // Impostazioni Server (COPIA DA password-recovery.php e ADATTA)
                                            $mail->isSMTP();
                                            $mail->Host = 'smtp.gmail.com'; // O il tuo host SMTP
                                            $mail->SMTPAuth = true;
                                            // --- USA LE TUE CREDENZIALI REALI QUI ---
                                            $mail->Username = 'dezuani.fotovoltaico@gmail.com'; // Sostituisci
                                            $mail->Password = 'ymzf ceed cgvr tpga'; // Sostituisci (Usa App Password se hai 2FA)
                                            // --- ----------------------------- ---
                                            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                                            $mail->Port = 587;
                                            $mail->CharSet = 'UTF-8';

                                            // Mittente e Destinatario
                                            // --- USA IL TUO INDIRIZZO E NOME QUI ---
                                            $mail->setFrom('dezuani.fotovoltaico@gmail.com', 'Asi-Castello'); // Sostituisci
                                            // --- ----------------------------- ---
                                            $mail->addAddress($email_utente, $nome_utente);

                                            // Contenuto
                                            $mail->isHTML(true);
                                            $mail->Subject = 'Conferma Iscrizione Manifestazione: ' . htmlspecialchars($titolo_manifestazione);

                                            $bodyContent = "Gentile " . htmlspecialchars($nome_utente) . ",<br><br>";
                                            $bodyContent .= "Grazie per esserti pre-iscritto alla manifestazione: <strong>" . htmlspecialchars($titolo_manifestazione) . "</strong>.<br>";
                                            $bodyContent .= "Per confermare la tua iscrizione, inserisci il seguente codice OTP nella pagina di conferma:<br><br>";
                                            $bodyContent .= "<strong style='font-size: 1.5em; letter-spacing: 2px;'>" . $otp_plain . "</strong><br><br>"; // Mostra l'OTP in chiaro
                                            $bodyContent .= "Questo codice scadrà tra 15 minuti.<br><br>";
                                            $bodyContent .= "Puoi confermare la tua iscrizione qui: ";
                                            // --- MODIFICA QUESTO URL BASE ---
                                            $confirmLinkBase = "http://localhost/asi-castello/"; // Cambia con il tuo URL reale!
                                            // --- ------------------------ ---
                                            $confirmLink = $confirmLinkBase . "conferma_iscrizione.php?iscrizione_id=" . $id_nuova_iscrizione;
                                            $bodyContent .= '<a href="' . $confirmLink . '">' . $confirmLink . '</a><br><br>';
                                            $bodyContent .= "Se non hai richiesto tu questa iscrizione, ignora questa email.<br><br>";
                                            $bodyContent .= "Cordiali saluti,<br>Il Team Asi-Castello";

                                            $mail->Body = $bodyContent;
                                            $mail->AltBody = "Gentile " . htmlspecialchars($nome_utente) . ",\n\nGrazie per esserti pre-iscritto alla manifestazione: " . htmlspecialchars($titolo_manifestazione) . ".\nPer confermare la tua iscrizione, inserisci il seguente codice OTP nella pagina di conferma: " . $otp_plain . "\nQuesto codice scadrà tra 15 minuti.\n\nPuoi confermare qui: " . $confirmLink . "\n\nSe non hai richiesto tu questa iscrizione, ignora questa email.\n\nCordiali saluti,\nIl Team Asi-Castello";

                                            $mail->send();

                                            // 6. Reindirizza alla pagina di conferma
                                            header("Location: conferma_iscrizione.php?iscrizione_id=" . $id_nuova_iscrizione);
                                            exit();

                                        } catch (Exception $e) {
                                            // Errore invio email: logga l'errore e informa l'utente
                                            error_log("Mailer Error [Manifestazione OTP]: " . $mail->ErrorInfo);
                                            // Potresti voler cancellare l'iscrizione pendente qui o marcarla come fallita
                                            // mysqli_query($con, "DELETE FROM iscrizioni_manifestazioni WHERE id = $id_nuova_iscrizione");
                                            $messaggio = "Iscrizione preliminare creata, ma errore nell'invio dell'email di conferma. Contatta l'assistenza. Dettagli: {$mail->ErrorInfo}";
                                            $messaggio_tipo = 'danger';
                                            // Non fare redirect qui, mostra il messaggio
                                        }

                                    } else {
                                        // Errore nell'INSERT
                                        if (mysqli_errno($con) == 1062) {
                                             throw new Exception("Risulti già pre-iscritto o iscritto a questa manifestazione.");
                                        } else {
                                             throw new Exception("Errore durante la creazione dell'iscrizione preliminare: " . mysqli_stmt_error($stmt_insert));
                                        }
                                        mysqli_stmt_close($stmt_insert);
                                    }

                                } catch (Exception $e) {
                                    // Cattura eccezioni da generazione token, DB o invio email
                                    $messaggio = "Si è verificato un errore: " . $e->getMessage();
                                    $messaggio_tipo = 'danger';
                                    error_log("Errore Iscrizione Manifestazione: " . $e->getMessage());
                                }
                            } // Fine 'else' per iscrizione non esistente
                        } // Fine 'else' per query 'già iscritto' OK
                    } else {
                        $messaggio = "Le iscrizioni per questa manifestazione sono chiuse.";
                        $messaggio_tipo = 'warning';
                    }
                } else {
                     if ($stmt_check_manif) mysqli_stmt_close($stmt_check_manif); // Chiudi se aperto
                    $messaggio = "Manifestazione non trovata.";
                    $messaggio_tipo = 'danger';
                }
                // Non chiudere $stmt_check_manif di nuovo qui se è già stato chiuso
            } // Fine 'else' per query check manifestazione OK
        } // Fine 'else' per utente attivo
    } else {
        // Se ci sono errori di validazione campi, costruisci un messaggio
        $messaggio = "Errore nel form:<ul>";
        foreach ($errore_campi as $campo => $err) {
            $messaggio .= "<li>" . htmlspecialchars($err) . "</li>";
        }
        $messaggio .= "</ul>";
        $messaggio_tipo = 'danger';
    }
} // Fine gestione POST


// --- Recupero Manifestazioni Disponibili ---
// (Codice invariato)
$sql_manifestazioni = "SELECT id, titolo, data_inizio, data_chiusura_iscrizioni, programma, luogo_ritrovo, quota_pranzo, note
                       FROM manifestazioni
                       WHERE data_chiusura_iscrizioni >= NOW()
                       ORDER BY data_inizio ASC";
$result_manifestazioni = mysqli_query($con, $sql_manifestazioni);
if (!$result_manifestazioni) {
    error_log("Errore query manifestazioni: " . mysqli_error($con));
    die("Errore nel caricamento delle manifestazioni. Si prega di riprovare più tardi.");
}

// --- Recupero Iscrizioni CONFERMATE dell'Utente ---
// Modifichiamo per mostrare solo quelle confermate (otp_confirmed = 1) qui
$sql_mie_iscrizioni = "SELECT m.id, m.titolo, m.data_inizio, i.numero_partecipanti, i.car_marca, i.car_modello, i.car_targa, i.stato_pagamento
                      FROM iscrizioni_manifestazioni i
                      JOIN manifestazioni m ON i.id_manifestazione = m.id
                      WHERE i.id_user = ? AND i.otp_confirmed = 1
                      ORDER BY m.data_inizio ASC";
$stmt_mie = mysqli_prepare($con, $sql_mie_iscrizioni);
$mie_iscrizioni_confermate = []; // Rinomina per chiarezza
if($stmt_mie) {
    mysqli_stmt_bind_param($stmt_mie, "i", $id_utente_loggato);
    mysqli_stmt_execute($stmt_mie);
    $result_mie = mysqli_stmt_get_result($stmt_mie);
    while ($row = mysqli_fetch_assoc($result_mie)) {
        $mie_iscrizioni_confermate[$row['id']] = $row; // Usa ID manifestazione come chiave
    }
    mysqli_stmt_close($stmt_mie);
} else {
    $messaggio .= "<br>Errore nella preparazione query mie iscrizioni confermate: " . mysqli_error($con);
    if($messaggio_tipo != 'danger') $messaggio_tipo = 'warning'; // Non sovrascrivere un errore più grave
    error_log("Errore preparazione query mie iscrizioni confermate: " . mysqli_error($con));
}


mysqli_close($con); // Chiudi la connessione al database alla fine dello script PHP
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iscrizione Manifestazioni</title>
    <link href="css/styles.css" rel="stylesheet" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/js/all.min.js" crossorigin="anonymous"></script>
    <style>
        /* Stili aggiuntivi specifici per questa pagina, se necessario */
        body { padding-top: 56px; /* Altezza navbar se fissa */}
        .container-manifestazioni { max-width: 900px; margin: 20px auto; padding: 15px; }
        .manifestazione { border: 1px solid #ccc; padding: 15px; margin-bottom: 20px; border-radius: 5px; background-color: #f9f9f9; }
        .manifestazione h3 { margin-top: 0; color: #333; }
        .manifestazione p, .manifestazione div { margin-bottom: 8px; }
        .manifestazione strong { color: #555; }
        .manifestazione form { margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee; }
        .manifestazione form label { display: block; margin-bottom: 5px; font-weight: bold; }
        .manifestazione form textarea { resize: vertical; min-height: 60px; }
        .alert ul { margin-top: 10px; margin-bottom: 0; padding-left: 20px; }
        .campo-errore input, .campo-errore textarea, .campo-errore select { border-color: #dc3545 !important; }
        .campo-errore label { color: #dc3545; }
        .gia-iscritto { font-style: italic; color: green; font-weight: bold; margin-top: 10px; }
        .mie-iscrizioni { margin-top: 30px; padding-top: 20px; border-top: 2px solid #eee; }
        .mie-iscrizioni h2 { margin-bottom: 15px; }
        .dettagli-iscrizione span { display: inline-block; margin-right: 15px; font-size: 0.9em; }
        .auto-socio-select { margin-bottom: 15px; }
        .hidden { display: none; }
    </style>
</head>
<body class="sb-nav-fixed">

    <?php include_once('includes/navbar.php');?>

    <div id="layoutSidenav">
        <?php include_once('includes/sidebar.php');?>

        <div id="layoutSidenav_content">
            <main>
                <div class="container-fluid px-4 container-manifestazioni">

                    <h1 class="mt-4">Manifestazioni Disponibili</h1>
                     <ol class="breadcrumb mb-4">
                        <li class="breadcrumb-item"><a href="welcome.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Manifestazioni</li>
                    </ol>

                    <?php if (!$is_user_active): ?>
                        <div class="alert alert-danger">
                            Il tuo account utente non è attivo. Non puoi iscriverti alle manifestazioni. Contatta l'amministrazione.
                        </div>
                    <?php endif; ?>

                    <?php if ($messaggio): ?>
                        <div class="alert alert-<?php echo $messaggio_tipo; ?> alert-dismissible fade show" role="alert">
                            <?php echo $messaggio; // L'HTML è già gestito nella creazione del messaggio ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($result_manifestazioni && mysqli_num_rows($result_manifestazioni) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($result_manifestazioni)): ?>
                            <?php $id_manifestazione_corrente = $row['id']; ?>
                            <div class="manifestazione card shadow-sm">
                                <div class="card-body">
                                    <h3 class="card-title"><?php echo htmlspecialchars($row['titolo']); ?></h3>
                                    <p><strong>Data Inizio:</strong> <?php echo date("d/m/Y H:i", strtotime($row['data_inizio'])); ?></p>
                                    <p><strong>Chiusura Iscrizioni:</strong> <?php echo date("d/m/Y H:i", strtotime($row['data_chiusura_iscrizioni'])); ?></p>
                                    <?php if ($row['luogo_ritrovo']): ?>
                                        <p><strong>Luogo Ritrovo:</strong> <?php echo htmlspecialchars($row['luogo_ritrovo']); ?></p>
                                    <?php endif; ?>
                                    <?php if ($row['quota_pranzo'] > 0): ?>
                                        <p><strong>Quota Pranzo:</strong> € <?php echo number_format($row['quota_pranzo'], 2, ',', '.'); ?></p>
                                    <?php endif; ?>
                                    <?php if ($row['programma']): ?>
                                        <div><strong>Programma:</strong><br><?php echo nl2br(htmlspecialchars($row['programma'])); ?></div>
                                    <?php endif; ?>
                                     <?php if ($row['note']): ?>
                                        <div><strong>Note:</strong><br><?php echo nl2br(htmlspecialchars($row['note'])); ?></div>
                                    <?php endif; ?>

                                    <?php
                                    // Controlla se l'utente ha un'iscrizione CONFERMATA per QUESTA manifestazione
                                    $gia_iscritto_confermato = isset($mie_iscrizioni_confermate[$id_manifestazione_corrente]);
                                    ?>

                                    <?php if ($gia_iscritto_confermato): ?>
                                        <p class="gia-iscritto mt-3">Sei già iscritto a questa manifestazione.</p>
                                        <?php
                                            $dettagli = $mie_iscrizioni_confermate[$id_manifestazione_corrente];
                                        ?>
                                        <div class="dettagli-iscrizione">
                                            <span>Partecipanti: <?php echo htmlspecialchars($dettagli['numero_partecipanti']); ?></span>
                                            <span>Auto: <?php echo htmlspecialchars($dettagli['car_marca'] . ' ' . $dettagli['car_modello'] . ' (' . $dettagli['car_targa'] . ')'); ?></span>
                                            <span>Pagamento: <?php echo htmlspecialchars($dettagli['stato_pagamento']); ?></span>
                                        </div>
                                    <?php elseif ($is_user_active): // Mostra form solo se utente attivo e non iscritto confermato ?>
                                        <form action="manifestazioni.php" method="post" id="form-iscrizione-<?php echo $id_manifestazione_corrente; ?>" class="mt-3 pt-3 border-top">
                                            <input type="hidden" name="id_manifestazione" value="<?php echo $id_manifestazione_corrente; ?>">

                                            <h4>Modulo di Iscrizione</h4>

                                            <div class="mb-3 <?php echo isset($errore_campi['numero_partecipanti']) ? 'campo-errore' : ''; ?>">
                                                <label for="numero_partecipanti_<?php echo $id_manifestazione_corrente; ?>" class="form-label">Numero Partecipanti (incluso te):</label>
                                                <input type="number" class="form-control" id="numero_partecipanti_<?php echo $id_manifestazione_corrente; ?>" name="numero_partecipanti" value="<?php echo isset($_POST['numero_partecipanti']) && ($_POST['id_manifestazione'] ?? null) == $id_manifestazione_corrente ? htmlspecialchars($_POST['numero_partecipanti']) : '1'; ?>" min="1" required>
                                            </div>

                                            <?php if (!empty($auto_socio)): ?>
                                                <div class="mb-3 auto-socio-select">
                                                    <label for="id_auto_socio_<?php echo $id_manifestazione_corrente; ?>" class="form-label">Seleziona un'auto registrata (opzionale):</label>
                                                    <select class="form-select" id="id_auto_socio_<?php echo $id_manifestazione_corrente; ?>" name="id_auto_socio" onchange="compilaDatiAuto(this, <?php echo $id_manifestazione_corrente; ?>)">
                                                        <option value="">-- Inserisci manualmente i dati sotto --</option>
                                                        <?php foreach ($auto_socio as $auto): ?>
                                                            <option value="<?php echo $auto['id']; ?>"
                                                                    data-marca="<?php echo htmlspecialchars($auto['marca']); ?>"
                                                                    data-modello="<?php echo htmlspecialchars($auto['modello']); ?>"
                                                                    data-targa="<?php echo htmlspecialchars($auto['targa']); ?>"
                                                                    <?php echo (isset($_POST['id_auto_socio']) && $_POST['id_auto_socio'] == $auto['id'] && ($_POST['id_manifestazione'] ?? null) == $id_manifestazione_corrente) ? 'selected' : ''; ?>>
                                                                <?php echo htmlspecialchars($auto['marca'] . ' ' . $auto['modello'] . ' (' . $auto['targa'] . ')'); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            <?php endif; ?>

                                            <div id="dati-auto-manuali-<?php echo $id_manifestazione_corrente; ?>">
                                                <div class="mb-3 <?php echo isset($errore_campi['car_marca']) ? 'campo-errore' : ''; ?>">
                                                    <label for="car_marca_<?php echo $id_manifestazione_corrente; ?>" class="form-label">Marca Auto:</label>
                                                    <input type="text" class="form-control" id="car_marca_<?php echo $id_manifestazione_corrente; ?>" name="car_marca" value="<?php echo isset($_POST['car_marca']) && ($_POST['id_manifestazione'] ?? null) == $id_manifestazione_corrente ? htmlspecialchars($_POST['car_marca']) : ''; ?>" required>
                                                </div>

                                                <div class="mb-3 <?php echo isset($errore_campi['car_modello']) ? 'campo-errore' : ''; ?>">
                                                    <label for="car_modello_<?php echo $id_manifestazione_corrente; ?>" class="form-label">Modello Auto:</label>
                                                    <input type="text" class="form-control" id="car_modello_<?php echo $id_manifestazione_corrente; ?>" name="car_modello" value="<?php echo isset($_POST['car_modello']) && ($_POST['id_manifestazione'] ?? null) == $id_manifestazione_corrente ? htmlspecialchars($_POST['car_modello']) : ''; ?>" required>
                                                </div>

                                                <div class="mb-3 <?php echo isset($errore_campi['car_targa']) ? 'campo-errore' : ''; ?>">
                                                    <label for="car_targa_<?php echo $id_manifestazione_corrente; ?>" class="form-label">Targa Auto:</label>
                                                    <input type="text" class="form-control" id="car_targa_<?php echo $id_manifestazione_corrente; ?>" name="car_targa" value="<?php echo isset($_POST['car_targa']) && ($_POST['id_manifestazione'] ?? null) == $id_manifestazione_corrente ? htmlspecialchars($_POST['car_targa']) : ''; ?>" required>
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <label for="note_iscrizione_<?php echo $id_manifestazione_corrente; ?>" class="form-label">Note (opzionale):</label>
                                                <textarea class="form-control" id="note_iscrizione_<?php echo $id_manifestazione_corrente; ?>" name="note_iscrizione"><?php echo isset($_POST['note_iscrizione']) && ($_POST['id_manifestazione'] ?? null) == $id_manifestazione_corrente ? htmlspecialchars($_POST['note_iscrizione']) : ''; ?></textarea>
                                            </div>

                                            <button type="submit" name="iscriviti" class="btn btn-primary">Procedi all'iscrizione</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                        <?php mysqli_free_result($result_manifestazioni); ?>
                    <?php else: ?>
                        <p>Al momento non ci sono manifestazioni disponibili per l'iscrizione.</p>
                    <?php endif; ?>


                    <?php if (!empty($mie_iscrizioni_confermate)): ?>
                        <div class="mie-iscrizioni">
                            <h2>Le Tue Iscrizioni Confermate</h2>
                            <ul class="list-group">
                                <?php foreach ($mie_iscrizioni_confermate as $isc): ?>
                                    <li class="list-group-item">
                                        <strong class="d-block mb-1"><?php echo htmlspecialchars($isc['titolo']); ?></strong>
                                        <small class="text-muted">(Inizio: <?php echo date("d/m/Y H:i", strtotime($isc['data_inizio'])); ?>)</small>
                                        <div class="dettagli-iscrizione mt-2">
                                             <span>Partecipanti: <?php echo htmlspecialchars($isc['numero_partecipanti']); ?></span>
                                             <span>Auto: <?php echo htmlspecialchars($isc['car_marca'] . ' ' . $isc['car_modello'] . ' (' . $isc['car_targa'] . ')'); ?></span>
                                             <span>Pagamento: <span class="badge bg-<?php echo ($isc['stato_pagamento'] == 'Pagato') ? 'success' : 'warning'; ?>"><?php echo htmlspecialchars($isc['stato_pagamento']); ?></span></span>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                </div>
            </main>
            <?php include('includes/footer.php');?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script src="js/scripts.js"></script>
    <script>
        // Funzione JS compilaDatiAuto (invariata)
        function compilaDatiAuto(selectElement, idManifestazione) {
            const selectedOption = selectElement.options[selectElement.selectedIndex];
            const marcaInput = document.getElementById('car_marca_' + idManifestazione);
            const modelloInput = document.getElementById('car_modello_' + idManifestazione);
            const targaInput = document.getElementById('car_targa_' + idManifestazione);

            if (selectElement.value && selectedOption) {
                marcaInput.value = selectedOption.getAttribute('data-marca') || '';
                modelloInput.value = selectedOption.getAttribute('data-modello') || '';
                targaInput.value = selectedOption.getAttribute('data-targa') || '';
            }
        }
        // Listener DOMContentLoaded (invariato)
        document.addEventListener('DOMContentLoaded', function() {
            const selects = document.querySelectorAll('select[name="id_auto_socio"]');
            selects.forEach(select => {
                if (select.value && select.selectedIndex > 0) {
                    const idParts = select.id.split('_');
                    const idManifestazione = idParts[idParts.length - 1];
                    if (idManifestazione) {
                         compilaDatiAuto(select, idManifestazione);
                    }
                }
            });
        });
    </script>

</body>
</html>
