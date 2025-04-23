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
$socio_data = null; // Array per i dati del socio da modificare
$users_list = []; // Array per gli utenti associabili

// --- Recupera ID Socio dalla URL ---
$socio_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$socio_id) {
    header("Location: gestisci-soci.php?msg=" . urlencode("ID socio non valido.") . "&msg_type=danger");
    exit();
}

// --- Recupero Utenti per associazione ---
// Seleziona utenti che NON sono già associati a un ALTRO socio, OPPURE l'utente attualmente associato a QUESTO socio
$sql_users = "SELECT id, fname, lname, email
              FROM users
              WHERE id_socio IS NULL OR id_socio = ?
              ORDER BY lname, fname";
$stmt_users = mysqli_prepare($con, $sql_users);
if ($stmt_users) {
    mysqli_stmt_bind_param($stmt_users, "i", $socio_id);
    mysqli_stmt_execute($stmt_users);
    $result_users = mysqli_stmt_get_result($stmt_users);
    while ($user = mysqli_fetch_assoc($result_users)) {
        $users_list[] = $user;
    }
    mysqli_stmt_close($stmt_users);
} else {
    $messaggio = "Errore nel caricamento dell'elenco utenti.";
    $messaggio_tipo = 'danger';
    error_log("Edit Socio - Fetch Users Error: " . mysqli_error($con));
}


