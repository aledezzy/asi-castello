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

// --- Gestione Aggiunta Socio (POST) ---
if (isset($_POST['submit'])) {
    // Recupera e pulisci i dati dal form
    $codice_fiscale = trim(strtoupper($_POST['codice_fiscale'] ?? ''));
    $nome = trim($_POST['nome'] ?? '');
    $cognome = trim($_POST['cognome'] ?? '');
    $tessera_club_numero = trim($_POST['tessera_club_numero'] ?? '');
    $tessera_club_scadenza_str = trim($_POST['tessera_club_scadenza'] ?? '');
    $has_tessera_asi = isset($_POST['has_tessera_asi']) ? 1 : 0; // Checkbox
    $tessera_asi_numero = trim($_POST['tessera_asi_numero'] ?? '');
    $note = trim($_POST['note'] ?? '');

    // --- Validazione Input ---
    if (empty($codice_fiscale)) {
        $errori_form['codice_fiscale'] = "Il Codice Fiscale è obbligatorio.";
    } elseif (!preg_match('/^[A-Z]{6}[0-9LMNPQRSTUV]{2}[A-Z]{1}[0-9LMNPQRSTUV]{2}[A-Z]{1}[0-9LMNPQRSTUV]{3}[A-Z]{1}$/i', $codice_fiscale)) {
        // Regex base per formato CF (potrebbe essere migliorata)
        $errori_form['codice_fiscale'] = "Formato Codice Fiscale non valido.";
    } else {
        // Controlla unicità Codice Fiscale
        $stmt_check_cf = mysqli_prepare($con, "SELECT id FROM soci WHERE codice_fiscale = ?");
        if ($stmt_check_cf) {
            mysqli_stmt_bind_param($stmt_check_cf, "s", $codice_fiscale);
            mysqli_stmt_execute($stmt_check_cf);
            mysqli_stmt_store_result($stmt_check_cf);
            if (mysqli_stmt_num_rows($stmt_check_cf) > 0) {
                $errori_form['codice_fiscale'] = "Questo Codice Fiscale è già presente nel database.";
            }
            mysqli_stmt_close($stmt_check_cf);
        } else {
             $errori_form['db_check'] = "Errore nel controllo del Codice Fiscale.";
             error_log("Add Socio - Check CF Prepare Error: " . mysqli_error($con));
        }
    }

    if (empty($nome)) {
        $errori_form['nome'] = "Il nome è obbligatorio.";
    }
    if (empty($cognome)) {
        $errori_form['cognome'] = "Il cognome è obbligatorio.";
    }

    // Validazione data scadenza tessera club (se inserita)
    $tessera_club_scadenza = null;
    if (!empty($tessera_club_scadenza_str)) {
        try {
            $tessera_club_scadenza_dt = new DateTime($tessera_club_scadenza_str);
            $tessera_club_scadenza = $tessera_club_scadenza_dt->format('Y-m-d');
        } catch (Exception $e) {
            $errori_form['tessera_club_scadenza'] = "Formato data scadenza tessera club non valido. Usa YYYY-MM-DD.";
        }
    }

    // Se non ci sono errori, procedi con l'inserimento
    if (empty($errori_form)) {
        $stmt_insert = mysqli_prepare($con,
            "INSERT INTO soci (codice_fiscale, nome, cognome, tessera_club_numero, tessera_club_scadenza, has_tessera_asi, tessera_asi_numero, note, data_iscrizione_club)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        );

        if ($stmt_insert) {
            mysqli_stmt_bind_param($stmt_insert, "sssssiss", // s=string, i=integer
                $codice_fiscale,
                $nome,
                $cognome,
                $tessera_club_numero,
                $tessera_club_scadenza, // Può essere NULL
                $has_tessera_asi,
                $tessera_asi_numero,
                $note
            );

            if (mysqli_stmt_execute($stmt_insert)) {
                $id_nuovo_socio = mysqli_insert_id($con);
                mysqli_stmt_close($stmt_insert);
                // Messaggio di successo e redirect
                $msg_success = "Socio (ID: " . $id_nuovo_socio . ") aggiunto con successo.";
                header("Location: gestisci-soci.php?msg=" . urlencode($msg_success) . "&msg_type=success");
                exit();
            } else {
                // Controlla errore duplicato CF (anche se già verificato)
                if (mysqli_errno($con) == 1062) {
                     $messaggio = "Errore: Codice Fiscale già esistente.";
                     $errori_form['codice_fiscale'] = "Questo Codice Fiscale è già presente nel database.";
                } else {
                    $messaggio = "Errore durante l'inserimento del socio nel database.";
                    error_log("Admin Add Socio Execute Error: " . mysqli_stmt_error($stmt_insert));
                }
                $messaggio_tipo = 'danger';
            }
             // Chiudi lo statement anche in caso di errore nell'esecuzione
             if (isset($stmt_insert) && $stmt_insert) mysqli_stmt_close($stmt_insert);
        } else {
            $messaggio = "Errore nella preparazione della query di inserimento.";
            $messaggio_tipo = 'danger';
            error_log("Admin Add Socio Prepare Error: " . mysqli_error($con));
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
        <meta name="description" content="Aggiungi Socio" />
        <meta name="author" content="" />
        <title>Aggiungi Socio | Sistema Admin</title>
        <link href="../css/styles.css" rel="stylesheet" />
        <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/js/all.min.js" crossorigin="anonymous"></script>
        <style>
            .is-invalid { border-color: #dc3545; }
            .invalid-feedback { color: #dc3545; display: block; font-size: 0.875em; }
            .form-check-input.is-invalid ~ .form-check-label { color: #dc3545; }
            .form-check-input.is-invalid { border-color: #dc3545; }
        </style>
    </head>
    <body class="sb-nav-fixed">
      <?php include_once('includes/navbar.php');?>
        <div id="layoutSidenav">
         <?php include_once('includes/sidebar.php');?>
            <div id="layoutSidenav_content">
                <main>
                    <div class="container-fluid px-4">
                        <h1 class="mt-4">Aggiungi Nuovo Socio</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="gestisci-soci.php">Gestisci Soci</a></li>
                            <li class="breadcrumb-item active">Aggiungi Socio</li>
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
                                <i class="fas fa-user-plus me-1"></i>
                                Dettagli Socio
                            </div>
                            <div class="card-body">
                                <form method="post" action="add-socio.php">

                                    <div class="mb-3">
                                        <label for="codice_fiscale" class="form-label">Codice Fiscale <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control <?php echo isset($errori_form['codice_fiscale']) ? 'is-invalid' : ''; ?>" id="codice_fiscale" name="codice_fiscale" value="<?php echo htmlspecialchars($_POST['codice_fiscale'] ?? ''); ?>" required maxlength="16" style="text-transform: uppercase;">
                                        <?php if (isset($errori_form['codice_fiscale'])): ?>
                                            <div class="invalid-feedback"><?php echo $errori_form['codice_fiscale']; ?></div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="cognome" class="form-label">Cognome <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control <?php echo isset($errori_form['cognome']) ? 'is-invalid' : ''; ?>" id="cognome" name="cognome" value="<?php echo htmlspecialchars($_POST['cognome'] ?? ''); ?>" required>
                                            <?php if (isset($errori_form['cognome'])): ?>
                                                <div class="invalid-feedback"><?php echo $errori_form['cognome']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="nome" class="form-label">Nome <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control <?php echo isset($errori_form['nome']) ? 'is-invalid' : ''; ?>" id="nome" name="nome" value="<?php echo htmlspecialchars($_POST['nome'] ?? ''); ?>" required>
                                             <?php if (isset($errori_form['nome'])): ?>
                                                <div class="invalid-feedback"><?php echo $errori_form['nome']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                     <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="tessera_club_numero" class="form-label">Numero Tessera Club</label>
                                            <input type="text" class="form-control" id="tessera_club_numero" name="tessera_club_numero" value="<?php echo htmlspecialchars($_POST['tessera_club_numero'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="tessera_club_scadenza" class="form-label">Scadenza Tessera Club</label>
                                            <input type="date" class="form-control <?php echo isset($errori_form['tessera_club_scadenza']) ? 'is-invalid' : ''; ?>" id="tessera_club_scadenza" name="tessera_club_scadenza" value="<?php echo htmlspecialchars($_POST['tessera_club_scadenza'] ?? ''); ?>">
                                             <?php if (isset($errori_form['tessera_club_scadenza'])): ?>
                                                <div class="invalid-feedback"><?php echo $errori_form['tessera_club_scadenza']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="row mb-3 align-items-center">
                                        <div class="col-md-6">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" value="1" id="has_tessera_asi" name="has_tessera_asi" <?php echo isset($_POST['has_tessera_asi']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="has_tessera_asi">
                                                    Ha Tessera ASI
                                                </label>
                                            </div>
                                        </div>
                                         <div class="col-md-6">
                                            <label for="tessera_asi_numero" class="form-label visually-hidden">Numero Tessera ASI</label> <?php // Label nascosta ma presente per accessibilità ?>
                                            <input type="text" class="form-control" id="tessera_asi_numero" name="tessera_asi_numero" placeholder="Numero Tessera ASI (se presente)" value="<?php echo htmlspecialchars($_POST['tessera_asi_numero'] ?? ''); ?>">
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="note" class="form-label">Note Aggiuntive</label>
                                        <textarea class="form-control" id="note" name="note" rows="3"><?php echo htmlspecialchars($_POST['note'] ?? ''); ?></textarea>
                                    </div>

                                    <div class="mt-4 d-flex justify-content-end">
                                         <a href="gestisci-soci.php" class="btn btn-secondary me-2">Annulla</a>
                                         <button type="submit" name="submit" class="btn btn-primary">Salva Socio</button>
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
    </body>
</html>
