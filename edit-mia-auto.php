<?php
session_start();
include_once('includes/config.php');

// Verifica se l'utente è loggato
if (strlen($_SESSION['id'] ?? 0) == 0) {
    header('location:logout.php');
    exit();
}

$id_utente_loggato = $_SESSION['id'];
$messaggio = '';
$messaggio_tipo = 'info';
$errori_form = []; // Array per memorizzare errori specifici dei campi
$auto_data = null; // Array per i dati dell'auto da modificare
$id_socio_utente = null; // ID socio associato all'utente loggato

// --- Recupera ID Auto dalla URL ---
$auto_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$auto_id) {
    header("Location: gestisci-auto.php?msg=" . urlencode("ID auto non valido.") . "&msg_type=danger");
    exit();
}

// --- Recupera ID Socio dell'utente loggato ---
$stmt_user = mysqli_prepare($con, "SELECT id_socio FROM users WHERE id = ?");
if ($stmt_user) {
    mysqli_stmt_bind_param($stmt_user, "i", $id_utente_loggato);
    mysqli_stmt_execute($stmt_user);
    $result_user = mysqli_stmt_get_result($stmt_user);
    if ($user_data = mysqli_fetch_assoc($result_user)) {
        $id_socio_utente = $user_data['id_socio']; // Può essere NULL
    }
    mysqli_stmt_close($stmt_user);
} else {
    $messaggio = "Errore nel recupero informazioni utente.";
    $messaggio_tipo = 'danger';
    error_log("User Edit Mia Auto - Fetch User Error: " . mysqli_error($con));
}

// Se l'utente non è un socio, non può modificare auto
if ($id_socio_utente === null && empty($messaggio)) {
     $messaggio = "Devi essere registrato come socio per poter modificare le auto.";
     $messaggio_tipo = 'warning';
}

