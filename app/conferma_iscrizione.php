<?php
session_start();
// Usa il file di configurazione corretto che definisce $con
require_once 'includes/config.php';

// Verifica se l'utente è loggato usando $_SESSION['id']
if (!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit;
}

$id_utente_loggato = $_SESSION['id'];
$messaggio = ''; // Per mostrare messaggi di successo o errore
$messaggio_tipo = 'info'; // Per lo stile del messaggio (success, danger, info)
$show_form = false; // Controlla se mostrare il form OTP
$iscrizione_id = null;
$titolo_manifestazione = ''; // Per mostrare a cosa ci si sta iscrivendo

// --- Recupera ID Iscrizione dalla URL ---
$iscrizione_id = filter_input(INPUT_GET, 'iscrizione_id', FILTER_VALIDATE_INT);

if (!$iscrizione_id) {
    $messaggio = "ID iscrizione non valido o mancante.";
    $messaggio_tipo = 'danger';
} else {
    // --- Gestione Invio OTP (POST) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['conferma_otp'])) {
        $submitted_otp = trim($_POST['otp_code'] ?? '');
        $hidden_iscrizione_id = filter_input(INPUT_POST, 'iscrizione_id', FILTER_VALIDATE_INT); // Prendi ID dal form

        // Validazione OTP inserito
        if (!preg_match('/^\d{6}$/', $submitted_otp)) {
            $messaggio = "Il codice OTP deve essere di 6 cifre numeriche.";
            $messaggio_tipo = 'danger';
            $show_form = true; // Mostra di nuovo il form
        } elseif ($hidden_iscrizione_id !== $iscrizione_id) {
            // Controllo di sicurezza aggiuntivo
            $messaggio = "Errore di validazione del form. Riprova.";
            $messaggio_tipo = 'danger';
            $show_form = false; // Non mostrare il form se c'è discrepanza
        } else {
            // 1. Recupera i dettagli dell'iscrizione PENDENTE dal DB
            $stmt_check = mysqli_prepare($con,
                "SELECT i.otp_codice, i.otp_expires, i.otp_confirmed, m.titolo
                 FROM iscrizioni_manifestazioni i
                 JOIN manifestazioni m ON i.id_manifestazione = m.id
                 WHERE i.id = ? AND i.id_user = ?"
            );
            if ($stmt_check) {
                mysqli_stmt_bind_param($stmt_check, "ii", $iscrizione_id, $id_utente_loggato);
                mysqli_stmt_execute($stmt_check);
                $result_check = mysqli_stmt_get_result($stmt_check);
                $iscrizione_data = mysqli_fetch_assoc($result_check);
                mysqli_stmt_close($stmt_check);

                if ($iscrizione_data) {
                    $titolo_manifestazione = $iscrizione_data['titolo']; // Recupera titolo per messaggi

                    if ($iscrizione_data['otp_confirmed'] == 1) {
                        $messaggio = "Questa iscrizione è già stata confermata.";
                        $messaggio_tipo = 'warning';
                        $show_form = false;
                    } else {
                        // 2. Controlla la scadenza
                        $otp_expiry_dt = new DateTime($iscrizione_data['otp_expires']);
                        $now_dt = new DateTime();

                        if ($now_dt > $otp_expiry_dt) {
                            $messaggio = "Il codice OTP è scaduto. Devi ripetere la procedura di iscrizione dalla pagina delle manifestazioni.";
                            $messaggio_tipo = 'danger';
                            $show_form = false;
                            // Opzionale: potresti cancellare l'iscrizione scaduta qui
                            // mysqli_query($con, "DELETE FROM iscrizioni_manifestazioni WHERE id = $iscrizione_id");
                        } else {
                            // 3. Verifica l'OTP
                            $otp_hash_db = $iscrizione_data['otp_codice'];
                            if (password_verify($submitted_otp, $otp_hash_db)) {
                                // OTP Corretto! Conferma l'iscrizione
                                $stmt_update = mysqli_prepare($con,
                                    "UPDATE iscrizioni_manifestazioni
                                     SET otp_confirmed = 1, otp_codice = NULL, otp_expires = NULL
                                     WHERE id = ?"
                                );
                                if ($stmt_update) {
                                    mysqli_stmt_bind_param($stmt_update, "i", $iscrizione_id);
                                    if (mysqli_stmt_execute($stmt_update)) {
                                        $messaggio = "Iscrizione alla manifestazione '" . htmlspecialchars($titolo_manifestazione) . "' confermata con successo!";
                                        $messaggio_tipo = 'success';
                                        $show_form = false; // Nascondi form dopo successo
                                    } else {
                                        $messaggio = "Errore durante la conferma dell'iscrizione nel database.";
                                        $messaggio_tipo = 'danger';
                                        error_log("Errore conferma OTP (Update DB): " . mysqli_stmt_error($stmt_update));
                                        $show_form = true; // Riprova?
                                    }
                                    mysqli_stmt_close($stmt_update);
                                } else {
                                    $messaggio = "Errore preparazione query di conferma.";
                                    $messaggio_tipo = 'danger';
                                    error_log("Errore preparazione conferma OTP: " . mysqli_error($con));
                                    $show_form = true;
                                }
                            } else {
                                // OTP Errato
                                $messaggio = "Codice OTP errato. Riprova.";
                                $messaggio_tipo = 'danger';
                                $show_form = true; // Mostra di nuovo il form
                            }
                        }
                    }
                } else {
                    // Iscrizione non trovata o non appartenente all'utente
                    $messaggio = "Iscrizione non trovata o non valida.";
                    $messaggio_tipo = 'danger';
                    $show_form = false;
                }
            } else {
                $messaggio = "Errore nella verifica dell'iscrizione.";
                $messaggio_tipo = 'danger';
                error_log("Errore verifica iscrizione per OTP: " . mysqli_error($con));
                $show_form = false;
            }
        }
    } else {
        // --- Caricamento Iniziale Pagina (GET) ---
        $stmt_get = mysqli_prepare($con,
            "SELECT i.otp_confirmed, i.otp_expires, m.titolo
             FROM iscrizioni_manifestazioni i
             JOIN manifestazioni m ON i.id_manifestazione = m.id
             WHERE i.id = ? AND i.id_user = ?"
        );
        if ($stmt_get) {
            mysqli_stmt_bind_param($stmt_get, "ii", $iscrizione_id, $id_utente_loggato);
            mysqli_stmt_execute($stmt_get);
            $result_get = mysqli_stmt_get_result($stmt_get);
            $iscrizione_data_get = mysqli_fetch_assoc($result_get);
            mysqli_stmt_close($stmt_get);

            if ($iscrizione_data_get) {
                $titolo_manifestazione = $iscrizione_data_get['titolo'];

                if ($iscrizione_data_get['otp_confirmed'] == 1) {
                    $messaggio = "Questa iscrizione è già stata confermata.";
                    $messaggio_tipo = 'warning';
                    $show_form = false;
                } else {
                    $otp_expiry_dt = new DateTime($iscrizione_data_get['otp_expires']);
                    $now_dt = new DateTime();

                    if ($now_dt > $otp_expiry_dt) {
                        $messaggio = "Il codice OTP per questa iscrizione è scaduto. Devi ripetere la procedura di iscrizione dalla pagina delle manifestazioni.";
                        $messaggio_tipo = 'danger';
                        $show_form = false;
                        // Opzionale: cancellare iscrizione scaduta
                        // mysqli_query($con, "DELETE FROM iscrizioni_manifestazioni WHERE id = $iscrizione_id");
                    } else {
                        // Tutto ok, mostra il form
                        $messaggio = "Inserisci il codice OTP a 6 cifre che hai ricevuto via email per confermare l'iscrizione a: <strong>" . htmlspecialchars($titolo_manifestazione) . "</strong>.";
                        $messaggio_tipo = 'info';
                        $show_form = true;
                    }
                }
            } else {
                $messaggio = "Iscrizione non trovata o non valida.";
                $messaggio_tipo = 'danger';
                $show_form = false;
            }
        } else {
            $messaggio = "Errore nel recupero dei dettagli dell'iscrizione.";
            $messaggio_tipo = 'danger';
            error_log("Errore recupero dettagli iscrizione per OTP (GET): " . mysqli_error($con));
            $show_form = false;
        }
    }
} // Fine controllo $iscrizione_id valido

