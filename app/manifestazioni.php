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
if ($id_socio_utente !== null) { // Recupera le auto SOLO se l'utente è un socio
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
    // Recupera l'ID auto selezionato SOLO se l'utente è socio
    $id_auto_socio_selezionata = ($id_socio_utente !== null) ? filter_input(INPUT_POST, 'id_auto_socio', FILTER_VALIDATE_INT) : null;

    // Validazione di base
    if (!$id_manifestazione_selezionata) { $errore_campi['generale'] = "Manifestazione non valida.";}
    if (!$numero_partecipanti) { $errore_campi['numero_partecipanti'] = "Numero partecipanti non valido (minimo 1)."; }
    if (empty($car_marca)) { $errore_campi['car_marca'] = "Marca auto obbligatoria."; }
    if (empty($car_modello)) { $errore_campi['car_modello'] = "Modello auto obbligatorio."; }
    if (empty($car_targa)) { $errore_campi['car_targa'] = "Targa auto obbligatoria."; }
    // Aggiungi qui validazione per auto d'epoca se necessario (es. anno immatricolazione)

    if (empty($errore_campi)) {
        if (!$is_user_active) {
            $messaggio = "Il tuo account non è attivo. Contatta l'amministrazione.";
            $messaggio_tipo = 'danger';
        } else {
            // 1. Verifica se la manifestazione esiste e se le iscrizioni sono aperte
            $stmt_check_manif = mysqli_prepare($con, "SELECT data_chiusura_iscrizioni, titolo FROM manifestazioni WHERE id = ?");
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
                    $titolo_manifestazione = $manifestazione['titolo'];
                    mysqli_stmt_close($stmt_check_manif);

                    $data_chiusura = new DateTime($manifestazione['data_chiusura_iscrizioni']);
                    $ora_attuale = new DateTime();

                    if ($ora_attuale <= $data_chiusura) {
                        // 2. Verifica se l'utente ha già un'iscrizione CONFERMATA o PENDENTE
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
                                    $id_iscrizione_pendente = $iscrizione_esistente['id'];
                                    header("Location: conferma_iscrizione.php?iscrizione_id=" . $id_iscrizione_pendente . "&pending=1");
                                    exit;
                                }
                            } else {
                                // Nessuna iscrizione esistente, procedi
                                try {
                                    // 3. Genera OTP e hash
                                    $otp_plain = sprintf("%06d", random_int(0, 999999));
                                    $otp_hash = password_hash($otp_plain, PASSWORD_DEFAULT);
                                    $otp_expiry = date("Y-m-d H:i:s", time() + 900);

                                    // 4. Inserisci iscrizione preliminare
                                    $stmt_insert = mysqli_prepare($con,
                                        "INSERT INTO iscrizioni_manifestazioni
                                        (id_manifestazione, id_user, numero_partecipanti, data_iscrizione, stato_pagamento,
                                         car_marca, car_modello, car_targa, id_auto_socio, note_iscrizione,
                                         otp_codice, otp_expires, otp_confirmed)
                                        VALUES (?, ?, ?, NOW(), 'In attesa', ?, ?, ?, ?, ?, ?, ?, 0)"
                                    );

                                    // Imposta id_auto_socio a NULL se non è stato selezionato o se l'utente non è socio
                                    $id_auto_da_inserire = ($id_auto_socio_selezionata > 0 && $id_socio_utente !== null) ? $id_auto_socio_selezionata : null;

                                    if (!$stmt_insert) {
                                        throw new Exception("Errore preparazione query inserimento: " . mysqli_error($con));
                                    }

                                    mysqli_stmt_bind_param(
                                        $stmt_insert,
                                        "iiisssisss",
                                        $id_manifestazione_selezionata,
                                        $id_utente_loggato,
                                        $numero_partecipanti,
                                        $car_marca,
                                        $car_modello,
                                        $car_targa,
                                        $id_auto_da_inserire, // Può essere NULL
                                        $note_iscrizione,
                                        $otp_hash,
                                        $otp_expiry
                                    );

                                    if (mysqli_stmt_execute($stmt_insert)) {
                                        $id_nuova_iscrizione = mysqli_insert_id($con);
                                        mysqli_stmt_close($stmt_insert);

                                        // 5. Invia l'email con l'OTP
                                        $mail = new PHPMailer(true);
                                        try {
                                            // ... (configurazione PHPMailer invariata) ...
                                            $mail->isSMTP();
                                            $mail->Host = SMTP_HOST;
                                            $mail->SMTPAuth = true;
                                            $mail->Username = SMTP_USER;
                                            $mail->Password = SMTP_PASS;
                                            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                                            $mail->Port = SMTP_PORT;
                                            $mail->CharSet = 'UTF-8';
                                            $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
                                            $mail->addAddress($email_utente, $nome_utente);
                                            $mail->isHTML(true);
                                            $mail->Subject = 'Conferma Iscrizione Manifestazione: ' . htmlspecialchars($titolo_manifestazione);
                                            $confirmLinkBase = APP_BASE_URL;
                                            $confirmLink = $confirmLinkBase . "conferma_iscrizione.php?iscrizione_id=" . $id_nuova_iscrizione;
                                            // ... (corpo email invariato, usa $otp_plain e $confirmLink) ...
                                            $bodyContent = "Gentile " . htmlspecialchars($nome_utente) . ",<br><br>";
                                            $bodyContent .= "Grazie per esserti pre-iscritto alla manifestazione: <strong>" . htmlspecialchars($titolo_manifestazione) . "</strong>.<br>";
                                            $bodyContent .= "Per confermare la tua iscrizione, inserisci il seguente codice OTP nella pagina di conferma:<br><br>";
                                            $bodyContent .= "<strong style='font-size: 1.5em; letter-spacing: 2px;'>" . $otp_plain . "</strong><br><br>";
                                            $bodyContent .= "Questo codice scadrà tra 15 minuti.<br><br>";
                                            $bodyContent .= "Puoi confermare la tua iscrizione qui: ";
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
                                            error_log("Mailer Error [Manifestazione OTP]: " . $mail->ErrorInfo);
                                            $messaggio = "Iscrizione preliminare creata, ma errore nell'invio dell'email di conferma. Contatta l'assistenza. Dettagli: {$mail->ErrorInfo}";
                                            $messaggio_tipo = 'danger';
                                        }

                                    } else {
                                        if (mysqli_errno($con) == 1062) {
                                             throw new Exception("Risulti già pre-iscritto o iscritto a questa manifestazione.");
                                        } else {
                                             throw new Exception("Errore durante la creazione dell'iscrizione preliminare: " . mysqli_stmt_error($stmt_insert));
                                        }
                                        
                                    }

                                } catch (Exception $e) {
                                    $messaggio = "Si è verificato un errore: " . $e->getMessage();
                                    $messaggio_tipo = 'danger';
                                    error_log("Errore Iscrizione Manifestazione: " . $e->getMessage());
                                }
                            }
                        }
                    } else {
                        $messaggio = "Le iscrizioni per questa manifestazione sono chiuse.";
                        $messaggio_tipo = 'warning';
                    }
                } else {
                     if ($stmt_check_manif) {mysqli_stmt_close($stmt_check_manif);}
                    $messaggio = "Manifestazione non trovata.";
                    $messaggio_tipo = 'danger';
                }
            }
        }
    } else {
        $messaggio = "Errore nel form:<ul>";
        foreach ($errore_campi as $campo => $err) {
            $messaggio .= "<li>" . htmlspecialchars($err) . "</li>";
        }
        $messaggio .= "</ul>";
        $messaggio_tipo = 'danger';
    }
} // Fine gestione POST


