<?php
session_start();
include_once 'includes/config.php';

// Verifica se l'utente è loggato
if (strlen($_SESSION['id'] ?? 0) == 0) {
    header('location:logout.php');
    exit();
}

$id_utente_loggato = $_SESSION['id'];
$messaggio = '';
$messaggio_tipo = 'info';
$errori_form = []; // Array per memorizzare errori specifici dei campi
$user_data = null; // Dati utente loggato
$is_already_socio = false; // Flag per verificare se è già socio

// --- Recupera Info Utente Loggato (incluso id_socio) ---
$stmt_user = mysqli_prepare($con, "SELECT id, fname, lname, email, contactno, id_socio FROM users WHERE id = ?");
if ($stmt_user) {
    mysqli_stmt_bind_param($stmt_user, "i", $id_utente_loggato);
    mysqli_stmt_execute($stmt_user);
    $result_user = mysqli_stmt_get_result($stmt_user);
    $user_data = mysqli_fetch_assoc($result_user);
    mysqli_stmt_close($stmt_user);

    if ($user_data && $user_data['id_socio'] !== null) {
        $is_already_socio = true;
        $messaggio = "Risulti già registrato come socio.";
        $messaggio_tipo = 'info';
    } elseif (!$user_data) {
         $messaggio = "Errore nel recupero dei dati utente. Effettuare il login.";
         $messaggio_tipo = 'danger';
         error_log("Diventa Socio - Fetch User Error: User ID {$id_utente_loggato} not found?");
    }
} else {
    $messaggio = "Errore nel recupero informazioni utente. Effettuare il login.";
    $messaggio_tipo = 'danger';
    error_log("Diventa Socio - Fetch User Prepare Error: " . mysqli_error($con));
}