// --- Gestione Modifica Auto (POST) ---
// Procedi solo se l'utente è un socio
if (isset($_POST['submit']) && $id_socio_utente !== null) {
    // Recupera e pulisci i dati dal form
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
    $note = trim($_POST['note'] ?? '');

    // --- Validazione Input (Simile ad add-mia-auto.php) ---
    if (empty($marca)) {
        $errori_form['marca'] = "La marca è obbligatoria.";
    }
    if (empty($modello)) {
        $errori_form['modello'] = "Il modello è obbligatorio.";
    }
    if (empty($targa)) {
        $errori_form['targa'] = "La targa è obbligatoria.";
    }

    // Validazione numero telaio (unicità escludendo l'auto corrente)
    if (!empty($numero_telaio)) {
        $stmt_check_telaio = mysqli_prepare($con, "SELECT id FROM auto WHERE numero_telaio = ? AND id != ?");
        if ($stmt_check_telaio) {
            mysqli_stmt_bind_param($stmt_check_telaio, "si", $numero_telaio, $auto_id);
            mysqli_stmt_execute($stmt_check_telaio);
            mysqli_stmt_store_result($stmt_check_telaio);
            if (mysqli_stmt_num_rows($stmt_check_telaio) > 0) {
                $errori_form['numero_telaio'] = "Questo numero di telaio è già associato a un'altra auto.";
            }
            mysqli_stmt_close($stmt_check_telaio);
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

    // Se non ci sono errori, procedi con l'aggiornamento
    if (empty($errori_form)) {
        // Aggiungi id_socio alla clausola WHERE per sicurezza!
        $stmt_update = mysqli_prepare($con,
            "UPDATE auto SET
                marca = ?, modello = ?, targa = ?, numero_telaio = ?, colore = ?,
                cilindrata = ?, tipo_carburante = ?, anno_immatricolazione = ?,
                has_certificazione_asi = ?, targa_oro = ?, note = ?
             WHERE id = ? AND id_socio = ?" // Aggiorna solo se l'ID e l'ID socio corrispondono
        );

        if ($stmt_update) {
            mysqli_stmt_bind_param($stmt_update, "sssssisiiisii", // Aggiunti 'ii' alla fine per id e id_socio
                $marca,
                $modello,
                $targa,
                $numero_telaio,
                $colore,
                $cilindrata,
                $tipo_carburante,
                $anno_immatricolazione,
                $has_certificazione_asi,
                $targa_oro,
                $note,
                $auto_id,         // ID dell'auto da aggiornare
                $id_socio_utente  // ID del socio proprietario (controllo sicurezza)
            );

            if (mysqli_stmt_execute($stmt_update)) {
                // Controlla se qualche riga è stata effettivamente modificata
                if (mysqli_stmt_affected_rows($stmt_update) > 0) {
                    $msg_success = "La tua auto (ID: " . $auto_id . ") è stata aggiornata con successo.";
                    $msg_type_redirect = 'success';
                } else {
                    // Nessuna riga modificata: o l'auto non appartiene all'utente o non c'erano modifiche
                    // Potrebbe essere un tentativo di modifica non autorizzato o nessun cambiamento effettivo
                    $msg_success = "Nessuna modifica apportata all'auto (ID: " . $auto_id . ").";
                    $msg_type_redirect = 'info'; // Usa 'info' o 'warning'
                }
                mysqli_stmt_close($stmt_update);
                // Redirect alla pagina utente
                header("Location: gestisci-auto.php?msg=" . urlencode($msg_success) . "&msg_type=" . $msg_type_redirect);
                exit();

            } else {
                $messaggio = "Errore durante l'aggiornamento dell'auto nel database.";
                $messaggio_tipo = 'danger';
                error_log("User Edit Mia Auto Execute Error: " . mysqli_stmt_error($stmt_update));
            }
             if (isset($stmt_update) && $stmt_update) mysqli_stmt_close($stmt_update);
        } else {
            $messaggio = "Errore nella preparazione della query di aggiornamento.";
            $messaggio_tipo = 'danger';
            error_log("User Edit Mia Auto Prepare Error: " . mysqli_error($con));
        }
    } else {
        // Se ci sono errori di validazione, costruisci un messaggio generale
        $messaggio = "Errore nel form. Controlla i campi evidenziati.";
        $messaggio_tipo = 'danger';
        // Riempi $auto_data con i dati POST per ripopolare il form
        $auto_data = $_POST;
        $auto_data['has_certificazione_asi'] = $has_certificazione_asi;
        $auto_data['targa_oro'] = $targa_oro;
        $auto_data['id'] = $auto_id; // Assicurati che l'ID sia presente
    }
} else {
    // --- Caricamento Iniziale Pagina (GET) ---
    // Procedi solo se l'utente è un socio
    if ($id_socio_utente !== null) {
        // Recupera i dati attuali dell'auto assicurandosi che appartenga al socio loggato
        $stmt_fetch = mysqli_prepare($con, "SELECT * FROM auto WHERE id = ? AND id_socio = ?");
        if ($stmt_fetch) {
            mysqli_stmt_bind_param($stmt_fetch, "ii", $auto_id, $id_socio_utente);
            mysqli_stmt_execute($stmt_fetch);
            $result_fetch = mysqli_stmt_get_result($stmt_fetch);
            $auto_data = mysqli_fetch_assoc($result_fetch);
            mysqli_stmt_close($stmt_fetch);

            if (!$auto_data) {
                // Auto non trovata o non appartenente all'utente
                header("Location: gestisci-auto.php?msg=" . urlencode("Auto non trovata o non autorizzata.") . "&msg_type=danger");
                exit();
            }
            // Non serve formattare date qui

        } else {
            // Errore nel recupero
            $messaggio = "Errore nel recupero dei dati dell'auto.";
            $messaggio_tipo = 'danger';
            error_log("User Edit Mia Auto Fetch Prepare Error: " . mysqli_error($con));
            $auto_data = null; // Non mostrare il form
        }
    }
    // Se l'utente non è socio, $auto_data rimarrà null e il form non verrà mostrato
}

mysqli_close($con);
?>
<!DOCTYPE html>
<html lang="it">
    <head>
        <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="description" content="Modifica Auto" />
        <meta name="author" content="" />
        <title>Modifica Auto | Sistema Utente</title>
        <link href="css/styles.css" rel="stylesheet" />
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
                        <h1 class="mt-4">Modifica Auto (ID: <?php echo $auto_id; ?>)</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="welcome.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="gestisci-auto.php">Le Tue Auto</a></li>
                            <li class="breadcrumb-item active">Modifica Auto</li>
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

                        <?php if ($id_socio_utente !== null && $auto_data): // Mostra il form solo se l'utente è socio e i dati auto sono stati caricati ?>
                            <div class="card mb-4">
                                <div class="card-header">
                                    <i class="fas fa-edit me-1"></i>
                                    Dettagli Auto
                                </div>
                                <div class="card-body">
                                     <form method="post" action="edit-mia-auto.php?id=<?php echo $auto_id; // Mantieni ID in action ?>">

                                        <?php // Il campo select socio è rimosso ?>

                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="marca" class="form-label">Marca <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control <?php echo isset($errori_form['marca']) ? 'is-invalid' : ''; ?>" id="marca" name="marca" value="<?php echo htmlspecialchars($auto_data['marca'] ?? ''); ?>" required>
                                                <?php if (isset($errori_form['marca'])): ?>
                                                    <div class="invalid-feedback"><?php echo $errori_form['marca']; ?></div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="modello" class="form-label">Modello <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control <?php echo isset($errori_form['modello']) ? 'is-invalid' : ''; ?>" id="modello" name="modello" value="<?php echo htmlspecialchars($auto_data['modello'] ?? ''); ?>" required>
                                                 <?php if (isset($errori_form['modello'])): ?>
                                                    <div class="invalid-feedback"><?php echo $errori_form['modello']; ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                         <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="targa" class="form-label">Targa <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control <?php echo isset($errori_form['targa']) ? 'is-invalid' : ''; ?>" id="targa" name="targa" value="<?php echo htmlspecialchars($auto_data['targa'] ?? ''); ?>" required>
                                                <?php if (isset($errori_form['targa'])): ?>
                                                    <div class="invalid-feedback"><?php echo $errori_form['targa']; ?></div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="numero_telaio" class="form-label">Numero Telaio</label>
                                                <input type="text" class="form-control <?php echo isset($errori_form['numero_telaio']) ? 'is-invalid' : ''; ?>" id="numero_telaio" name="numero_telaio" value="<?php echo htmlspecialchars($auto_data['numero_telaio'] ?? ''); ?>">
                                                 <?php if (isset($errori_form['numero_telaio'])): ?>
                                                    <div class="invalid-feedback"><?php echo $errori_form['numero_telaio']; ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="row mb-3">
                                            <div class="col-md-4">
                                                <label for="colore" class="form-label">Colore</label>
                                                <input type="text" class="form-control" id="colore" name="colore" value="<?php echo htmlspecialchars($auto_data['colore'] ?? ''); ?>">
                                            </div>
                                             <div class="col-md-4">
                                                <label for="cilindrata" class="form-label">Cilindrata (cc)</label>
                                                <input type="number" class="form-control <?php echo isset($errori_form['cilindrata']) ? 'is-invalid' : ''; ?>" id="cilindrata" name="cilindrata" value="<?php echo htmlspecialchars($auto_data['cilindrata'] ?? ''); ?>" min="1">
                                                 <?php if (isset($errori_form['cilindrata'])): ?>
                                                    <div class="invalid-feedback"><?php echo $errori_form['cilindrata']; ?></div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-4">
                                                <label for="anno_immatricolazione" class="form-label">Anno Immatricolazione</label>
                                                <input type="number" class="form-control <?php echo isset($errori_form['anno_immatricolazione']) ? 'is-invalid' : ''; ?>" id="anno_immatricolazione" name="anno_immatricolazione" placeholder="YYYY" value="<?php echo htmlspecialchars($auto_data['anno_immatricolazione'] ?? ''); ?>" min="1900" max="<?php echo date('Y'); ?>">
                                                 <?php if (isset($errori_form['anno_immatricolazione'])): ?>
                                                    <div class="invalid-feedback"><?php echo $errori_form['anno_immatricolazione']; ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                             <label for="tipo_carburante" class="form-label">Tipo Carburante</label>
                                             <select class="form-select" id="tipo_carburante" name="tipo_carburante">
                                                 <option value="">-- Seleziona --</option>
                                                 <option value="Benzina" <?php echo (isset($auto_data['tipo_carburante']) && $auto_data['tipo_carburante'] == 'Benzina') ? 'selected' : ''; ?>>Benzina</option>
                                                 <option value="Diesel" <?php echo (isset($auto_data['tipo_carburante']) && $auto_data['tipo_carburante'] == 'Diesel') ? 'selected' : ''; ?>>Diesel</option>
                                                 <option value="GPL" <?php echo (isset($auto_data['tipo_carburante']) && $auto_data['tipo_carburante'] == 'GPL') ? 'selected' : ''; ?>>GPL</option>
                                                 <option value="Metano" <?php echo (isset($auto_data['tipo_carburante']) && $auto_data['tipo_carburante'] == 'Metano') ? 'selected' : ''; ?>>Metano</option>
                                                 <option value="Elettrico" <?php echo (isset($auto_data['tipo_carburante']) && $auto_data['tipo_carburante'] == 'Elettrico') ? 'selected' : ''; ?>>Elettrico</option>
                                                 <option value="Ibrido" <?php echo (isset($auto_data['tipo_carburante']) && $auto_data['tipo_carburante'] == 'Ibrido') ? 'selected' : ''; ?>>Ibrido</option>
                                                 <option value="Altro" <?php echo (isset($auto_data['tipo_carburante']) && $auto_data['tipo_carburante'] == 'Altro') ? 'selected' : ''; ?>>Altro</option>
                                             </select>
                                        </div>

                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" value="1" id="has_certificazione_asi" name="has_certificazione_asi" <?php echo !empty($auto_data['has_certificazione_asi']) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="has_certificazione_asi">
                                                        Ho Certificazione ASI
                                                    </label>
                                                </div>
                                            </div>
                                             <div class="col-md-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" value="1" id="targa_oro" name="targa_oro" <?php echo !empty($auto_data['targa_oro']) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="targa_oro">
                                                        Ho Targa Oro
                                                    </label>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="note" class="form-label">Note Aggiuntive</label>
                                            <textarea class="form-control" id="note" name="note" rows="3"><?php echo htmlspecialchars($auto_data['note'] ?? ''); ?></textarea>
                                        </div>

                                        <?php /* Sezione Upload Foto (Omessa) */ ?>

                                        <div class="mt-4 d-flex justify-content-end">
                                             <a href="gestisci-auto.php" class="btn btn-secondary me-2">Annulla</a>
                                             <button type="submit" name="submit" class="btn btn-primary">Salva Modifiche</button>
                                        </div>

                                    </form>
                                </div>
                            </div>
                        <?php elseif ($id_socio_utente !== null): // Utente è socio ma i dati auto non sono stati caricati (errore fetch o auto non sua) ?>
                            <div class="alert alert-warning">Impossibile caricare i dati dell'auto per la modifica.</div>
                        <?php endif; // Fine controllo $id_socio_utente e $auto_data ?>
                    </div>
                </main>
                <?php include('includes/footer.php');?>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
        <script src="js/scripts.js"></script>
    </body>
</html>
