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

// --- Gestione Cancellazione Iscrizione (quando il form viene inviato) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancella_iscrizione'])) {

    $id_iscrizione_da_cancellare = filter_input(INPUT_POST, 'id_iscrizione', FILTER_VALIDATE_INT);

    if ($id_iscrizione_da_cancellare) {
        // Verifica che l'iscrizione appartenga all'utente loggato prima di cancellare!
        $stmt_check = mysqli_prepare($con, "SELECT id FROM iscrizioni_manifestazioni WHERE id = ? AND id_user = ?");
        if ($stmt_check) {
            mysqli_stmt_bind_param($stmt_check, "ii", $id_iscrizione_da_cancellare, $id_utente_loggato);
            mysqli_stmt_execute($stmt_check);
            mysqli_stmt_store_result($stmt_check);

            if (mysqli_stmt_num_rows($stmt_check) > 0) {
                // L'iscrizione appartiene all'utente, procedi con la cancellazione
                mysqli_stmt_close($stmt_check); // Chiudi lo statement di controllo

                // Aggiungere qui eventuali controlli sulla data (es. non cancellabile se la manifestazione è iniziata?)

                $stmt_delete = mysqli_prepare($con, "DELETE FROM iscrizioni_manifestazioni WHERE id = ?");
                if ($stmt_delete) {
                    mysqli_stmt_bind_param($stmt_delete, "i", $id_iscrizione_da_cancellare);
                    if (mysqli_stmt_execute($stmt_delete)) {
                        $messaggio = "Iscrizione cancellata con successo.";
                        $messaggio_tipo = 'success';
                        // Nota: Se ci fosse un campo 'posti_disponibili' in 'manifestazioni',
                        // dovresti incrementarlo qui (idealmente in una transazione).
                    } else {
                        $messaggio = "Errore durante la cancellazione dell'iscrizione: " . mysqli_stmt_error($stmt_delete);
                        $messaggio_tipo = 'danger';
                        error_log("Errore cancellazione iscrizione (ID: $id_iscrizione_da_cancellare): " . mysqli_stmt_error($stmt_delete));
                    }
                    mysqli_stmt_close($stmt_delete);
                } else {
                    $messaggio = "Errore nella preparazione della query di cancellazione: " . mysqli_error($con);
                    $messaggio_tipo = 'danger';
                    error_log("Errore preparazione query cancellazione: " . mysqli_error($con));
                }

            } else {
                // L'iscrizione non appartiene all'utente o non esiste
                mysqli_stmt_close($stmt_check);
                $messaggio = "Non puoi cancellare questa iscrizione.";
                $messaggio_tipo = 'danger';
            }
        } else {
            $messaggio = "Errore nella preparazione della query di verifica: " . mysqli_error($con);
            $messaggio_tipo = 'danger';
            error_log("Errore preparazione query verifica cancellazione: " . mysqli_error($con));
        }
    } else {
        $messaggio = "ID iscrizione non valido.";
        $messaggio_tipo = 'danger';
    }
}


// --- Recupero Iscrizioni dell'Utente ---
$sql_mie_iscrizioni = "SELECT
                           i.id AS id_iscrizione,
                           i.numero_partecipanti,
                           i.data_iscrizione,
                           i.stato_pagamento,
                           i.car_marca,
                           i.car_modello,
                           i.car_targa,
                           i.note_iscrizione,
                           m.id AS id_manifestazione,
                           m.titolo AS titolo_manifestazione,
                           m.data_inizio AS data_inizio_manifestazione,
                           m.data_chiusura_iscrizioni
                       FROM
                           iscrizioni_manifestazioni i
                       JOIN
                           manifestazioni m ON i.id_manifestazione = m.id
                       WHERE
                           i.id_user = ?
                       ORDER BY
                           m.data_inizio ASC";

$stmt_mie = mysqli_prepare($con, $sql_mie_iscrizioni);
$mie_iscrizioni_dettagliate = [];
if($stmt_mie) {
    mysqli_stmt_bind_param($stmt_mie, "i", $id_utente_loggato);
    mysqli_stmt_execute($stmt_mie);
    $result_mie = mysqli_stmt_get_result($stmt_mie);
    while ($row = mysqli_fetch_assoc($result_mie)) {
        $mie_iscrizioni_dettagliate[] = $row; // Aggiungi ogni riga all'array
    }
    mysqli_stmt_close($stmt_mie);
} else {
    $messaggio .= "<br>Errore nella preparazione query mie iscrizioni: " . mysqli_error($con);
    $messaggio_tipo = 'danger';
    error_log("Errore preparazione query mie iscrizioni (pagina iscrizioni.php): " . mysqli_error($con));
}