mysqli_close($con); // Chiudi la connessione al database
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conferma Iscrizione Manifestazione</title>
    <link href="css/styles.css" rel="stylesheet" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/js/all.min.js" crossorigin="anonymous"></script>
    <style>
        body { padding-top: 56px; }
        .container-confirm { max-width: 600px; margin: 40px auto; padding: 20px; }
        .otp-input {
            width: 80px;
            height: 50px;
            font-size: 1.5rem;
            text-align: center;
            letter-spacing: 5px;
        }
    </style>
</head>
<body class="sb-nav-fixed">

    <?php include_once 'includes/navbar.php';?>

    <div id="layoutSidenav">
        <?php include_once 'includes/sidebar.php';?>

        <div id="layoutSidenav_content">
            <main>
                <div class="container-fluid px-4 container-confirm">

                    <h1 class="mt-4">Conferma Iscrizione</h1>
                     <ol class="breadcrumb mb-4">
                        <li class="breadcrumb-item"><a href="welcome.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="manifestazioni.php">Manifestazioni</a></li>
                        <li class="breadcrumb-item active">Conferma Iscrizione</li>
                    </ol>

                    <div class="card shadow-lg border-0 rounded-lg">
                        <div class="card-header"><h3 class="text-center font-weight-light my-4">Verifica Codice OTP</h3></div>
                        <div class="card-body">

                            <?php if ($messaggio): ?>
                                <div class="alert alert-<?php echo $messaggio_tipo; ?>" role="alert">
                                    <?php echo $messaggio; // L'HTML è già gestito ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($show_form): ?>
                                <form method="post" action="conferma_iscrizione.php?iscrizione_id=<?php echo $iscrizione_id; // Mantieni ID in URL per sicurezza ?>">
                                    <input type="hidden" name="iscrizione_id" value="<?php echo $iscrizione_id; ?>">
                                    <div class="form-floating mb-3 text-center">
                                        <input class="form-control otp-input mx-auto" id="otp_code" name="otp_code" type="text" inputmode="numeric" pattern="\d{6}" maxlength="6" placeholder="123456" required autofocus />
                                        <label for="otp_code" style="left: auto; right: auto;">Codice OTP a 6 cifre</label>
                                    </div>
                                    <div class="d-flex align-items-center justify-content-center mt-4 mb-0">
                                        <button class="btn btn-primary" type="submit" name="conferma_otp">Conferma Iscrizione</button>
                                    </div>
                                </form>
                            <?php elseif ($messaggio_tipo === 'success'): ?>
                                <div class="text-center">
                                    <a href="iscrizioni.php" class="btn btn-success">Visualizza le tue iscrizioni</a>
                                    <a href="manifestazioni.php" class="btn btn-secondary">Torna alle manifestazioni</a>
                                </div>
                            <?php else: // Errori che non mostrano il form ?>
                                 <div class="text-center">
                                    <a href="manifestazioni.php" class="btn btn-primary">Torna alle manifestazioni</a>
                                </div>
                            <?php endif; ?>

                        </div>
                        <div class="card-footer text-center py-3">
                            <div class="small">Se non hai ricevuto l'email, controlla la cartella spam o <a href="manifestazioni.php">riprova l'iscrizione</a>.</div>
                        </div>
                    </div>

                </div>
            </main>
            <?php include 'includes/footer.php';?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script src="js/scripts.js"></script>

</body>
</html>