// --- Gestione Invio Form (POST) ---
// Procedi solo se l'utente NON è già socio e i dati utente sono stati caricati
if (isset($_POST['submit']) && !$is_already_socio && $user_data) {

    // Recupera dati Socio
    $codice_fiscale = trim(strtoupper($_POST['codice_fiscale'] ?? ''));
    // Aggiungi qui il recupero degli altri campi socio dal form, se li hai aggiunti
    // $indirizzo = trim($_POST['indirizzo'] ?? '');
    // $citta = trim($_POST['citta'] ?? '');
    // $provincia = trim($_POST['provincia'] ?? '');
    // $cap = trim($_POST['cap'] ?? '');
    // $telefono_socio = trim($_POST['telefono_socio'] ?? '');
    // $email_pec = trim($_POST['email_pec'] ?? '');
    // $tessera_club_numero = trim($_POST['tessera_club_numero'] ?? '');
    // $tessera_club_scadenza_str = trim($_POST['tessera_club_scadenza'] ?? '');
    // $tessera_asi_numero = trim($_POST['tessera_asi_numero'] ?? '');
    // $note_socio = trim($_POST['note_socio'] ?? ''); // Se hai un campo note per il socio

    // Recupera dati Auto
    $marca = trim($_POST['marca'] ?? '');
    $modello = trim($_POST['modello'] ?? '');
    $targa = trim(strtoupper($_POST['targa'] ?? ''));
    $numero_telaio = trim($_POST['numero_telaio'] ?? '');
    $colore = trim($_POST['colore'] ?? '');
    $cilindrata_str = trim($_POST['cilindrata'] ?? '');
    $tipo_carburante = trim($_POST['tipo_carburante'] ?? '');
    $anno_immatricolazione_str = trim($_POST['anno_immatricolazione'] ?? '');
    $has_certificazione_asi = isset($_POST['has_certificazione_asi']) ? 1 : 0;
    $targa_oro = isset($_POST['targa_oro']) ? 1 : 0;
    $note_auto = trim($_POST['note_auto'] ?? '');

    // --- Validazione Input Socio ---
    if (empty($codice_fiscale)) {
        $errori_form['codice_fiscale'] = "Il Codice Fiscale è obbligatorio.";
    } elseif (!preg_match('/^[A-Z]{6}[0-9LMNPQRSTUV]{2}[A-Z]{1}[0-9LMNPQRSTUV]{2}[A-Z]{1}[0-9LMNPQRSTUV]{3}[A-Z]{1}$/i', $codice_fiscale)) {
        $errori_form['codice_fiscale'] = "Formato Codice Fiscale non valido.";
    } else {
        // Controlla unicità Codice Fiscale nella tabella soci
        $stmt_check_cf = mysqli_prepare($con, "SELECT id FROM soci WHERE codice_fiscale = ?");
        if ($stmt_check_cf) {
            mysqli_stmt_bind_param($stmt_check_cf, "s", $codice_fiscale);
            mysqli_stmt_execute($stmt_check_cf);
            mysqli_stmt_store_result($stmt_check_cf);
            if (mysqli_stmt_num_rows($stmt_check_cf) > 0) {
                $errori_form['codice_fiscale'] = "Questo Codice Fiscale è già registrato per un altro socio.";
            }
            mysqli_stmt_close($stmt_check_cf);
        } else {
             $errori_form['db_check'] = "Errore nel controllo del Codice Fiscale.";
             error_log("Diventa Socio - Check CF Prepare Error: " . mysqli_error($con));
        }
    }
    // Aggiungi qui le validazioni per gli altri campi socio (indirizzo, citta, etc.)
    // if (empty($indirizzo)) $errori_form['indirizzo'] = "L'indirizzo è obbligatorio.";
    // if (empty($citta)) $errori_form['citta'] = "La città è obbligatoria.";
    // if (empty($provincia)) $errori_form['provincia'] = "La provincia è obbligatoria.";
    // if (empty($cap)) $errori_form['cap'] = "Il CAP è obbligatorio.";

    // --- Validazione Input Auto (obbligatoria) ---
    if (empty($marca)) $errori_form['marca'] = "La marca dell'auto è obbligatoria.";
    if (empty($modello)) $errori_form['modello'] = "Il modello dell'auto è obbligatorio.";
    if (empty($targa)) $errori_form['targa'] = "La targa dell'auto è obbligatoria.";

    // Validazione numero telaio (unicità)
    if (!empty($numero_telaio)) {
        $stmt_check_telaio = mysqli_prepare($con, "SELECT id FROM auto WHERE numero_telaio = ?");
        if ($stmt_check_telaio) {
            mysqli_stmt_bind_param($stmt_check_telaio, "s", $numero_telaio);
            mysqli_stmt_execute($stmt_check_telaio);
            mysqli_stmt_store_result($stmt_check_telaio);
            if (mysqli_stmt_num_rows($stmt_check_telaio) > 0) {
                $errori_form['numero_telaio'] = "Questo numero di telaio è già associato a un'altra auto.";
            }
            mysqli_stmt_close($stmt_check_telaio);
        } else {
             $errori_form['db_check_telaio'] = "Errore nel controllo del numero di telaio.";
             error_log("Diventa Socio - Check Telaio Prepare Error: " . mysqli_error($con));
        }
    }
    // Validazione cilindrata
    $cilindrata = null;
    if (!empty($cilindrata_str)) {
        if (!filter_var($cilindrata_str, FILTER_VALIDATE_INT) || $cilindrata_str <= 0) {
            $errori_form['cilindrata'] = "La cilindrata deve essere un numero intero positivo.";
        } else {
            $cilindrata = (int)$cilindrata_str;
        }
    }
    // Validazione anno immatricolazione
    $anno_immatricolazione = null;
    if (!empty($anno_immatricolazione_str)) {
        if (!preg_match('/^\d{4}$/', $anno_immatricolazione_str) || (int)$anno_immatricolazione_str > date('Y') || (int)$anno_immatricolazione_str < 1900) {
             $errori_form['anno_immatricolazione'] = "L'anno di immatricolazione deve essere un anno valido (4 cifre).";
        } else {
            $anno_immatricolazione = $anno_immatricolazione_str;
        }
    }

    // --- Transazione Database ---
    if (empty($errori_form)) {
        mysqli_begin_transaction($con);
        $commit = true;
        $new_socio_id = null;

        // 1. Inserisci Socio
        // Assicurati che la query INSERT INTO soci includa tutti i campi che hai nel form
        // e che i tipi in bind_param corrispondano
        $sql_insert_socio = "INSERT INTO soci (codice_fiscale, nome, cognome, data_iscrizione_club";
        $sql_values_socio = "VALUES (?, ?, ?, NOW()";
        $bind_types_socio = "sss";
        $bind_params_socio = [$codice_fiscale, $user_data['fname'], $user_data['lname']];

        // Aggiungi campi opzionali alla query dinamicamente (esempio)
        // if (!empty($indirizzo)) { $sql_insert_socio .= ", indirizzo"; $sql_values_socio .= ", ?"; $bind_types_socio .= "s"; $bind_params_socio[] = $indirizzo; }
        // if (!empty($citta)) { $sql_insert_socio .= ", citta"; $sql_values_socio .= ", ?"; $bind_types_socio .= "s"; $bind_params_socio[] = $citta; }
        // ... aggiungi gli altri campi socio che hai nel form ...

        $sql_insert_socio .= ") " . $sql_values_socio . ")";

        $stmt_insert_socio = mysqli_prepare($con, $sql_insert_socio);

        if ($stmt_insert_socio) {
            // Usa l'operatore splat (...) per passare i parametri dinamicamente
            mysqli_stmt_bind_param($stmt_insert_socio, $bind_types_socio, ...$bind_params_socio);

            if (mysqli_stmt_execute($stmt_insert_socio)) {
                $new_socio_id = mysqli_insert_id($con); // Recupera l'ID del nuovo socio
            } else {
                $commit = false;
                $messaggio = "Errore durante la creazione del profilo socio.";
                error_log("Diventa Socio - Insert Socio Execute Error: " . mysqli_stmt_error($stmt_insert_socio));
                 if (mysqli_errno($con) == 1062) { // Errore duplicato CF
                     $errori_form['codice_fiscale'] = "Questo Codice Fiscale è già registrato.";
                     $messaggio = "Errore nel form: Codice Fiscale già registrato.";
                 }
            }
            mysqli_stmt_close($stmt_insert_socio);
        } else {
            $commit = false;
            $messaggio = "Errore preparazione query inserimento socio: " . mysqli_error($con);
            error_log("Diventa Socio - Insert Socio Prepare Error: " . mysqli_error($con));
        }

        // 2. Inserisci Auto (solo se socio inserito correttamente)
        if ($commit && $new_socio_id) {
            $stmt_insert_auto = mysqli_prepare($con,
                "INSERT INTO auto (id_socio, marca, modello, targa, numero_telaio, colore, cilindrata, tipo_carburante, anno_immatricolazione, has_certificazione_asi, targa_oro, note, data_inserimento)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
            );
            if ($stmt_insert_auto) {
                mysqli_stmt_bind_param($stmt_insert_auto, "isssssisiiis",
                    $new_socio_id, // Usa il nuovo ID socio
                    $marca, $modello, $targa, $numero_telaio, $colore, $cilindrata,
                    $tipo_carburante, $anno_immatricolazione, $has_certificazione_asi,
                    $targa_oro, $note_auto
                );
                if (!mysqli_stmt_execute($stmt_insert_auto)) {
                    $commit = false;
                    $messaggio = "Errore durante l'inserimento dell'auto.";
                    error_log("Diventa Socio - Insert Auto Execute Error: " . mysqli_stmt_error($stmt_insert_auto));
                }
                mysqli_stmt_close($stmt_insert_auto);
            } else {
                $commit = false;
                $messaggio = "Errore preparazione query inserimento auto: " . mysqli_error($con);
                error_log("Diventa Socio - Insert Auto Prepare Error: " . mysqli_error($con));
            }
        }

        // 3. Aggiorna Utente (solo se tutto ok finora)
        if ($commit && $new_socio_id) {
            $stmt_update_user = mysqli_prepare($con, "UPDATE users SET id_socio = ? WHERE id = ?");
            if ($stmt_update_user) {
                mysqli_stmt_bind_param($stmt_update_user, "ii", $new_socio_id, $id_utente_loggato);
                if (!mysqli_stmt_execute($stmt_update_user)) {
                    $commit = false;
                    $messaggio = "Errore durante l'associazione dell'utente al profilo socio.";
                    error_log("Diventa Socio - Update User Execute Error: " . mysqli_stmt_error($stmt_update_user));
                }
                mysqli_stmt_close($stmt_update_user);
            } else {
                $commit = false;
                $messaggio = "Errore preparazione query aggiornamento utente: " . mysqli_error($con);
                error_log("Diventa Socio - Update User Prepare Error: " . mysqli_error($con));
            }
        }

        // Finalizza Transazione
        if ($commit) {
            mysqli_commit($con);
            $msg_success = "Registrazione come socio completata con successo! La tua prima auto è stata aggiunta.";
            header("Location: welcome.php?msg=" . urlencode($msg_success) . "&msg_type=success"); // Redirect alla dashboard utente
            exit();
        } else {
            mysqli_rollback($con);
            $messaggio_tipo = 'danger'; // Assicura che il messaggio sia di errore
            if (empty($messaggio)) { // Messaggio generico se non impostato specificamente
                 $messaggio = "Si è verificato un errore durante la registrazione. Riprova.";
            }
        }

    } else {
        // Se ci sono errori di validazione, costruisci un messaggio generale
        $messaggio = "Errore nel form. Controlla i campi evidenziati.";
        $messaggio_tipo = 'danger';
    }
} // Fine gestione POST

