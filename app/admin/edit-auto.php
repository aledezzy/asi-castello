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


define('UPLOAD_DIR', '../uploads/auto_foto/'); 
define('MAX_FILE_SIZE', 2 * 1024 * 1024); // 2 MB
$allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

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
    $messaggio = "Errore nel caricamento dell'elenco soci.";
    $messaggio_tipo = 'danger';
    error_log("Admin Edit Auto - Fetch Soci Error: " . mysqli_error($con));
}

// --- Funzione Helper per Processare Upload (Identica alle altre pagine) ---
function process_uploaded_file($file_key, $allowed_mime_types, $allowed_extensions, $max_size, $upload_dir, $id_socio) {
    if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES[$file_key];
        $tmp_name = $file['tmp_name'];
        $file_size = $file['size'];

        // 1. Controllo Dimensione
        if ($file_size > $max_size) return ['error' => "Il file '$file_key' supera la dimensione massima consentita (2MB)."];
        if ($file_size === 0) return ['error' => "Il file '$file_key' è vuoto."];

        // 2. Controllo Tipo MIME
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $tmp_name);
        finfo_close($finfo);
        if (!in_array($mime_type, $allowed_mime_types)) return ['error' => "Il tipo di file '$file_key' ($mime_type) non è consentito (solo JPG, PNG, GIF, WEBP)."];

        // 3. Controllo Estensione
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowed_extensions)) return ['error' => "L'estensione del file '$file_key' non è consentita."];

        // 4. Verifica se è un'immagine valida
        if (@getimagesize($tmp_name) === false) return ['error' => "Il file '$file_key' non sembra essere un'immagine valida."];

        // 5. Genera Nome File Sicuro
        $safe_filename = sprintf('auto_%d_%s_%s.%s', $id_socio, time(), bin2hex(random_bytes(8)), $extension);

        // 6. Crea directory se non esiste
        // Assicurati che UPLOAD_DIR sia corretto e scrivibile dal server web
        if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true)) {
             error_log("Impossibile creare la directory di upload: " . $upload_dir);
             return ['error' => "Errore interno del server (creazione directory upload)."];
        }
        if (!is_writable($upload_dir)) {
            error_log("La directory di upload non è scrivibile: " . $upload_dir);
            return ['error' => "Errore interno del server (permessi directory upload)."];
        }

        // 7. Sposta il File
        $destination = rtrim($upload_dir, '/') . '/' . $safe_filename; // Assicura uno slash
        if (move_uploaded_file($tmp_name, $destination)) {
            return ['success' => true, 'filename' => $safe_filename, 'filepath' => $destination];
        } else {
            error_log("Errore move_uploaded_file per $file_key: " . $file['error'] . " da $tmp_name a $destination");
            return ['error' => "Errore durante il salvataggio del file '$file_key'."];
        }

    } elseif (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] !== UPLOAD_ERR_NO_FILE) {
        // Gestisci altri errori di upload
        $upload_errors = [
            UPLOAD_ERR_INI_SIZE   => "Il file supera la direttiva upload_max_filesize in php.ini.",
            UPLOAD_ERR_FORM_SIZE  => "Il file supera la direttiva MAX_FILE_SIZE specificata nel form HTML.",
            UPLOAD_ERR_PARTIAL    => "Il file è stato caricato solo parzialmente.",
            UPLOAD_ERR_NO_TMP_DIR => "Manca una directory temporanea.",
            UPLOAD_ERR_CANT_WRITE => "Impossibile scrivere il file su disco.",
            UPLOAD_ERR_EXTENSION  => "Un'estensione PHP ha interrotto l'upload del file.",
        ];
        $error_code = $_FILES[$file_key]['error'];
        return ['error' => $upload_errors[$error_code] ?? "Errore sconosciuto upload '$file_key'."];
    }
    return null; // Nessun file caricato
}


