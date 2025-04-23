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
$auto_data = null; // Array per i dati dell'auto da modificare

// --- Recupera ID Auto dalla URL ---
$auto_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$auto_id) {
    header("Location: gestisci-auto.php?msg=" . urlencode("ID auto non valido.") . "&msg_type=danger");
    exit();
}

// --- Recupero Soci per il dropdown (necessario sia per GET che per POST con errori) ---
$soci_list = [];
$result_soci = mysqli_query($con, "SELECT id, nome, cognome, codice_fiscale FROM soci ORDER BY cognome, nome");
if ($result_soci) {
    while ($socio = mysqli_fetch_assoc($result_soci)) {
        $soci_list[] = $socio;
    }
    mysqli_free_result($result_soci);
} else {
    // Gestisci errore se non si possono caricare i soci
    $messaggio = "Errore nel caricamento dell'elenco soci.";
    $messaggio_tipo = 'danger';
    error_log("Edit Auto - Fetch Soci Error: " . mysqli_error($con));
    // Potresti voler impedire il caricamento del form se i soci non sono disponibili
}


// --- Gestione Modifica Auto (POST) ---
if (isset($_POST['submit'])) {
    // Recupera e pulisci i dati dal form
    $id_socio = filter_input(INPUT_POST, 'id_socio', FILTER_VALIDATE_INT);
    $marca = trim($_POST['marca'] ?? '');
    $modello = trim($_POST['modello'] ?? '');
    $targa = trim(strtoupper($_POST['targa'] ?? '')); // Targa in maiuscolo
    $numero_telaio = trim($_POST['numero_telaio'] ?? '');
    $colore = trim($_POST['colore'] ?? '');
    $cilindrata_str = trim($_POST['cilindrata'] ?? '');
    $tipo_carburante = trim($_POST['tipo_carburante'] ?? '');
    $anno_immatricolazione_str = trim($_POST['anno_immatricolazione'] ?? '');
    $has_certificazione_asi = isset($_POST['has_certificazione_asi']) ? 1 : 0; // Checkbox
    $targa_oro = isset($_POST['targa_oro']) ? 1 : 0; // Checkbox
    $note = trim($_POST['note'] ?? '');
    // Gestione foto omessa

    // --- Validazione Input (Simile ad add-auto.php) ---
    if (empty($id_socio)) {
        $errori_form['id_socio'] = "Selezionare un socio è obbligatorio.";
    }
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
        $stmt_check_telaio = mysqli_prepare($con, "SELECT id FROM auto WHERE numero_telaio = ? AND id != ?"); // Aggiunto AND id != ?
        if ($stmt_check_telaio) {
            mysqli_stmt_bind_param($stmt_check_telaio, "si", $numero_telaio, $auto_id); // Bind anche $auto_id
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
        $stmt_update = mysqli_prepare($con,
            "UPDATE auto SET
                id_socio = ?, marca = ?, modello = ?, targa = ?, numero_telaio = ?,
                colore = ?, cilindrata = ?, tipo_carburante = ?, anno_immatricolazione = ?,
                has_certificazione_asi = ?, targa_oro = ?, note = ?
             WHERE id = ?" // Clausola WHERE
        );

        if ($stmt_update) {
            mysqli_stmt_bind_param($stmt_update, "isssssisiiisi", // Aggiunto 'i' alla fine per l'ID where
                $id_socio,
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
                $auto_id // ID dell'auto da aggiornare
            );

            if (mysqli_stmt_execute($stmt_update)) {
                mysqli_stmt_close($stmt_update);
                // Messaggio di successo e redirect
                $msg_success = "Auto (ID: " . $auto_id . ") aggiornata con successo.";
                header("Location: gestisci-auto.php?msg=" . urlencode($msg_success) . "&msg_type=success");
                exit();
            } else {
                $messaggio = "Errore durante l'aggiornamento dell'auto nel database.";
                $messaggio_tipo = 'danger';
                error_log("Admin Edit Auto Execute Error: " . mysqli_stmt_error($stmt_update));
            }
             if (isset($stmt_update) && $stmt_update) mysqli_stmt_close($stmt_update);
        } else {
            $messaggio = "Errore nella preparazione della query di aggiornamento.";
            $messaggio_tipo = 'danger';
            error_log("Admin Edit Auto Prepare Error: " . mysqli_error($con));
        }
    } else {
        // Se ci sono errori di validazione, costruisci un messaggio generale
        $messaggio = "Errore nel form. Controlla i campi evidenziati.";
        $messaggio_tipo = 'danger';
        // Riempi $auto_data con i dati POST per ripopolare il form
        // Includi anche i valori dei checkbox non inviati se non checkati
        $auto_data = $_POST;
        $auto_data['has_certificazione_asi'] = $has_certificazione_asi;
        $auto_data['targa_oro'] = $targa_oro;
        $auto_data['id'] = $auto_id; // Assicurati che l'ID sia presente
    }
} else {
    // --- Caricamento Iniziale Pagina (GET) ---
    // Recupera i dati attuali dell'auto dal DB
    $stmt_fetch = mysqli_prepare($con, "SELECT * FROM auto WHERE id = ?");
    if ($stmt_fetch) {
        mysqli_stmt_bind_param($stmt_fetch, "i", $auto_id);
        mysqli_stmt_execute($stmt_fetch);
        $result_fetch = mysqli_stmt_get_result($stmt_fetch);
        $auto_data = mysqli_fetch_assoc($result_fetch);
        mysqli_stmt_close($stmt_fetch);

        if (!$auto_data) {
            // Auto non trovata
            header("Location: gestisci-auto.php?msg=" . urlencode("Auto non trovata.") . "&msg_type=danger");
            exit();
        }
        // Non è necessario formattare le date qui perché non usiamo datetime-local
        // Formatta la quota pranzo (se esistesse in questa tabella)

    } else {
        // Errore nel recupero
        $messaggio = "Errore nel recupero dei dati dell'auto.";
        $messaggio_tipo = 'danger';
        error_log("Admin Edit Auto Fetch Prepare Error: " . mysqli_error($con));
        $auto_data = null; // Non mostrare il form
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
        <meta name="description" content="Modifica Auto Socio" />
        <meta name="author" content="" />
        <title>Modifica Auto | Sistema Admin</title>
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
                        <h1 class="mt-4">Modifica Auto (ID: <?php echo $auto_id; ?>)</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="gestisci-auto.php">Gestisci Auto</a></li>
                            <li class="breadcrumb-item active">Modifica Auto</li>
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

                        <?php if ($auto_data && !empty($soci_list)): // Mostra il form solo se i dati auto e soci sono stati caricati ?>
                            <div class="card mb-4">
                                <div class="card-header">
                                    <i class="fas fa-edit me-1"></i>
                                    Dettagli Auto
                                </div>
                                <div class="card-body">
                                    <form method="post" action="edit-auto.php?id=<?php echo $auto_id; // Mantieni ID in action ?>">

                                        <div class="mb-3">
                                            <label for="id_socio" class="form-label">Socio Proprietario <span class="text-danger">*</span></label>
                                            <select class="form-select <?php echo isset($errori_form['id_socio']) ? 'is-invalid' : ''; ?>" id="id_socio" name="id_socio" required>
                                                <option value="">-- Seleziona un socio --</option>
                                                <?php foreach ($soci_list as $socio): ?>
                                                    <option value="<?php echo $socio['id']; ?>" <?php echo (isset($auto_data['id_socio']) && $auto_data['id_socio'] == $socio['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($socio['cognome'] . ' ' . $socio['nome'] . ' (' . $socio['codice_fiscale'] . ')'); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <?php if (isset($errori_form['id_socio'])): ?>
                                                <div class="invalid-feedback"><?php echo $errori_form['id_socio']; ?></div>
                                            <?php endif; ?>
                                        </div>

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
                                                        Ha Certificazione ASI
                                                    </label>
                                                </div>
                                            </div>
                                             <div class="col-md-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" value="1" id="targa_oro" name="targa_oro" <?php echo !empty($auto_data['targa_oro']) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="targa_oro">
                                                        Targa Oro
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
                        <?php else: ?>
                             <div class="alert alert-warning">Impossibile caricare i dati dell'auto o l'elenco soci per la modifica.</div>
                        <?php endif; // Fine controllo $auto_data e $soci_list ?>
                    </div>
                </main>
                <?php include('../includes/footer.php');?>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
        <script src="../js/scripts.js"></script>
    </body>
</html>