// --- Recupero Manifestazioni Disponibili ---
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
$sql_mie_iscrizioni = "SELECT m.id, m.titolo, m.data_inizio, i.numero_partecipanti, i.car_marca, i.car_modello, i.car_targa, i.stato_pagamento
                      FROM iscrizioni_manifestazioni i
                      JOIN manifestazioni m ON i.id_manifestazione = m.id
                      WHERE i.id_user = ? AND i.otp_confirmed = 1
                      ORDER BY m.data_inizio ASC";
$stmt_mie = mysqli_prepare($con, $sql_mie_iscrizioni);
$mie_iscrizioni_confermate = [];
if($stmt_mie) {
    mysqli_stmt_bind_param($stmt_mie, "i", $id_utente_loggato);
    mysqli_stmt_execute($stmt_mie);
    $result_mie = mysqli_stmt_get_result($stmt_mie);
    while ($row = mysqli_fetch_assoc($result_mie)) {
        $mie_iscrizioni_confermate[$row['id']] = $row;
    }
    mysqli_stmt_close($stmt_mie);
} else {
    $messaggio .= "<br>Errore nella preparazione query mie iscrizioni confermate: " . mysqli_error($con);
    if($messaggio_tipo != 'danger') {$messaggio_tipo = 'warning';}
    error_log("Errore preparazione query mie iscrizioni confermate: " . mysqli_error($con));
}