// --- Gestione Modifica Auto (POST) ---
if (isset($_POST['submit'])) {
    // Recupera e pulisci i dati dal form
    $id_socio = filter_input(INPUT_POST, 'id_socio', FILTER_VALIDATE_INT);
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

    // Recupera info gestione foto
    $existing_foto1 = filter_input(INPUT_POST, 'existing_foto1', FILTER_DEFAULT);
    $existing_foto2 = filter_input(INPUT_POST, 'existing_foto2', FILTER_DEFAULT);
    $delete_foto1 = isset($_POST['delete_foto1']);
    $delete_foto2 = isset($_POST['delete_foto2']);

    // Inizializza nomi file con quelli esistenti
    $foto1_filename = $existing_foto1;
    $foto2_filename = $existing_foto2;
    $uploaded_files_to_delete_on_error = []; // Per cleanup

    // --- Validazione Input (Simile ad add-auto.php) ---
    if (empty($id_socio)) $errori_form['id_socio'] = "Selezionare un socio è obbligatorio.";
    if (empty($marca)) $errori_form['marca'] = "La marca è obbligatoria.";
    if (empty($modello)) $errori_form['modello'] = "Il modello è obbligatorio.";
    if (empty($targa)) $errori_form['targa'] = "La targa è obbligatoria.";

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
        } else {
             $errori_form['db_check_telaio'] = "Errore nel controllo del numero di telaio.";
             error_log("Admin Edit Auto Check Telaio Prepare Error: " . mysqli_error($con));
        }
    }

    // Validazione cilindrata
    $cilindrata = null;
    if (!empty($cilindrata_str)) {
        if (!filter_var($cilindrata_str, FILTER_VALIDATE_INT) || $cilindrata_str <= 0) {
            $errori_form['cilindrata'] = "La cilindrata deve essere un numero intero positivo.";
        } else { $cilindrata = (int)$cilindrata_str; }
    }

    // Validazione anno immatricolazione
    $anno_immatricolazione = null;
    if (!empty($anno_immatricolazione_str)) {
        if (!preg_match('/^\d{4}$/', $anno_immatricolazione_str) || (int)$anno_immatricolazione_str > date('Y') || (int)$anno_immatricolazione_str < 1900) {
             $errori_form['anno_immatricolazione'] = "L'anno di immatricolazione deve essere un anno valido (4 cifre).";
        } else { $anno_immatricolazione = $anno_immatricolazione_str; }
    }

    // --- Processa Upload e Cancellazione Foto (SOLO se le validazioni base sono ok) ---
    if (empty($errori_form)) {
        // Processa Foto 1
        if ($delete_foto1 && !empty($existing_foto1)) {
            $file_to_delete = rtrim(UPLOAD_DIR, '/') . '/' . $existing_foto1;
            if (strpos($file_to_delete, '..') === false && file_exists($file_to_delete)) { @unlink($file_to_delete); }
            $foto1_filename = null;
        } else {
            // Passa l'ID del socio selezionato nel form alla funzione
            $result1 = process_uploaded_file('foto1', $allowed_mime_types, $allowed_extensions, MAX_FILE_SIZE, UPLOAD_DIR, $id_socio);
            if ($result1) {
                if (isset($result1['error'])) {
                    $errori_form['foto1'] = $result1['error'];
                } elseif (isset($result1['success'])) {
                    if (!empty($existing_foto1)) {
                        $old_file = rtrim(UPLOAD_DIR, '/') . '/' . $existing_foto1;
                        if (strpos($old_file, '..') === false && file_exists($old_file)) { @unlink($old_file); }
                    }
                    $foto1_filename = $result1['filename'];
                    $uploaded_files_to_delete_on_error[] = $result1['filepath'];
                }
            }
        }

        // Processa Foto 2 (solo se foto1 non ha dato errori)
        if (!isset($errori_form['foto1'])) {
             if ($delete_foto2 && !empty($existing_foto2)) {
                $file_to_delete = rtrim(UPLOAD_DIR, '/') . '/' . $existing_foto2;
                if (strpos($file_to_delete, '..') === false && file_exists($file_to_delete)) { @unlink($file_to_delete); }
                $foto2_filename = null;
            } else {
                // Passa l'ID del socio selezionato nel form alla funzione
                $result2 = process_uploaded_file('foto2', $allowed_mime_types, $allowed_extensions, MAX_FILE_SIZE, UPLOAD_DIR, $id_socio);
                if ($result2) {
                    if (isset($result2['error'])) {
                        $errori_form['foto2'] = $result2['error'];
                    } elseif (isset($result2['success'])) {
                        if (!empty($existing_foto2)) {
                            $old_file = rtrim(UPLOAD_DIR, '/') . '/' . $existing_foto2;
                            if (strpos($old_file, '..') === false && file_exists($old_file)) { @unlink($old_file); }
                        }
                        $foto2_filename = $result2['filename'];
                        $uploaded_files_to_delete_on_error[] = $result2['filepath'];
                    }
                }
            }
        }
    } // Fine processa upload

    // Se non ci sono errori, procedi con l'aggiornamento
    if (empty($errori_form)) {
        $stmt_update = mysqli_prepare($con,
            "UPDATE auto SET
                id_socio = ?, marca = ?, modello = ?, targa = ?, numero_telaio = ?,
                colore = ?, cilindrata = ?, tipo_carburante = ?, anno_immatricolazione = ?,
                has_certificazione_asi = ?, targa_oro = ?, note = ?,
                foto1 = ?, foto2 = ? -- Aggiunte colonne foto
             WHERE id = ?" // Clausola WHERE
        );

        if ($stmt_update) {
            // Aggiunti 'ss' per foto1, foto2 prima dell'ID 'i'
            mysqli_stmt_bind_param($stmt_update, "isssssisiiisssi",
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
                $foto1_filename, // Può essere null
                $foto2_filename, // Può essere null
                $auto_id
            );

            if (mysqli_stmt_execute($stmt_update)) {
                mysqli_stmt_close($stmt_update);
                $msg_success = "Auto (ID: " . $auto_id . ") aggiornata con successo.";
                header("Location: gestisci-auto.php?msg=" . urlencode($msg_success) . "&msg_type=success");
                exit();
            } else {
                // Errore DB: Cancella i file eventualmente caricati!
                foreach ($uploaded_files_to_delete_on_error as $filepath) {
                    if (file_exists($filepath)) { @unlink($filepath); }
                }
                $messaggio = "Errore durante l'aggiornamento dell'auto nel database.";
                $messaggio_tipo = 'danger';
                error_log("Admin Edit Auto Execute Error: " . mysqli_stmt_error($stmt_update));
            }
             if (isset($stmt_update) && $stmt_update) mysqli_stmt_close($stmt_update);
        } else {
            // Errore preparazione: Cancella i file eventualmente caricati!
            foreach ($uploaded_files_to_delete_on_error as $filepath) {
                if (file_exists($filepath)) { @unlink($filepath); }
            }
            $messaggio = "Errore nella preparazione della query di aggiornamento.";
            $messaggio_tipo = 'danger';
            error_log("Admin Edit Auto Prepare Error: " . mysqli_error($con));
        }
    } else {
        // Se ci sono errori di validazione, costruisci un messaggio generale
        // Cancella i file eventualmente caricati se ci sono stati errori *dopo* l'upload
         foreach ($uploaded_files_to_delete_on_error as $filepath) {
            if (file_exists($filepath)) { @unlink($filepath); }
         }
        $messaggio = "Errore nel form. Controlla i campi evidenziati.";
        $messaggio_tipo = 'danger';
        // Riempi $auto_data con i dati POST per ripopolare il form
        $auto_data = $_POST;
        $auto_data['has_certificazione_asi'] = $has_certificazione_asi;
        $auto_data['targa_oro'] = $targa_oro;
        $auto_data['id'] = $auto_id;
        // Mantieni i nomi delle foto esistenti se non sono state cancellate o sostituite
        $auto_data['foto1'] = (isset($errori_form['foto1']) && !$delete_foto1) ? $existing_foto1 : $foto1_filename;
        $auto_data['foto2'] = (isset($errori_form['foto2']) && !$delete_foto2) ? $existing_foto2 : $foto2_filename;
    }
} else {
    // --- Caricamento Iniziale Pagina (GET) ---
    // Recupera i dati attuali dell'auto dal DB (inclusi foto1, foto2)
    $stmt_fetch = mysqli_prepare($con, "SELECT * FROM auto WHERE id = ?");
    if ($stmt_fetch) {
        mysqli_stmt_bind_param($stmt_fetch, "i", $auto_id);
        mysqli_stmt_execute($stmt_fetch);
        $result_fetch = mysqli_stmt_get_result($stmt_fetch);
        $auto_data = mysqli_fetch_assoc($result_fetch);
        mysqli_stmt_close($stmt_fetch);

        if (!$auto_data) {
            header("Location: gestisci-auto.php?msg=" . urlencode("Auto non trovata.") . "&msg_type=danger");
            exit();
        }
    } else {
        $messaggio = "Errore nel recupero dei dati dell'auto.";
        $messaggio_tipo = 'danger';
        error_log("Admin Edit Auto Fetch Prepare Error: " . mysqli_error($con));
        $auto_data = null;
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
            .current-photo { max-height: 60px; margin-right: 10px; vertical-align: middle; }
            .delete-photo-label { margin-left: 5px; font-weight: normal; }
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

                        <?php if (!empty($messaggio) && $messaggio_tipo === 'danger'): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($messaggio); ?>
                                <?php if (!empty($errori_form)): ?>
                                    <ul>
                                        <?php foreach ($errori_form as $field => $errore): ?>
                                            <li><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $field))) . ': ' . htmlspecialchars($errore); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <?php if ($auto_data && !empty($soci_list)): ?>
                            <div class="card mb-4">
                                <div class="card-header">
                                    <i class="fas fa-edit me-1"></i>
                                    Dettagli Auto
                                </div>
                                <div class="card-body">
                                    <!-- !!! Aggiunto enctype per upload file !!! -->
                                    <form method="post" action="edit-auto.php?id=<?php echo $auto_id; ?>" enctype="multipart/form-data">

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

                                        <hr>
                                        <h5>Foto Auto (Max 2, JPG/PNG/GIF/WEBP, max 2MB)</h5>

                                        <!-- Foto 1 -->
                                        <div class="mb-3">
                                            <label for="foto1" class="form-label">Foto 1</label>
                                            <input class="form-control <?php echo isset($errori_form['foto1']) ? 'is-invalid' : ''; ?>" type="file" id="foto1" name="foto1" accept="image/jpeg, image/png, image/gif, image/webp">
                                            <?php if (isset($errori_form['foto1'])): ?>
                                                <div class="invalid-feedback"><?php echo $errori_form['foto1']; ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($auto_data['foto1'])): ?>
                                                <div class="mt-2">
                                                    <!-- Modifica il percorso src per puntare allo script nella directory principale dell'app -->
                                                    <img src="../visualizza_foto.php?file=<?php echo urlencode($auto_data['foto1']); ?>" alt="Foto 1 attuale" class="current-photo">
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="checkbox" id="delete_foto1" name="delete_foto1" value="1">
                                                        <label class="form-check-label delete-photo-label" for="delete_foto1">Elimina foto 1</label>
                                                    </div>
                                                    <input type="hidden" name="existing_foto1" value="<?php echo htmlspecialchars($auto_data['foto1']); ?>">
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Foto 2 -->
                                        <div class="mb-3">
                                            <label for="foto2" class="form-label">Foto 2</label>
                                            <input class="form-control <?php echo isset($errori_form['foto2']) ? 'is-invalid' : ''; ?>" type="file" id="foto2" name="foto2" accept="image/jpeg, image/png, image/gif, image/webp">
                                            <?php if (isset($errori_form['foto2'])): ?>
                                                <div class="invalid-feedback"><?php echo $errori_form['foto2']; ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($auto_data['foto2'])): ?>
                                                <div class="mt-2">
                                                     <!-- Modifica il percorso src per puntare allo script nella directory principale dell'app -->
                                                    <img src="../visualizza_foto.php?file=<?php echo urlencode($auto_data['foto2']); ?>" alt="Foto 2 attuale" class="current-photo">
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="checkbox" id="delete_foto2" name="delete_foto2" value="1">
                                                        <label class="form-check-label delete-photo-label" for="delete_foto2">Elimina foto 2</label>
                                                    </div>
                                                    <input type="hidden" name="existing_foto2" value="<?php echo htmlspecialchars($auto_data['foto2']); ?>">
                                                </div>
                                            <?php endif; ?>
                                        </div>


                                        <div class="mt-4 d-flex justify-content-end">
                                             <a href="gestisci-auto.php" class="btn btn-secondary me-2">Annulla</a>
                                             <button type="submit" name="submit" class="btn btn-primary">Salva Modifiche</button>
                                        </div>

                                    </form>
                                </div>
                            </div>
                        <?php else: ?>
                             <div class="alert alert-warning">Impossibile caricare i dati dell'auto o l'elenco soci per la modifica.</div>
                        <?php endif; ?>
                    </div>
                </main>
                <?php include('../includes/footer.php');?>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
        <script src="../js/scripts.js"></script>
        <!-- Script JavaScript per validazione frontend (opzionale ma utile) -->
        <script>
            const maxFileSize = 2 * 1024 * 1024; // 2MB
            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

            function validateFile(inputId) {
                 const input = document.getElementById(inputId);
                 const file = input.files[0];
                 if (file) {
                    if (file.size > maxFileSize) {
                        alert(`Il file ${inputId} supera la dimensione massima di 2MB.`);
                        input.value = ''; // Resetta campo
                        return false;
                    } else if (!allowedTypes.includes(file.type)) {
                         alert(`Tipo file non consentito per ${inputId}. Usare JPG, PNG, GIF o WEBP.`);
                         input.value = ''; // Resetta campo
                         return false;
                    }
                 }
                 return true;
            }

            document.getElementById('foto1').addEventListener('change', () => validateFile('foto1'));
            document.getElementById('foto2').addEventListener('change', () => validateFile('foto2'));
        </script>
    </body>
</html>