mysqli_close($con); // Chiudi la connessione al database alla fine dello script PHP
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Le Tue Iscrizioni</title>
    <link href="css/styles.css" rel="stylesheet" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/js/all.min.js" crossorigin="anonymous"></script>
    <style>
        /* Stili aggiuntivi specifici per questa pagina */
        body { padding-top: 56px; /* Altezza navbar se fissa */}
        .container-iscrizioni { max-width: 960px; margin: 20px auto; padding: 15px; }
        .iscrizione-item { border: 1px solid #dee2e6; margin-bottom: 1.5rem; border-radius: .25rem; background-color: #fff; }
        .iscrizione-header { background-color: rgba(0,0,0,.03); padding: .75rem 1.25rem; border-bottom: 1px solid rgba(0,0,0,.125); display: flex; justify-content: space-between; align-items: center; }
        .iscrizione-header h5 { margin-bottom: 0; }
        .iscrizione-body { padding: 1.25rem; }
        .iscrizione-body p { margin-bottom: .5rem; }
        .iscrizione-body strong { color: #495057; }
        .btn-cancella { font-size: 0.8rem; padding: 0.25rem 0.5rem; }
        .badge { font-size: 0.85em; }
    </style>
</head>
<body class="sb-nav-fixed">

    <?php include_once('includes/navbar.php');?>

    <div id="layoutSidenav">
        <?php include_once('includes/sidebar.php');?>

        <div id="layoutSidenav_content">
            <main>
                <div class="container-fluid px-4 container-iscrizioni"> <!-- Aggiunto container-iscrizioni -->

                    <h1 class="mt-4">Le Tue Iscrizioni</h1>
                     <ol class="breadcrumb mb-4">
                        <li class="breadcrumb-item"><a href="welcome.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Le Tue Iscrizioni</li>
                    </ol>

                    <?php if ($messaggio): ?>
                        <div class="alert alert-<?php echo $messaggio_tipo; ?> alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($messaggio); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($mie_iscrizioni_dettagliate)): ?>
                        <?php foreach ($mie_iscrizioni_dettagliate as $isc): ?>
                            <?php
                                // Determina se la cancellazione è permessa (es. prima della chiusura iscrizioni)
                                $data_chiusura_dt = new DateTime($isc['data_chiusura_iscrizioni']);
                                $ora_attuale_dt = new DateTime();
                                $cancellazione_permessa = ($ora_attuale_dt < $data_chiusura_dt);
                            ?>
                            <div class="iscrizione-item shadow-sm">
                                <div class="iscrizione-header">
                                    <h5><?php echo htmlspecialchars($isc['titolo_manifestazione']); ?></h5>
                                    <?php if ($cancellazione_permessa): ?>
                                        <form action="iscrizioni.php" method="post" onsubmit="return confirm('Sei sicuro di voler cancellare questa iscrizione?');" style="margin-bottom: 0;">
                                            <input type="hidden" name="id_iscrizione" value="<?php echo $isc['id_iscrizione']; ?>">
                                            <button type="submit" name="cancella_iscrizione" class="btn btn-danger btn-sm btn-cancella">
                                                <i class="fas fa-trash-alt me-1"></i>Cancella Iscrizione
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Iscrizioni Chiuse</span>
                                    <?php endif; ?>
                                </div>
                                <div class="iscrizione-body">
                                    <p><strong>Data Manifestazione:</strong> <?php echo date("d/m/Y H:i", strtotime($isc['data_inizio_manifestazione'])); ?></p>
                                    <p><strong>Data Iscrizione:</strong> <?php echo date("d/m/Y H:i", strtotime($isc['data_iscrizione'])); ?></p>
                                    <p><strong>Numero Partecipanti:</strong> <?php echo htmlspecialchars($isc['numero_partecipanti']); ?></p>
                                    <p><strong>Auto:</strong> <?php echo htmlspecialchars($isc['car_marca'] . ' ' . $isc['car_modello'] . ' (' . $isc['car_targa'] . ')'); ?></p>
                                    <p><strong>Stato Pagamento:</strong> <span class="badge bg-<?php echo ($isc['stato_pagamento'] == 'Pagato') ? 'success' : (($isc['stato_pagamento'] == 'In attesa') ? 'warning' : 'secondary'); ?>"><?php echo htmlspecialchars($isc['stato_pagamento']); ?></span></p>
                                    <?php if (!empty($isc['note_iscrizione'])): ?>
                                        <p><strong>Note:</strong> <?php echo nl2br(htmlspecialchars($isc['note_iscrizione'])); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="alert alert-info" role="alert">
                            Al momento non risulti iscritto a nessuna manifestazione.
                            <a href="manifestazioni.php" class="alert-link">Visualizza le manifestazioni disponibili</a>.
                        </div>
                    <?php endif; ?>

                </div> <!-- Fine container-fluid -->
            </main>
            <?php include('includes/footer.php');?>
        </div> <!-- Fine layoutSidenav_content -->
    </div> <!-- Fine layoutSidenav -->

    <!-- Script Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <!-- Tuo script JS -->
    <script src="js/scripts.js"></script>

</body>
</html>
