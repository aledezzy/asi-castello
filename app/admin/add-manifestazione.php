<?php
session_start();
include_once('../includes/config.php');

// Verifica se l'admin è loggato
if (strlen($_SESSION['adminid'] ?? 0) == 0) {
    header('location:logout.php');
    exit();
}

$messaggio = '';
$messaggio_tipo = 'info';
$errori_form = []; // Array per memorizzare errori specifici dei campi

// --- Gestione Aggiunta Manifestazione (POST) ---
if (isset($_POST['submit'])) {
    // Recupera e pulisci i dati dal form
    $titolo = trim($_POST['titolo'] ?? '');
    $data_inizio_str = trim($_POST['data_inizio'] ?? '');
    $data_chiusura_str = trim($_POST['data_chiusura_iscrizioni'] ?? '');
    $programma = trim($_POST['programma'] ?? '');
    $luogo_ritrovo = trim($_POST['luogo_ritrovo'] ?? '');
    $quota_pranzo_str = trim($_POST['quota_pranzo'] ?? '');
    $note = trim($_POST['note'] ?? '');

    // --- Validazione Input ---
    if (empty($titolo)) {
        $errori_form['titolo'] = "Il titolo è obbligatorio.";
    }
    if (empty($data_inizio_str)) {
        $errori_form['data_inizio'] = "La data di inizio è obbligatoria.";
    }
    if (empty($data_chiusura_str)) {
        $errori_form['data_chiusura_iscrizioni'] = "La data di chiusura iscrizioni è obbligatoria.";
    }

    // Conversione e validazione date
    $data_inizio = null;
    $data_chiusura = null;
    try {
        if (!empty($data_inizio_str)) {
            $data_inizio_dt = new DateTime($data_inizio_str);
            $data_inizio = $data_inizio_dt->format('Y-m-d H:i:s');
        }
        if (!empty($data_chiusura_str)) {
            $data_chiusura_dt = new DateTime($data_chiusura_str);
            $data_chiusura = $data_chiusura_dt->format('Y-m-d H:i:s');
        }
        // Controllo logico date
        if ($data_inizio_dt && $data_chiusura_dt && $data_chiusura_dt >= $data_inizio_dt) {
             $errori_form['date_logica'] = "La data di chiusura iscrizioni deve essere precedente alla data di inizio.";
        }
    } catch (Exception $e) {
        $errori_form['date_formato'] = "Formato data/ora non valido. Usa il formato YYYY-MM-DDTHH:MM.";
    }

    // Validazione quota pranzo (se inserita)
    $quota_pranzo = null;
    if (!empty($quota_pranzo_str)) {
        // Sostituisci la virgola con il punto per la validazione/conversione
        $quota_pranzo_str_norm = str_replace(',', '.', $quota_pranzo_str);
        if (!is_numeric($quota_pranzo_str_norm) || $quota_pranzo_str_norm < 0) {
            $errori_form['quota_pranzo'] = "La quota pranzo deve essere un numero positivo o zero.";
        } else {
            $quota_pranzo = (float)$quota_pranzo_str_norm;
        }
    } else {
        $quota_pranzo = 0.00; // Default a 0 se non inserita
    }

    // Se non ci sono errori, procedi con l'inserimento
    if (empty($errori_form)) {
        $stmt_insert = mysqli_prepare($con,
            "INSERT INTO manifestazioni (titolo, data_inizio, data_chiusura_iscrizioni, programma, luogo_ritrovo, quota_pranzo, note, data_creazione)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
        );

        if ($stmt_insert) {
            mysqli_stmt_bind_param($stmt_insert, "ssssdds", // s=string, d=double (per decimal/float)
                $titolo,
                $data_inizio,
                $data_chiusura,
                $programma,
                $luogo_ritrovo,
                $quota_pranzo,
                $note
            );

            if (mysqli_stmt_execute($stmt_insert)) {
                $id_nuova_manifestazione = mysqli_insert_id($con);
                mysqli_stmt_close($stmt_insert);
                // Messaggio di successo e redirect
                $msg_success = "Manifestazione (ID: " . $id_nuova_manifestazione . ") aggiunta con successo.";
                header("Location: manage-manifestazioni.php?msg=" . urlencode($msg_success) . "&msg_type=success");
                exit();
            } else {
                $messaggio = "Errore durante l'inserimento della manifestazione nel database.";
                $messaggio_tipo = 'danger';
                error_log("Admin Add Manifestazione Execute Error: " . mysqli_stmt_error($stmt_insert));
            }
            // Chiudi lo statement anche in caso di errore nell'esecuzione
             if (isset($stmt_insert) && $stmt_insert) mysqli_stmt_close($stmt_insert);
        } else {
            $messaggio = "Errore nella preparazione della query di inserimento.";
            $messaggio_tipo = 'danger';
            error_log("Admin Add Manifestazione Prepare Error: " . mysqli_error($con));
        }
    } else {
        // Se ci sono errori di validazione, costruisci un messaggio generale
        $messaggio = "Errore nel form. Controlla i campi evidenziati.";
        $messaggio_tipo = 'danger';
    }
}