mysqli_close($con);
?>
<!DOCTYPE html>
<html lang="it">
    <head>
        <!-- ... (head invariato) ... -->
         <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="description" content="Diventa Socio" />
        <meta name="author" content="" />
        <title>Diventa Socio | Sistema Utente</title>
        <link href="css/styles.css" rel="stylesheet" />
        <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/js/all.min.js" crossorigin="anonymous"></script>
        <style>
            .is-invalid { border-color: #dc3545; }
            .invalid-feedback { color: #dc3545; display: block; font-size: 0.875em; }
            .form-check-input.is-invalid ~ .form-check-label { color: #dc3545; }
            .form-check-input.is-invalid { border-color: #dc3545; }
            .card-header-section { background-color: #e9ecef; padding-top: 0.75rem; padding-bottom: 0.75rem; margin-bottom: 1rem; border-bottom: 1px solid #dee2e6;}
        </style>
    </head>
    <body class="sb-nav-fixed">
      <?php include_once('includes/navbar.php');?>
        <div id="layoutSidenav">
         <?php include_once('includes/sidebar.php');?>
            <div id="layoutSidenav_content">
                <main>
                    <div class="container-fluid px-4">
                        <h1 class="mt-4">Diventa Socio</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="welcome.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Diventa Socio</li>
                        </ol>

                        <?php if (!empty($messaggio)): // Mostra tutti i messaggi qui ?>
                            <div class="alert alert-<?php echo htmlspecialchars($messaggio_tipo); ?> alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($messaggio); ?>
                                <?php if ($messaggio_tipo === 'danger' && !empty($errori_form)): ?>
                                    <ul>
                                        <?php foreach ($errori_form as $errore): ?>
                                            <li><?php echo htmlspecialchars($errore); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <?php if (!$is_already_socio && $user_data): // Mostra il form solo se l'utente non è socio e i dati utente sono caricati ?>
                            <div class="card mb-4">
                                <div class="card-header">
                                    <i class="fas fa-user-plus me-1"></i>
                                    Completa la registrazione come Socio
                                </div>
                                <div class="card-body">
                                     <p class="text-muted">Compila i seguenti dati per registrarti come socio. È richiesto l'inserimento di almeno un'auto d'epoca.</p>
                                    <form method="post" action="diventa-socio.php">

                                        <h5><div class="card-header-section">Dati Anagrafici Socio</div></h5>

                                        <!-- ... (Campi Dati Socio: CF, Indirizzo, Città, Prov, CAP, Tel, PEC - come prima) ... -->
                                        <div class="row mb-3">
                                             <div class="col-md-6">
                                                <label class="form-label">Nome</label>
                                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user_data['fname']); ?>" disabled readonly>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Cognome</label>
                                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user_data['lname']); ?>" disabled readonly>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="codice_fiscale" class="form-label">Codice Fiscale <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control <?php echo isset($errori_form['codice_fiscale']) ? 'is-invalid' : ''; ?>" id="codice_fiscale" name="codice_fiscale" value="<?php echo htmlspecialchars($_POST['codice_fiscale'] ?? ''); ?>" required maxlength="16" style="text-transform: uppercase;">
                                            <?php if (isset($errori_form['codice_fiscale'])): ?>
                                                <div class="invalid-feedback"><?php echo $errori_form['codice_fiscale']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <!-- Rimuovi o commenta i campi socio non presenti nella tabella 'soci' -->
                                        <!--
                                        <div class="mb-3">
                                            <label for="indirizzo" class="form-label">Indirizzo <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control <?php echo isset($errori_form['indirizzo']) ? 'is-invalid' : ''; ?>" id="indirizzo" name="indirizzo" value="<?php echo htmlspecialchars($_POST['indirizzo'] ?? ''); ?>" required>
                                            <?php if (isset($errori_form['indirizzo'])): ?>
                                                <div class="invalid-feedback"><?php echo $errori_form['indirizzo']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-md-5">
                                                <label for="citta" class="form-label">Città <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control <?php echo isset($errori_form['citta']) ? 'is-invalid' : ''; ?>" id="citta" name="citta" value="<?php echo htmlspecialchars($_POST['citta'] ?? ''); ?>" required>
                                                <?php if (isset($errori_form['citta'])): ?>
                                                    <div class="invalid-feedback"><?php echo $errori_form['citta']; ?></div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-4">
                                                <label for="provincia" class="form-label">Provincia (Sigla) <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control <?php echo isset($errori_form['provincia']) ? 'is-invalid' : ''; ?>" id="provincia" name="provincia" value="<?php echo htmlspecialchars($_POST['provincia'] ?? ''); ?>" required maxlength="2" style="text-transform: uppercase;">
                                                 <?php if (isset($errori_form['provincia'])): ?>
                                                    <div class="invalid-feedback"><?php echo $errori_form['provincia']; ?></div>
                                                <?php endif; ?>
                                            </div>
                                             <div class="col-md-3">
                                                <label for="cap" class="form-label">CAP <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control <?php echo isset($errori_form['cap']) ? 'is-invalid' : ''; ?>" id="cap" name="cap" value="<?php echo htmlspecialchars($_POST['cap'] ?? ''); ?>" required maxlength="5">
                                                 <?php if (isset($errori_form['cap'])): ?>
                                                    <div class="invalid-feedback"><?php echo $errori_form['cap']; ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="telefono_socio" class="form-label">Telefono Socio</label>
                                                <input type="tel" class="form-control" id="telefono_socio" name="telefono_socio" value="<?php echo htmlspecialchars($_POST['telefono_socio'] ?? $user_data['contactno']); // Precompila con contactno utente ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="email_pec" class="form-label">Email PEC (Opzionale)</label>
                                                <input type="email" class="form-control" id="email_pec" name="email_pec" value="<?php echo htmlspecialchars($_POST['email_pec'] ?? ''); ?>">
                                            </div>
                                        </div>
                                        -->


                                        <hr>
                                        <h5><div class="card-header-section">Dati Prima Auto (Obbligatoria)</div></h5>

                                        <!-- ... (Campi Dati Auto: Marca, Modello, Targa, Telaio, Colore, Cilindrata, Anno, Carburante, ASI, Targa Oro, Note - come prima) ... -->
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="marca" class="form-label">Marca <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control <?php echo isset($errori_form['marca']) ? 'is-invalid' : ''; ?>" id="marca" name="marca" value="<?php echo htmlspecialchars($_POST['marca'] ?? ''); ?>" required>
                                                <?php if (isset($errori_form['marca'])): ?>
                                                    <div class="invalid-feedback"><?php echo $errori_form['marca']; ?></div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="modello" class="form-label">Modello <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control <?php echo isset($errori_form['modello']) ? 'is-invalid' : ''; ?>" id="modello" name="modello" value="<?php echo htmlspecialchars($_POST['modello'] ?? ''); ?>" required>
                                                 <?php if (isset($errori_form['modello'])): ?>
                                                    <div class="invalid-feedback"><?php echo $errori_form['modello']; ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                         <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="targa" class="form-label">Targa <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control <?php echo isset($errori_form['targa']) ? 'is-invalid' : ''; ?>" id="targa" name="targa" value="<?php echo htmlspecialchars($_POST['targa'] ?? ''); ?>" required>
                                                <?php if (isset($errori_form['targa'])): ?>
                                                    <div class="invalid-feedback"><?php echo $errori_form['targa']; ?></div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="numero_telaio" class="form-label">Numero Telaio</label>
                                                <input type="text" class="form-control <?php echo isset($errori_form['numero_telaio']) ? 'is-invalid' : ''; ?>" id="numero_telaio" name="numero_telaio" value="<?php echo htmlspecialchars($_POST['numero_telaio'] ?? ''); ?>">
                                                 <?php if (isset($errori_form['numero_telaio'])): ?>
                                                    <div class="invalid-feedback"><?php echo $errori_form['numero_telaio']; ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-md-4">
                                                <label for="colore" class="form-label">Colore</label>
                                                <input type="text" class="form-control" id="colore" name="colore" value="<?php echo htmlspecialchars($_POST['colore'] ?? ''); ?>">
                                            </div>
                                             <div class="col-md-4">
                                                <label for="cilindrata" class="form-label">Cilindrata (cc)</label>
                                                <input type="number" class="form-control <?php echo isset($errori_form['cilindrata']) ? 'is-invalid' : ''; ?>" id="cilindrata" name="cilindrata" value="<?php echo htmlspecialchars($_POST['cilindrata'] ?? ''); ?>" min="1">
                                                 <?php if (isset($errori_form['cilindrata'])): ?>
                                                    <div class="invalid-feedback"><?php echo $errori_form['cilindrata']; ?></div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-4">
                                                <label for="anno_immatricolazione" class="form-label">Anno Immatricolazione</label>
                                                <input type="number" class="form-control <?php echo isset($errori_form['anno_immatricolazione']) ? 'is-invalid' : ''; ?>" id="anno_immatricolazione" name="anno_immatricolazione" placeholder="YYYY" value="<?php echo htmlspecialchars($_POST['anno_immatricolazione'] ?? ''); ?>" min="1900" max="<?php echo date('Y'); ?>">
                                                 <?php if (isset($errori_form['anno_immatricolazione'])): ?>
                                                    <div class="invalid-feedback"><?php echo $errori_form['anno_immatricolazione']; ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                             <label for="tipo_carburante" class="form-label">Tipo Carburante</label>
                                             <select class="form-select" id="tipo_carburante" name="tipo_carburante">
                                                 <option value="">-- Seleziona --</option>
                                                 <option value="Benzina" <?php echo (isset($_POST['tipo_carburante']) && $_POST['tipo_carburante'] == 'Benzina') ? 'selected' : ''; ?>>Benzina</option>
                                                 <option value="Diesel" <?php echo (isset($_POST['tipo_carburante']) && $_POST['tipo_carburante'] == 'Diesel') ? 'selected' : ''; ?>>Diesel</option>
                                                 <option value="GPL" <?php echo (isset($_POST['tipo_carburante']) && $_POST['tipo_carburante'] == 'GPL') ? 'selected' : ''; ?>>GPL</option>
                                                 <option value="Metano" <?php echo (isset($_POST['tipo_carburante']) && $_POST['tipo_carburante'] == 'Metano') ? 'selected' : ''; ?>>Metano</option>
                                                 <option value="Elettrico" <?php echo (isset($_POST['tipo_carburante']) && $_POST['tipo_carburante'] == 'Elettrico') ? 'selected' : ''; ?>>Elettrico</option>
                                                 <option value="Ibrido" <?php echo (isset($_POST['tipo_carburante']) && $_POST['tipo_carburante'] == 'Ibrido') ? 'selected' : ''; ?>>Ibrido</option>
                                                 <option value="Altro" <?php echo (isset($_POST['tipo_carburante']) && $_POST['tipo_carburante'] == 'Altro') ? 'selected' : ''; ?>>Altro</option>
                                             </select>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" value="1" id="has_certificazione_asi" name="has_certificazione_asi" <?php echo isset($_POST['has_certificazione_asi']) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="has_certificazione_asi">
                                                        Ha Certificazione ASI
                                                    </label>
                                                </div>
                                            </div>
                                             <div class="col-md-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" value="1" id="targa_oro" name="targa_oro" <?php echo isset($_POST['targa_oro']) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="targa_oro">
                                                        Targa Oro
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="note_auto" class="form-label">Note Auto</label>
                                            <textarea class="form-control" id="note_auto" name="note_auto" rows="3"><?php echo htmlspecialchars($_POST['note_auto'] ?? ''); ?></textarea>
                                        </div>


                                        <div class="mt-4 d-flex justify-content-end">
                                             <a href="welcome.php" class="btn btn-secondary me-2">Annulla</a>
                                             <button type="submit" name="submit" class="btn btn-primary">Diventa Socio</button> <!-- Cambiato testo bottone -->
                                        </div>

                                    </form>
                                </div>
                            </div>
                        <?php endif; // Fine controllo !$is_already_socio && $user_data ?>
                    </div>
                </main>
                <?php include('includes/footer.php');?>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
        <script src="js/scripts.js"></script>
    </body>
</html>