// --- Gestione Modifica Socio (POST) ---
if (isset($_POST['submit'])) {
    // Recupera e pulisci i dati dal form
    $codice_fiscale = trim(strtoupper($_POST['codice_fiscale'] ?? ''));
    $nome = trim($_POST['nome'] ?? '');
    $cognome = trim($_POST['cognome'] ?? '');
    $tessera_club_numero = trim($_POST['tessera_club_numero'] ?? '');
    $tessera_club_scadenza_str = trim($_POST['tessera_club_scadenza'] ?? '');
    $has_tessera_asi = isset($_POST['has_tessera_asi']) ? 1 : 0;
    $tessera_asi_numero = trim($_POST['tessera_asi_numero'] ?? '');
    $note = trim($_POST['note'] ?? '');
    $id_utente_associato = filter_input(INPUT_POST, 'id_utente_associato', FILTER_VALIDATE_INT);
    // Se l'utente seleziona "Nessun utente", l'ID sarà 0 o vuoto, lo convertiamo in NULL
    if (empty($id_utente_associato)) {
        $id_utente_associato = null;
    }

    // --- Validazione Input (Simile ad add-socio.php) ---
    if (empty($codice_fiscale)) {
        $errori_form['codice_fiscale'] = "Il Codice Fiscale è obbligatorio.";
    } elseif (!preg_match('/^[A-Z]{6}[0-9LMNPQRSTUV]{2}[A-Z]{1}[0-9LMNPQRSTUV]{2}[A-Z]{1}[0-9LMNPQRSTUV]{3}[A-Z]{1}$/i', $codice_fiscale)) {
        $errori_form['codice_fiscale'] = "Formato Codice Fiscale non valido.";
    } else {
        // Controlla unicità Codice Fiscale (escludendo il socio corrente)
        $stmt_check_cf = mysqli_prepare($con, "SELECT id FROM soci WHERE codice_fiscale = ? AND id != ?");
        if ($stmt_check_cf) {
            mysqli_stmt_bind_param($stmt_check_cf, "si", $codice_fiscale, $socio_id);
            mysqli_stmt_execute($stmt_check_cf);
            mysqli_stmt_store_result($stmt_check_cf);
            if (mysqli_stmt_num_rows($stmt_check_cf) > 0) {
                $errori_form['codice_fiscale'] = "Questo Codice Fiscale è già associato a un altro socio.";
            }
            mysqli_stmt_close($stmt_check_cf);
        } else {
             $errori_form['db_check'] = "Errore nel controllo del Codice Fiscale.";
             error_log("Edit Socio - Check CF Prepare Error: " . mysqli_error($con));
        }
    }

    if (empty($nome)) {
        $errori_form['nome'] = "Il nome è obbligatorio.";
    }
    if (empty($cognome)) {
        $errori_form['cognome'] = "Il cognome è obbligatorio.";
    }

    // Validazione data scadenza tessera club
    $tessera_club_scadenza = null;
    if (!empty($tessera_club_scadenza_str)) {
        try {
            $tessera_club_scadenza_dt = new DateTime($tessera_club_scadenza_str);
            $tessera_club_scadenza = $tessera_club_scadenza_dt->format('Y-m-d');
        } catch (Exception $e) {
            $errori_form['tessera_club_scadenza'] = "Formato data scadenza tessera club non valido. Usa YYYY-MM-DD.";
        }
    }

    // Validazione Associazione Utente: Verifica che l'utente selezionato non sia già associato a un *altro* socio
    if ($id_utente_associato !== null) {
        $stmt_check_user_assoc = mysqli_prepare($con, "SELECT id FROM users WHERE id = ? AND (id_socio IS NULL OR id_socio = ?)");
        if ($stmt_check_user_assoc) {
            mysqli_stmt_bind_param($stmt_check_user_assoc, "ii", $id_utente_associato, $socio_id);
            mysqli_stmt_execute($stmt_check_user_assoc);
            mysqli_stmt_store_result($stmt_check_user_assoc);
            if (mysqli_stmt_num_rows($stmt_check_user_assoc) == 0) {
                $errori_form['id_utente_associato'] = "L'utente selezionato è già associato a un altro socio.";
            }
            mysqli_stmt_close($stmt_check_user_assoc);
        } else {
             $errori_form['db_check_user'] = "Errore nel controllo dell'associazione utente.";
             error_log("Edit Socio - Check User Assoc Prepare Error: " . mysqli_error($con));
        }
    }


    // Se non ci sono errori, procedi con l'aggiornamento
    if (empty($errori_form)) {

        // Recupera l'ID utente attualmente associato a questo socio (prima dell'update)
        $current_associated_user_id = null;
        $stmt_get_current_user = mysqli_prepare($con, "SELECT id FROM users WHERE id_socio = ?");
        if($stmt_get_current_user){
            mysqli_stmt_bind_param($stmt_get_current_user, "i", $socio_id);
            mysqli_stmt_execute($stmt_get_current_user);
            $res_current_user = mysqli_stmt_get_result($stmt_get_current_user);
            $row_current_user = mysqli_fetch_assoc($res_current_user);
            if($row_current_user) {
                $current_associated_user_id = $row_current_user['id'];
            }
            mysqli_stmt_close($stmt_get_current_user);
        }

        // Inizia una transazione per garantire l'atomicità degli aggiornamenti
        mysqli_begin_transaction($con);
        $commit = true; // Flag per il commit

        // 1. Aggiorna la tabella soci
        $stmt_update_socio = mysqli_prepare($con,
            "UPDATE soci SET
                codice_fiscale = ?, nome = ?, cognome = ?, tessera_club_numero = ?,
                tessera_club_scadenza = ?, has_tessera_asi = ?, tessera_asi_numero = ?, note = ?
             WHERE id = ?"
        );

        if ($stmt_update_socio) {
            mysqli_stmt_bind_param($stmt_update_socio, "sssssissi", // Tipi corretti
                $codice_fiscale,
                $nome,
                $cognome,
                $tessera_club_numero,
                $tessera_club_scadenza,
                $has_tessera_asi,
                $tessera_asi_numero,
                $note,
                $socio_id
            );

            if (!mysqli_stmt_execute($stmt_update_socio)) {
                $commit = false;
                $messaggio = "Errore durante l'aggiornamento dei dati del socio.";
                error_log("Admin Edit Socio Execute Error: " . mysqli_stmt_error($stmt_update_socio));
            }
            mysqli_stmt_close($stmt_update_socio);
        } else {
            $commit = false;
            $messaggio = "Errore nella preparazione della query di aggiornamento socio.";
            error_log("Admin Edit Socio Prepare Error: " . mysqli_error($con));
        }

        // 2. Aggiorna le associazioni nella tabella users (solo se l'update socio è andato bene)
        if ($commit) {
            // Dissocia l'utente precedente, se diverso da quello nuovo e non nullo
            if ($current_associated_user_id !== null && $current_associated_user_id != $id_utente_associato) {
                $stmt_dissoc = mysqli_prepare($con, "UPDATE users SET id_socio = NULL WHERE id = ?");
                if ($stmt_dissoc) {
                    mysqli_stmt_bind_param($stmt_dissoc, "i", $current_associated_user_id);
                    if (!mysqli_stmt_execute($stmt_dissoc)) {
                        $commit = false;
                        $messaggio = "Errore durante la dissociazione dell'utente precedente.";
                        error_log("Admin Edit Socio Dissociate User Error: " . mysqli_stmt_error($stmt_dissoc));
                    }
                    mysqli_stmt_close($stmt_dissoc);
                } else {
                    $commit = false;
                    $messaggio = "Errore preparazione query dissociazione utente.";
                    error_log("Admin Edit Socio Dissociate User Prepare Error: " . mysqli_error($con));
                }
            }

            // Associa il nuovo utente (se selezionato e diverso dal precedente)
            if ($commit && $id_utente_associato !== null && $id_utente_associato != $current_associated_user_id) {
                 $stmt_assoc = mysqli_prepare($con, "UPDATE users SET id_socio = ? WHERE id = ?");
                 if ($stmt_assoc) {
                    mysqli_stmt_bind_param($stmt_assoc, "ii", $socio_id, $id_utente_associato);
                     if (!mysqli_stmt_execute($stmt_assoc)) {
                        $commit = false;
                        $messaggio = "Errore durante l'associazione del nuovo utente.";
                        error_log("Admin Edit Socio Associate User Error: " . mysqli_stmt_error($stmt_assoc));
                    }
                    mysqli_stmt_close($stmt_assoc);
                 } else {
                    $commit = false;
                    $messaggio = "Errore preparazione query associazione utente.";
                    error_log("Admin Edit Socio Associate User Prepare Error: " . mysqli_error($con));
                 }
            }
        }

        // Finalizza la transazione
        if ($commit) {
            mysqli_commit($con);
            // Messaggio di successo e redirect
            $msg_success = "Socio (ID: " . $socio_id . ") aggiornato con successo.";
            header("Location: gestisci-soci.php?msg=" . urlencode($msg_success) . "&msg_type=success");
            exit();
        } else {
            mysqli_rollback($con);
            $messaggio_tipo = 'danger'; // Assicura che il messaggio sia di errore
        }

    } else {
        // Se ci sono errori di validazione, costruisci un messaggio generale
        $messaggio = "Errore nel form. Controlla i campi evidenziati.";
        $messaggio_tipo = 'danger';
        // Riempi $socio_data con i dati POST per ripopolare il form
        $socio_data = $_POST;
        $socio_data['has_tessera_asi'] = $has_tessera_asi; // Ripopola checkbox
        $socio_data['id'] = $socio_id; // Assicurati che l'ID sia presente
        // Recupera l'ID utente associato dal DB per il ripopolamento corretto del select
        // (se non c'è errore POST, altrimenti usa quello da POST)
        if (!isset($socio_data['id_utente_associato'])) {
             $stmt_get_user = mysqli_prepare($con, "SELECT id FROM users WHERE id_socio = ?");
             if($stmt_get_user){
                 mysqli_stmt_bind_param($stmt_get_user, "i", $socio_id);
                 mysqli_stmt_execute($stmt_get_user);
                 $res_user = mysqli_stmt_get_result($stmt_get_user);
                 $row_user = mysqli_fetch_assoc($res_user);
                 $socio_data['id_utente_associato'] = $row_user ? $row_user['id'] : null;
                 mysqli_stmt_close($stmt_get_user);
             }
        }

    }
} else {
    // --- Caricamento Iniziale Pagina (GET) ---
    // Recupera i dati attuali del socio dal DB, incluso l'utente associato
    $stmt_fetch = mysqli_prepare($con, "SELECT s.*, u.id as id_utente_associato
                                        FROM soci s
                                        LEFT JOIN users u ON s.id = u.id_socio
                                        WHERE s.id = ?");
    if ($stmt_fetch) {
        mysqli_stmt_bind_param($stmt_fetch, "i", $socio_id);
        mysqli_stmt_execute($stmt_fetch);
        $result_fetch = mysqli_stmt_get_result($stmt_fetch);
        $socio_data = mysqli_fetch_assoc($result_fetch);
        mysqli_stmt_close($stmt_fetch);

        if (!$socio_data) {
            // Socio non trovato
            header("Location: gestisci-soci.php?msg=" . urlencode("Socio non trovato.") . "&msg_type=danger");
            exit();
        }
        // Formatta la data per il campo date
        if (!empty($socio_data['tessera_club_scadenza'])) {
            $socio_data['tessera_club_scadenza'] = date('Y-m-d', strtotime($socio_data['tessera_club_scadenza']));
        }

    } else {
        // Errore nel recupero
        $messaggio = "Errore nel recupero dei dati del socio.";
        $messaggio_tipo = 'danger';
        error_log("Admin Edit Socio Fetch Prepare Error: " . mysqli_error($con));
        $socio_data = null; // Non mostrare il form
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
        <meta name="description" content="Modifica Socio" />
        <meta name="author" content="" />
        <title>Modifica Socio | Sistema Admin</title>
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
                        <h1 class="mt-4">Modifica Socio (ID: <?php echo $socio_id; ?>)</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="gestisci-soci.php">Gestisci Soci</a></li>
                            <li class="breadcrumb-item active">Modifica Socio</li>
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

                        <?php if ($socio_data): // Mostra il form solo se i dati sono stati caricati ?>
                            <div class="card mb-4">
                                <div class="card-header">
                                    <i class="fas fa-user-edit me-1"></i>
                                    Dettagli Socio
                                </div>
                                <div class="card-body">
                                    <form method="post" action="edit-socio.php?id=<?php echo $socio_id; // Mantieni ID in action ?>">

                                        <div class="mb-3">
                                            <label for="codice_fiscale" class="form-label">Codice Fiscale <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control <?php echo isset($errori_form['codice_fiscale']) ? 'is-invalid' : ''; ?>" id="codice_fiscale" name="codice_fiscale" value="<?php echo htmlspecialchars($socio_data['codice_fiscale'] ?? ''); ?>" required maxlength="16" style="text-transform: uppercase;">
                                            <?php if (isset($errori_form['codice_fiscale'])): ?>
                                                <div class="invalid-feedback"><?php echo $errori_form['codice_fiscale']; ?></div>
                                            <?php endif; ?>
                                        </div>

                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="cognome" class="form-label">Cognome <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control <?php echo isset($errori_form['cognome']) ? 'is-invalid' : ''; ?>" id="cognome" name="cognome" value="<?php echo htmlspecialchars($socio_data['cognome'] ?? ''); ?>" required>
                                                <?php if (isset($errori_form['cognome'])): ?>
                                                    <div class="invalid-feedback"><?php echo $errori_form['cognome']; ?></div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="nome" class="form-label">Nome <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control <?php echo isset($errori_form['nome']) ? 'is-invalid' : ''; ?>" id="nome" name="nome" value="<?php echo htmlspecialchars($socio_data['nome'] ?? ''); ?>" required>
                                                 <?php if (isset($errori_form['nome'])): ?>
                                                    <div class="invalid-feedback"><?php echo $errori_form['nome']; ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                         <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="tessera_club_numero" class="form-label">Numero Tessera Club</label>
                                                <input type="text" class="form-control" id="tessera_club_numero" name="tessera_club_numero" value="<?php echo htmlspecialchars($socio_data['tessera_club_numero'] ?? ''); ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="tessera_club_scadenza" class="form-label">Scadenza Tessera Club</label>
                                                <input type="date" class="form-control <?php echo isset($errori_form['tessera_club_scadenza']) ? 'is-invalid' : ''; ?>" id="tessera_club_scadenza" name="tessera_club_scadenza" value="<?php echo htmlspecialchars($socio_data['tessera_club_scadenza'] ?? ''); ?>">
                                                 <?php if (isset($errori_form['tessera_club_scadenza'])): ?>
                                                    <div class="invalid-feedback"><?php echo $errori_form['tessera_club_scadenza']; ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="row mb-3 align-items-center">
                                            <div class="col-md-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" value="1" id="has_tessera_asi" name="has_tessera_asi" <?php echo !empty($socio_data['has_tessera_asi']) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="has_tessera_asi">
                                                        Ha Tessera ASI
                                                    </label>
                                                </div>
                                            </div>
                                             <div class="col-md-6">
                                                <label for="tessera_asi_numero" class="form-label visually-hidden">Numero Tessera ASI</label>
                                                <input type="text" class="form-control" id="tessera_asi_numero" name="tessera_asi_numero" placeholder="Numero Tessera ASI (se presente)" value="<?php echo htmlspecialchars($socio_data['tessera_asi_numero'] ?? ''); ?>">
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="note" class="form-label">Note Aggiuntive</label>
                                            <textarea class="form-control" id="note" name="note" rows="3"><?php echo htmlspecialchars($socio_data['note'] ?? ''); ?></textarea>
                                        </div>

                                        <hr>

                                        <div class="mb-3">
                                            <label for="id_utente_associato" class="form-label">Associa Account Utente</label>
                                            <select class="form-select <?php echo isset($errori_form['id_utente_associato']) ? 'is-invalid' : ''; ?>" id="id_utente_associato" name="id_utente_associato">
                                                <option value="">-- Nessun utente --</option>
                                                <?php if (!empty($users_list)): ?>
                                                    <?php foreach ($users_list as $user): ?>
                                                        <option value="<?php echo $user['id']; ?>" <?php echo (isset($socio_data['id_utente_associato']) && $socio_data['id_utente_associato'] == $user['id']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($user['lname'] . ' ' . $user['fname'] . ' (' . $user['email'] . ')'); ?>
                                                            <?php echo ($socio_data['id_utente_associato'] == $user['id']) ? ' (Attualmente associato)' : ''; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <option value="" disabled>Nessun utente disponibile per l'associazione</option>
                                                <?php endif; ?>
                                            </select>
                                            <div class="form-text">Seleziona un account utente da collegare a questo socio. Selezionando "Nessun utente", l'eventuale associazione precedente verrà rimossa.</div>
                                            <?php if (isset($errori_form['id_utente_associato'])): ?>
                                                <div class="invalid-feedback"><?php echo $errori_form['id_utente_associato']; ?></div>
                                            <?php endif; ?>
                                        </div>


                                        <div class="mt-4 d-flex justify-content-end">
                                             <a href="gestisci-soci.php" class="btn btn-secondary me-2">Annulla</a>
                                             <button type="submit" name="submit" class="btn btn-primary">Salva Modifiche</button>
                                        </div>

                                    </form>
                                </div>
                            </div>
                        <?php else: ?>
                             <div class="alert alert-warning">Impossibile caricare i dati del socio per la modifica.</div>
                        <?php endif; // Fine controllo $socio_data ?>
                    </div>
                </main>
                <?php include('../includes/footer.php');?>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
        <script src="../js/scripts.js"></script>
    </body>
</html>