mysqli_close($con);
?>
<!DOCTYPE html>
<html lang="it">
    <head>
        <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="description" content="Aggiungi Manifestazione" />
        <meta name="author" content="" />
        <title>Aggiungi Manifestazione | Sistema Admin</title>
        <link href="../css/styles.css" rel="stylesheet" />
        <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/js/all.min.js" crossorigin="anonymous"></script>
        <style>
            .is-invalid { border-color: #dc3545; }
            .invalid-feedback { color: #dc3545; display: block; font-size: 0.875em; }
        </style>
    </head>
    <body class="sb-nav-fixed">
      <?php include_once('includes/navbar.php');?>
        <div id="layoutSidenav">
         <?php include_once('includes/sidebar.php');?>
            <div id="layoutSidenav_content">
                <main>
                    <div class="container-fluid px-4">
                        <h1 class="mt-4">Aggiungi Nuova Manifestazione</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="manage-manifestazioni.php">Gestisci Manifestazioni</a></li>
                            <li class="breadcrumb-item active">Aggiungi Manifestazione</li>
                        </ol>

                        <?php if (!empty($messaggio) && $messaggio_tipo === 'danger'): // Mostra solo errori generali qui ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($messaggio); ?>
                                <?php if (!empty($errori_form)): ?>
                                    <ul>
                                        <?php foreach ($errori_form as $errore): ?>
                                            <li><?php echo htmlspecialchars($errore); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-calendar-plus me-1"></i>
                                Dettagli Manifestazione
                            </div>
                            <div class="card-body">
                                <form method="post" action="add-manifestazione.php">

                                    <div class="mb-3">
                                        <label for="titolo" class="form-label">Titolo <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control <?php echo isset($errori_form['titolo']) ? 'is-invalid' : ''; ?>" id="titolo" name="titolo" value="<?php echo htmlspecialchars($_POST['titolo'] ?? ''); ?>" required>
                                        <?php if (isset($errori_form['titolo'])): ?>
                                            <div class="invalid-feedback"><?php echo $errori_form['titolo']; ?></div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="data_inizio" class="form-label">Data e Ora Inizio <span class="text-danger">*</span></label>
                                            <input type="datetime-local" class="form-control <?php echo (isset($errori_form['data_inizio']) || isset($errori_form['date_formato']) || isset($errori_form['date_logica'])) ? 'is-invalid' : ''; ?>" id="data_inizio" name="data_inizio" value="<?php echo htmlspecialchars($_POST['data_inizio'] ?? ''); ?>" required>
                                            <?php if (isset($errori_form['data_inizio'])): ?>
                                                <div class="invalid-feedback"><?php echo $errori_form['data_inizio']; ?></div>
                                            <?php elseif (isset($errori_form['date_formato'])): ?>
                                                <div class="invalid-feedback"><?php echo $errori_form['date_formato']; ?></div>
                                            <?php elseif (isset($errori_form['date_logica'])): ?>
                                                <div class="invalid-feedback"><?php echo $errori_form['date_logica']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="data_chiusura_iscrizioni" class="form-label">Data e Ora Chiusura Iscrizioni <span class="text-danger">*</span></label>
                                            <input type="datetime-local" class="form-control <?php echo (isset($errori_form['data_chiusura_iscrizioni']) || isset($errori_form['date_formato']) || isset($errori_form['date_logica'])) ? 'is-invalid' : ''; ?>" id="data_chiusura_iscrizioni" name="data_chiusura_iscrizioni" value="<?php echo htmlspecialchars($_POST['data_chiusura_iscrizioni'] ?? ''); ?>" required>
                                             <?php if (isset($errori_form['data_chiusura_iscrizioni'])): ?>
                                                <div class="invalid-feedback"><?php echo $errori_form['data_chiusura_iscrizioni']; ?></div>
                                            <?php elseif (isset($errori_form['date_formato']) && !isset($errori_form['data_inizio'])): // Mostra solo una volta l'errore formato ?>
                                                <div class="invalid-feedback"><?php echo $errori_form['date_formato']; ?></div>
                                            <?php elseif (isset($errori_form['date_logica']) && !isset($errori_form['data_inizio'])): // Mostra solo una volta l'errore logica ?>
                                                <div class="invalid-feedback"><?php echo $errori_form['date_logica']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="luogo_ritrovo" class="form-label">Luogo Ritrovo</label>
                                        <input type="text" class="form-control" id="luogo_ritrovo" name="luogo_ritrovo" value="<?php echo htmlspecialchars($_POST['luogo_ritrovo'] ?? ''); ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label for="quota_pranzo" class="form-label">Quota Pranzo (€)</label>
                                        <input type="text" inputmode="decimal" class="form-control <?php echo isset($errori_form['quota_pranzo']) ? 'is-invalid' : ''; ?>" id="quota_pranzo" name="quota_pranzo" placeholder="Es. 25,50" value="<?php echo htmlspecialchars($_POST['quota_pranzo'] ?? ''); ?>">
                                        <?php if (isset($errori_form['quota_pranzo'])): ?>
                                            <div class="invalid-feedback"><?php echo $errori_form['quota_pranzo']; ?></div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="mb-3">
                                        <label for="programma" class="form-label">Programma</label>
                                        <textarea class="form-control" id="programma" name="programma" rows="5"><?php echo htmlspecialchars($_POST['programma'] ?? ''); ?></textarea>
                                    </div>

                                    <div class="mb-3">
                                        <label for="note" class="form-label">Note Aggiuntive</label>
                                        <textarea class="form-control" id="note" name="note" rows="3"><?php echo htmlspecialchars($_POST['note'] ?? ''); ?></textarea>
                                    </div>

                                    <div class="mt-4 d-flex justify-content-end">
                                         <a href="manage-manifestazioni.php" class="btn btn-secondary me-2">Annulla</a>
                                         <button type="submit" name="submit" class="btn btn-primary">Salva Manifestazione</button>
                                    </div>

                                </form>
                            </div>
                        </div>
                    </div>
                </main>
                <?php include('../includes/footer.php');?>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
        <script src="../js/scripts.js"></script>
        <?php // Non servono DataTables qui ?>
    </body>
</html>
