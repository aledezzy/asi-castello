<?php
session_start();
include_once 'includes/config.php'; // Include config per $con e costanti

// Verifica se l'utente è loggato
if (strlen($_SESSION['id'] ?? 0) == 0) {
    header('location:logout.php');
    exit();
}

$id_utente_loggato = $_SESSION['id'];
$messaggio = '';
$messaggio_tipo = 'info';
$errori_form = []; // Array per memorizzare errori specifici dei campi
$id_socio_utente = null; // ID socio associato all'utente loggato

// --- Costanti e Configurazioni Upload ---

define('UPLOAD_DIR', 'uploads/auto_foto');
define('MAX_FILE_SIZE', 2 * 1024 * 1024); // 2 MB
$allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

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
    error_log("User Add Mia Auto - Fetch User Error: " . mysqli_error($con));
}

// Se l'utente non è un socio, non può aggiungere auto
if ($id_socio_utente === null && empty($messaggio)) {
     $messaggio = "Devi essere registrato come socio per poter aggiungere auto.";
     $messaggio_tipo = 'warning';
}
// --- Funzione per Processare File Caricati ---
function process_uploaded_file($file_key, $allowed_mime_types, $allowed_extensions, $max_size, $upload_dir, $id_socio) {
    if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES[$file_key];
        $tmp_name = $file['tmp_name'];
        $file_size = $file['size'];

        // 1. Controllo Dimensione
        if ($file_size > $max_size) {return ['error' => "Il file '$file_key' supera la dimensione massima consentita (2MB)."];}
        if ($file_size === 0) {return ['error' => "Il file '$file_key' è vuoto."];}

        // 2. Controllo Tipo MIME
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $tmp_name);
        finfo_close($finfo);
        if (!in_array($mime_type, $allowed_mime_types)) {return ['error' => "Il tipo di file '$file_key' ($mime_type) non è consentito (solo JPG, PNG, GIF, WEBP)."];}

        // 3. Controllo Estensione
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowed_extensions)) {return ['error' => "L'estensione del file '$file_key' non è consentita."];}

        // 4. Verifica se è un'immagine valida
        if (@getimagesize($tmp_name) === false) {return ['error' => "Il file '$file_key' non sembra essere un'immagine valida."];}

        // 5. Genera Nome File Sicuro
        $safe_filename = sprintf('auto_%d_%s_%s.%s', $id_socio, time(), bin2hex(random_bytes(8)), $extension);

        // 6. Crea directory se non esiste
        if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true)) {return ['error' => "Impossibile creare la directory di upload."];}
        if (!is_writable($upload_dir)) {
            error_log("La directory di upload non è scrivibile: " . $upload_dir);
            return ['error' => "Errore interno del server (permessi directory upload)."];
        }

        // 7. Sposta il File
        $destination = $upload_dir . $safe_filename;
        if (move_uploaded_file($tmp_name, $destination)) {
            return ['success' => true, 'filename' => $safe_filename, 'filepath' => $destination];
        } else {
            error_log("Errore move_uploaded_file per $file_key: " . $file['error']);
            return ['error' => "Errore durante il salvataggio del file '$file_key'."];
        }

    } elseif (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] !== UPLOAD_ERR_NO_FILE) {
        // Gestisci altri errori di upload
        $upload_errors = [ /* ... (array errori come prima) ... */ ];
        $error_code = $_FILES[$file_key]['error'];
        return ['error' => $upload_errors[$error_code] ?? "Errore sconosciuto upload '$file_key'."];
    }
    return null; // Nessun file caricato
}