// --- Recupero Date Manifestazioni per Calendario ---
$event_dates = [];
$sql_event_dates = "SELECT DISTINCT DATE(data_inizio) as event_date FROM manifestazioni ORDER BY event_date ASC";
$result_event_dates = mysqli_query($con, $sql_event_dates);
if ($result_event_dates) {
    while ($row_date = mysqli_fetch_assoc($result_event_dates)) {
        $event_dates[] = $row_date['event_date']; // Formato YYYY-MM-DD
    }
    mysqli_free_result($result_event_dates);
} else {
    error_log("Errore query date manifestazioni per calendario: " . mysqli_error($con));
}

mysqli_close($con);
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
        body { padding-top: 56px; }
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
        /* Stili Calendario */
        #event-calendar-container { margin-top: 40px; padding-top: 20px; border-top: 2px solid #eee; }
        .calendar { width: 100%; max-width: 600px; margin: 1em auto; border: 1px solid #ccc; }
        .calendar-header { display: flex; justify-content: space-between; align-items: center; padding: 10px; background-color: #f0f0f0; border-bottom: 1px solid #ccc; }
        .calendar-header button { background: none; border: none; font-size: 1.2em; cursor: pointer; }
        .calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 1px; background-color: #ccc; }
        .calendar-day-header, .calendar-day { padding: 10px 5px; text-align: center; background-color: #fff; font-size: 0.9em; }
        .calendar-day-header { font-weight: bold; background-color: #f8f9fa; }
        .calendar-day.other-month { color: #aaa; background-color: #f8f9fa; }
        .calendar-day.has-event { background-color: #d4edda; /* Verde chiaro */ font-weight: bold; position: relative; }
        .calendar-day.has-event::after {
            /* Optional: add a small dot */
            /* content: '';
            position: absolute;
            bottom: 4px;
            left: 50%;
            transform: translateX(-50%);
            width: 5px;
            height: 5px;
            background-color: #155724;
            border-radius: 50%; */
        }
        .hidden { display: none; }
    </style>
</head>
<body class="sb-nav-fixed">

    <?php include_once 'includes/navbar.php';?>

    <div id="layoutSidenav">
        <?php include_once 'includes/sidebar.php';?>

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
                            <?php echo $messaggio; ?>
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
                                    $gia_iscritto_confermato = isset($mie_iscrizioni_confermate[$id_manifestazione_corrente]);
                                    ?>

                                    <?php if ($gia_iscritto_confermato): ?>
                                        <p class="gia-iscritto mt-3">Sei già iscritto a questa manifestazione.</p>
                                        <?php $dettagli = $mie_iscrizioni_confermate[$id_manifestazione_corrente]; ?>
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

                                            <?php // Mostra il dropdown auto SOLO se l'utente è socio ?>
                                            <?php if ($id_socio_utente !== null && !empty($auto_socio)): ?>
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
                                            <?php elseif ($id_socio_utente === null): ?>
                                                <p class="text-muted small">Come non socio, inserisci i dati della tua auto d'epoca qui sotto.</p>
                                            <?php endif; ?>

                                            <?php // Campi manuali auto sempre visibili ?>
                                            <div id="dati-auto-manuali-<?php echo $id_manifestazione_corrente; ?>">
                                                <div class="mb-3 <?php echo isset($errore_campi['car_marca']) ? 'campo-errore' : ''; ?>">
                                                    <label for="car_marca_<?php echo $id_manifestazione_corrente; ?>" class="form-label">Marca Auto <span class="text-danger">*</span>:</label>
                                                    <input type="text" class="form-control" id="car_marca_<?php echo $id_manifestazione_corrente; ?>" name="car_marca" value="<?php echo isset($_POST['car_marca']) && ($_POST['id_manifestazione'] ?? null) == $id_manifestazione_corrente ? htmlspecialchars($_POST['car_marca']) : ''; ?>" required>
                                                </div>

                                                <div class="mb-3 <?php echo isset($errore_campi['car_modello']) ? 'campo-errore' : ''; ?>">
                                                    <label for="car_modello_<?php echo $id_manifestazione_corrente; ?>" class="form-label">Modello Auto <span class="text-danger">*</span>:</label>
                                                    <input type="text" class="form-control" id="car_modello_<?php echo $id_manifestazione_corrente; ?>" name="car_modello" value="<?php echo isset($_POST['car_modello']) && ($_POST['id_manifestazione'] ?? null) == $id_manifestazione_corrente ? htmlspecialchars($_POST['car_modello']) : ''; ?>" required>
                                                </div>

                                                <div class="mb-3 <?php echo isset($errore_campi['car_targa']) ? 'campo-errore' : ''; ?>">
                                                    <label for="car_targa_<?php echo $id_manifestazione_corrente; ?>" class="form-label">Targa Auto <span class="text-danger">*</span>:</label>
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


                    <?php // Sezione "Le Tue Iscrizioni Confermate" (invariata) ?>
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

                    <!-- === INIZIO SEZIONE CALENDARIO === -->
                    <div id="event-calendar-container">
                        <h2>Calendario Manifestazioni</h2>
                        <div class="calendar">
                            <div class="calendar-header">
                                <button id="prev-month">&lt;</button>
                                <span id="current-month-year"></span>
                                <button id="next-month">&gt;</button>
                            </div>
                            <div class="calendar-grid" id="calendar-days-header">
                                <!-- Header giorni settimana (generato da JS) -->
                            </div>
                            <div class="calendar-grid" id="calendar-body">
                                <!-- Giorni del mese (generati da JS) -->
                            </div>
                        </div>
                    </div>
                    <!-- === FINE SEZIONE CALENDARIO === -->

                </div>
            </main>
            <?php include 'includes/footer.php';?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script src="js/scripts.js"></script>
    <script>
        // Passa le date degli eventi a JavaScript
        // Formato date atteso da JS: YYYY-MM-DD
        const eventDates = <?php echo json_encode($event_dates); ?>;

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
            // Se si deseleziona, si potrebbero svuotare i campi manuali, ma forse è meglio lasciarli
            // else {
            //     marcaInput.value = '';
            //     modelloInput.value = '';
            //     targaInput.value = '';
            // }
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

        // === INIZIO SCRIPT CALENDARIO ===
        document.addEventListener('DOMContentLoaded', function() {
            const calendarBody = document.getElementById('calendar-body');
            const calendarDaysHeader = document.getElementById('calendar-days-header');
            const currentMonthYear = document.getElementById('current-month-year');
            const prevMonthBtn = document.getElementById('prev-month');
            const nextMonthBtn = document.getElementById('next-month');

            let currentDate = new Date();

            const monthNames = ["Gennaio", "Febbraio", "Marzo", "Aprile", "Maggio", "Giugno",
                                "Luglio", "Agosto", "Settembre", "Ottobre", "Novembre", "Dicembre"];
            const dayNames = ["Dom", "Lun", "Mar", "Mer", "Gio", "Ven", "Sab"];

            function renderCalendar(date) {
                calendarBody.innerHTML = ''; // Pulisci calendario
                calendarDaysHeader.innerHTML = ''; // Pulisci header giorni
                const year = date.getFullYear();
                const month = date.getMonth(); // 0-11

                currentMonthYear.textContent = `${monthNames[month]} ${year}`;

                // Render header giorni settimana
                dayNames.forEach(day => {
                    const dayHeaderEl = document.createElement('div');
                    dayHeaderEl.classList.add('calendar-day-header');
                    dayHeaderEl.textContent = day;
                    calendarDaysHeader.appendChild(dayHeaderEl);
                });

                const firstDayOfMonth = new Date(year, month, 1);
                const lastDayOfMonth = new Date(year, month + 1, 0);
                const daysInMonth = lastDayOfMonth.getDate();
                const startDayOfWeek = firstDayOfMonth.getDay(); // 0=Domenica, 1=Lunedì...

                // Aggiungi spazi vuoti per i giorni del mese precedente
                for (let i = 0; i < startDayOfWeek; i++) {
                    const emptyCell = document.createElement('div');
                    emptyCell.classList.add('calendar-day', 'other-month');
                    calendarBody.appendChild(emptyCell);
                }

                // Render giorni del mese corrente
                for (let day = 1; day <= daysInMonth; day++) {
                    const dayCell = document.createElement('div');
                    dayCell.classList.add('calendar-day');
                    dayCell.textContent = day;

                    const currentDayStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;

                    // Controlla se c'è un evento in questo giorno
                    if (eventDates.includes(currentDayStr)) {
                        dayCell.classList.add('has-event');
                        dayCell.title = 'Manifestazione in questo giorno'; // Tooltip
                    }

                    calendarBody.appendChild(dayCell);
                }
            }

            prevMonthBtn.addEventListener('click', () => {
                currentDate.setMonth(currentDate.getMonth() - 1);
                renderCalendar(currentDate);
            });

            nextMonthBtn.addEventListener('click', () => {
                currentDate.setMonth(currentDate.getMonth() + 1);
                renderCalendar(currentDate);
            });

            renderCalendar(currentDate); // Render iniziale
        });
        // === FINE SCRIPT CALENDARIO ===
    </script>

</body>
</html>
