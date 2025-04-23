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
$id_socio_utente = null; // ID socio associato all'utente loggato

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
if ($id_socio_utente === null && empty($messaggio)) { // Controlla $messaggio per evitare sovrascrittura
     $messaggio = "Devi essere registrato come socio per poter aggiungere auto.";
     $messaggio_tipo = 'warning';
     // Non serve chiudere la connessione qui, verrà chiusa alla fine
     // mysqli_close($con);
     // Potresti reindirizzare o semplicemente non mostrare il form (gestito dopo)
}


// --- Gestione Aggiunta Auto (POST) ---
// Procedi solo se l'utente è un socio
if (isset($_POST['submit']) && $id_socio_utente !== null) {
    // Recupera e pulisci i dati dal form (simile ad admin/add-auto.php, ma senza id_socio)
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
    // Gestione foto omessa per semplicità iniziale

    // --- Validazione Input (Identica ad admin/add-auto.php, tranne id_socio) ---
    if (empty($marca)) {
        $errori_form['marca'] = "La marca è obbligatoria.";
    }
    if (empty($modello)) {
        $errori_form['modello'] = "Il modello è obbligatorio.";
    }
    if (empty($targa)) {
        $errori_form['targa'] = "La targa è obbligatoria.";
    }

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

    // Se non ci sono errori, procedi con l'inserimento
    if (empty($errori_form)) {
        $stmt_insert = mysqli_prepare($con,
            "INSERT INTO auto (id_socio, marca, modello, targa, numero_telaio, colore, cilindrata, tipo_carburante, anno_immatricolazione, has_certificazione_asi, targa_oro, note, data_inserimento)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        );

        if ($stmt_insert) {
            // Usa $id_socio_utente recuperato all'inizio
            mysqli_stmt_bind_param($stmt_insert, "isssssisiiis",
                $id_socio_utente, // ID del socio loggato
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
                $note
            );

            if (mysqli_stmt_execute($stmt_insert)) {
                $id_nuova_auto = mysqli_insert_id($con);
                mysqli_stmt_close($stmt_insert);
                // Messaggio di successo e redirect alla pagina utente
                $msg_success = "La tua auto (ID: " . $id_nuova_auto . ") è stata aggiunta con successo.";
                header("Location: gestisci-auto.php?msg=" . urlencode($msg_success) . "&msg_type=success");
                exit();
            } else {
                $messaggio = "Errore durante l'inserimento dell'auto nel database.";
                $messaggio_tipo = 'danger';
                error_log("User Add Mia Auto Execute Error: " . mysqli_stmt_error($stmt_insert));
            }
             if (isset($stmt_insert) && $stmt_insert) mysqli_stmt_close($stmt_insert);
        } else {
            $messaggio = "Errore nella preparazione della query di inserimento.";
            $messaggio_tipo = 'danger';
            error_log("User Add Mia Auto Prepare Error: " . mysqli_error($con));
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
      <?php include_once('includes/navbar.php');?>
        <div id="layoutSidenav">
         <?php include_once('includes/sidebar.php');?>
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
                                        <?php foreach ($errori_form as $errore): ?>
                                            <li><?php echo htmlspecialchars($errore); ?></li>
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
                                    <form method="post" action="add-mia-auto.php">

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

                                        <?php /* Sezione Upload Foto (Omessa) */ ?>

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
                <?php include('includes/footer.php');?>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
        <script src="js/scripts.js"></script>
    </body>
</html>