// --- Gestione Aggiunta Auto (POST) ---
if (isset($_POST['submit']) && $id_socio_utente !== null) {
    // Recupera dati form (come prima)
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

    // Variabili per nomi file foto
    $foto1_filename = null;
    $foto2_filename = null;
    $uploaded_files_to_delete_on_error = []; // Per cleanup

    // --- Validazione Input (come prima) ---
    if (empty($marca)) {$errori_form['marca'] = "La marca è obbligatoria.";}
    if (empty($modello)) {$errori_form['modello'] = "Il modello è obbligatorio.";}
    if (empty($targa)) {$errori_form['targa'] = "La targa è obbligatoria.";}
    // ... (altre validazioni come prima: telaio, cilindrata, anno) ...
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
        }
    }
    $cilindrata = null;
    if (!empty($cilindrata_str)) {
        if (!filter_var($cilindrata_str, FILTER_VALIDATE_INT) || $cilindrata_str <= 0) {
            $errori_form['cilindrata'] = "La cilindrata deve essere un numero intero positivo.";
        } else { $cilindrata = (int)$cilindrata_str; }
    }
    $anno_immatricolazione = null;
    if (!empty($anno_immatricolazione_str)) {
        if (!preg_match('/^\d{4}$/', $anno_immatricolazione_str) || (int)$anno_immatricolazione_str > date('Y') || (int)$anno_immatricolazione_str < 1900) {
             $errori_form['anno_immatricolazione'] = "L'anno di immatricolazione deve essere un anno valido (4 cifre).";
        } else { $anno_immatricolazione = $anno_immatricolazione_str; }
    }


    // --- Processa Upload Foto (SOLO se le validazioni base sono ok) ---
    if (empty($errori_form)) {
        // Processa Foto 1
        $result1 = process_uploaded_file('foto1', $allowed_mime_types, $allowed_extensions, MAX_FILE_SIZE, UPLOAD_DIR, $id_socio_utente);
        if ($result1) {
            if (isset($result1['error'])) {
                $errori_form['foto1'] = $result1['error'];
            } elseif (isset($result1['success'])) {
                $foto1_filename = $result1['filename'];
                $uploaded_files_to_delete_on_error[] = $result1['filepath']; // Aggiungi per eventuale cleanup
            }
        }

        // Processa Foto 2 (solo se foto1 non ha dato errori)
        if (!isset($errori_form['foto1'])) {
            $result2 = process_uploaded_file('foto2', $allowed_mime_types, $allowed_extensions, MAX_FILE_SIZE, UPLOAD_DIR, $id_socio_utente);
            if ($result2) {
                if (isset($result2['error'])) {
                    $errori_form['foto2'] = $result2['error'];
                } elseif (isset($result2['success'])) {
                    $foto2_filename = $result2['filename'];
                    $uploaded_files_to_delete_on_error[] = $result2['filepath']; // Aggiungi per eventuale cleanup
                }
            }
        }
    } // Fine processa upload

    // Se non ci sono errori (inclusi quelli di upload), procedi con l'inserimento DB
    if (empty($errori_form)) {
        $stmt_insert = mysqli_prepare($con,
            "INSERT INTO auto (id_socio, marca, modello, targa, numero_telaio, colore, cilindrata, tipo_carburante, anno_immatricolazione, has_certificazione_asi, targa_oro, note, foto1, foto2, data_inserimento)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())" // Aggiunti foto1, foto2
        );

        if ($stmt_insert) {
            // Aggiunti 'ss' alla fine per foto1 e foto2
            mysqli_stmt_bind_param($stmt_insert, "isssssisiiisss",
                $id_socio_utente,
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
                $foto2_filename  // Può essere null
            );

            if (mysqli_stmt_execute($stmt_insert)) {
                $id_nuova_auto = mysqli_insert_id($con);
                mysqli_stmt_close($stmt_insert);
                $msg_success = "La tua auto (ID: " . $id_nuova_auto . ") è stata aggiunta con successo.";
                header("Location: gestisci-auto.php?msg=" . urlencode($msg_success) . "&msg_type=success");
                exit();
            } else {
                // Errore DB: Cancella i file eventualmente caricati!
                foreach ($uploaded_files_to_delete_on_error as $filepath) {
                    if (file_exists($filepath)) {
                        unlink($filepath);
                    }
                }
                $messaggio = "Errore durante l'inserimento dell'auto nel database.";
                $messaggio_tipo = 'danger';
                error_log("User Add Mia Auto Execute Error: " . mysqli_stmt_error($stmt_insert));
                 if (isset($stmt_insert) && $stmt_insert) {mysqli_stmt_close($stmt_insert);} // Chiudi se ancora aperto
            }
        } else {
            // Errore preparazione: Cancella i file eventualmente caricati!
            foreach ($uploaded_files_to_delete_on_error as $filepath) {
                if (file_exists($filepath)) {
                    unlink($filepath);
                }
            }
            $messaggio = "Errore nella preparazione della query di inserimento.";
            $messaggio_tipo = 'danger';
            error_log("User Add Mia Auto Prepare Error: " . mysqli_error($con));
        }
    } else {
        // Se ci sono errori di validazione (inclusi upload), costruisci messaggio
        // Cancella i file eventualmente caricati se ci sono stati errori *dopo* l'upload
         foreach ($uploaded_files_to_delete_on_error as $filepath) {
            if (file_exists($filepath)) {
                unlink($filepath);
            }
         }
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
        <meta name="description" content="Aggiungi Nuova Auto" />
        <meta name="author" content="" />
        <title>Aggiungi Auto | Sistema Utente</title>
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
      <?php include_once'includes/navbar.php';?>
        <div id="layoutSidenav">
         <?php include_once'includes/sidebar.php';?>
            <div id="layoutSidenav_content">
                <main>
                    <div class="container-fluid px-4">
                        <h1 class="mt-4">Aggiungi Nuova Auto</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="welcome.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="gestisci-auto.php">Le Tue Auto</a></li>
                            <li class="breadcrumb-item active">Aggiungi Auto</li>
                        </ol>

                        <?php if (!empty($messaggio)): // Mostra tutti i messaggi qui ?>
                            <div class="alert alert-<?php echo htmlspecialchars($messaggio_tipo); ?> alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($messaggio); ?>
                                <?php if ($messaggio_tipo === 'danger' && !empty($errori_form)): ?>
                                    <ul>
                                        <?php foreach ($errori_form as $field => $errore): ?>
                                            <li><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $field))) . ': ' . htmlspecialchars($errore); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <?php if ($id_socio_utente !== null): // Mostra il form solo se l'utente è un socio ?>
                            <div class="card mb-4">
                                <div class="card-header">
                                    <i class="fas fa-car me-1"></i>
                                    Dettagli Auto
                                </div>
                                <div class="card-body">
                                    <!-- !!! Aggiunto enctype per upload file !!! -->
                                    <form method="post" action="add-mia-auto.php" enctype="multipart/form-data">

                                        <?php // Il campo select socio è rimosso ?>

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
                                                        Ho Certificazione ASI
                                                    </label>
                                                </div>
                                            </div>
                                             <div class="col-md-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" value="1" id="targa_oro" name="targa_oro" <?php echo isset($_POST['targa_oro']) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="targa_oro">
                                                        Ho Targa Oro
                                                    </label>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="note" class="form-label">Note Aggiuntive</label>
                                            <textarea class="form-control" id="note" name="note" rows="3"><?php echo htmlspecialchars($_POST['note'] ?? ''); ?></textarea>
                                        </div>

                                        <hr>
                                        <h5>Foto Auto (Max 2, JPG/PNG/GIF/WEBP, max 2MB)</h5>

                                        <div class="mb-3">
                                            <label for="foto1" class="form-label">Foto 1</label>
                                            <input class="form-control <?php echo isset($errori_form['foto1']) ? 'is-invalid' : ''; ?>" type="file" id="foto1" name="foto1" accept="image/jpeg, image/png, image/gif, image/webp">
                                            <?php if (isset($errori_form['foto1'])): ?>
                                                <div class="invalid-feedback"><?php echo $errori_form['foto1']; ?></div>
                                            <?php endif; ?>
                                        </div>

                                        <div class="mb-3">
                                            <label for="foto2" class="form-label">Foto 2</label>
                                            <input class="form-control <?php echo isset($errori_form['foto2']) ? 'is-invalid' : ''; ?>" type="file" id="foto2" name="foto2" accept="image/jpeg, image/png, image/gif, image/webp">
                                            <?php if (isset($errori_form['foto2'])): ?>
                                                <div class="invalid-feedback"><?php echo $errori_form['foto2']; ?></div>
                                            <?php endif; ?>
                                        </div>


                                        <div class="mt-4 d-flex justify-content-end">
                                             <a href="gestisci-auto.php" class="btn btn-secondary me-2">Annulla</a>
                                             <button type="submit" name="submit" class="btn btn-primary">Salva Auto</button>
                                        </div>

                                    </form>
                                </div>
                            </div>
                        <?php endif; // Fine controllo $id_socio_utente ?>
                    </div>
                </main>
                <?php include 'includes/footer.php';?>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
        <script src="js/scripts.js"></script>
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
