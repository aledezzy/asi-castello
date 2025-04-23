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

// --- Gestione Rimozione Iscrizione (GET) ---
if (isset($_GET['del_iscrizione_id'])) {
    $iscrizione_id_to_delete = filter_input(INPUT_GET, 'del_iscrizione_id', FILTER_VALIDATE_INT);
    if ($iscrizione_id_to_delete) {
        // Gli admin possono cancellare qualsiasi iscrizione
        $stmt_delete = mysqli_prepare($con, "DELETE FROM iscrizioni_manifestazioni WHERE id = ?");
        if ($stmt_delete) {
            mysqli_stmt_bind_param($stmt_delete, "i", $iscrizione_id_to_delete);
            if (mysqli_stmt_execute($stmt_delete)) {
                $messaggio = "Iscrizione (ID: " . $iscrizione_id_to_delete . ") rimossa con successo.";
                $messaggio_tipo = 'success';
                // Nota: Se ci fosse un campo 'posti_disponibili' in 'manifestazioni',
                // e l'iscrizione cancellata era 'confermata', dovresti incrementarlo qui.
                // Questo richiederebbe prima un SELECT per ottenere id_manifestazione e otp_confirmed.
            } else {
                $messaggio = "Errore durante la rimozione dell'iscrizione.";
                $messaggio_tipo = 'danger';
                error_log("Admin Inscription Deletion Error (ID: $iscrizione_id_to_delete): " . mysqli_stmt_error($stmt_delete));
            }
            mysqli_stmt_close($stmt_delete);
        } else {
            $messaggio = "Errore nella preparazione della query di rimozione iscrizione.";
            $messaggio_tipo = 'danger';
            error_log("Admin Inscription Delete Prepare Error: " . mysqli_error($con));
        }
        // Ricarica la pagina senza il parametro GET
        header("Location: iscrizioni-manifestazioni-users.php?msg=" . urlencode($messaggio) . "&msg_type=" . $messaggio_tipo);
        exit();
    }
}

// Recupera messaggio dalla URL se presente (dopo redirect)
if (isset($_GET['msg'])) {
    $messaggio = $_GET['msg'];
    $messaggio_tipo = $_GET['msg_type'] ?? 'info';
}

// --- Recupero Tutte le Iscrizioni ---
$all_iscrizioni = [];
$sql_all_iscrizioni = "SELECT
                           i.id AS id_iscrizione,
                           i.numero_partecipanti,
                           i.data_iscrizione,
                           i.stato_pagamento,
                           i.car_marca,
                           i.car_modello,
                           i.car_targa,
                           i.note_iscrizione,
                           i.otp_confirmed,
                           m.id AS id_manifestazione,
                           m.titolo AS titolo_manifestazione,
                           m.data_inizio AS data_inizio_manifestazione,
                           u.id AS id_utente,
                           u.fname AS nome_utente,
                           u.lname AS cognome_utente,
                           u.email AS email_utente
                       FROM
                           iscrizioni_manifestazioni i
                       JOIN
                           manifestazioni m ON i.id_manifestazione = m.id
                       JOIN
                           users u ON i.id_user = u.id
                       ORDER BY
                           m.data_inizio DESC, i.data_iscrizione DESC"; // Ordina per manifestazione più recente, poi iscrizione più recente

$result_all = mysqli_query($con, $sql_all_iscrizioni);
if ($result_all) {
    while ($row = mysqli_fetch_assoc($result_all)) {
        $all_iscrizioni[] = $row;
    }
    mysqli_free_result($result_all);
} else {
    $messaggio .= "<br>Errore nel recupero delle iscrizioni: " . mysqli_error($con);
    if($messaggio_tipo != 'danger') $messaggio_tipo = 'warning';
    error_log("Fetch All Inscriptions Error: " . mysqli_error($con));
}

mysqli_close($con);
?>
<!DOCTYPE html>
<html lang="it">
    <head>
        <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="description" content="Gestione Iscrizioni Utenti alle Manifestazioni" />
        <meta name="author" content="" />
        <title>Gestisci Iscrizioni Manifestazioni | Sistema Admin</title>
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@latest/dist/style.css" rel="stylesheet" />
        <link href="../css/styles.css" rel="stylesheet" />
        <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/js/all.min.js" crossorigin="anonymous"></script>
        <style>
            /* Stili per rendere la tabella più leggibile */
            #datatablesInscriptions td,
            #datatablesInscriptions th {
                vertical-align: middle;
                font-size: 0.9rem; /* Riduci leggermente la dimensione del font */
            }
            .badge { font-size: 0.8em; }
            .table-responsive { margin-top: 1rem; } /* Aggiungi spazio sopra la tabella */
        </style>
    </head>
    <body class="sb-nav-fixed">
      <?php include_once('includes/navbar.php');?>
        <div id="layoutSidenav">
         <?php include_once('includes/sidebar.php');?>
            <div id="layoutSidenav_content">
                <main>
                    <div class="container-fluid px-4">
                        <h1 class="mt-4">Iscrizioni alle Manifestazioni</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Iscrizioni Manifestazioni</li>
                        </ol>

                        <?php if (!empty($messaggio)): ?>
                            <div class="alert alert-<?php echo htmlspecialchars($messaggio_tipo); ?> alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($messaggio); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-clipboard-list me-1"></i>
                                Elenco Iscrizioni Utenti
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="datatablesInscriptions"> <!-- ID univoco per datatables -->
                                        <thead>
                                            <tr>
                                                <th>ID Iscr.</th>
                                                <th>Manifestazione</th>
                                                <th>Utente</th>
                                                <th>Email</th>
                                                <th>Data Iscr.</th>
                                                <th>Partecip.</th>
                                                <th>Auto</th>
                                                <th>Stato Pag.</th>
                                                <th>Confermata</th>
                                                <th>Azione</th>
                                            </tr>
                                        </thead>
                                        <tfoot>
                                            <tr>
                                                <th>ID Iscr.</th>
                                                <th>Manifestazione</th>
                                                <th>Utente</th>
                                                <th>Email</th>
                                                <th>Data Iscr.</th>
                                                <th>Partecip.</th>
                                                <th>Auto</th>
                                                <th>Stato Pag.</th>
                                                <th>Confermata</th>
                                                <th>Azione</th>
                                            </tr>
                                        </tfoot>
                                        <tbody>
                                            <?php if (!empty($all_iscrizioni)): ?>
                                                <?php foreach ($all_iscrizioni as $isc): ?>
                                                <tr>
                                                    <td><?php echo $isc['id_iscrizione']; ?></td>
                                                    <td>
                                                        <?php echo htmlspecialchars($isc['titolo_manifestazione']); ?><br>
                                                        <small class="text-muted"><?php echo date("d/m/y", strtotime($isc['data_inizio_manifestazione'])); ?></small>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($isc['nome_utente'] . ' ' . $isc['cognome_utente']); ?></td>
                                                    <td><?php echo htmlspecialchars($isc['email_utente']); ?></td>
                                                    <td><?php echo htmlspecialchars(date("d/m/Y H:i", strtotime($isc['data_iscrizione']))); ?></td>
                                                    <td><?php echo htmlspecialchars($isc['numero_partecipanti']); ?></td>
                                                    <td><?php echo htmlspecialchars($isc['car_marca'] . ' ' . $isc['car_modello'] . ' (' . $isc['car_targa'] . ')'); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo ($isc['stato_pagamento'] == 'Pagato') ? 'success' : (($isc['stato_pagamento'] == 'In attesa') ? 'warning' : 'secondary'); ?>">
                                                            <?php echo htmlspecialchars($isc['stato_pagamento']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if ($isc['otp_confirmed'] == 1): ?>
                                                            <span class="badge bg-success">Sì</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger">No</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <a href="iscrizioni-manifestazioni-users.php?del_iscrizione_id=<?php echo $isc['id_iscrizione']; ?>"
                                                           onClick="return confirm('Sei sicuro di voler cancellare questa iscrizione (ID: <?php echo $isc['id_iscrizione']; ?>)?');"
                                                           title="Cancella Iscrizione" class="btn btn-danger btn-sm">
                                                            <i class="fa fa-trash" aria-hidden="true"></i>
                                                        </a>
                                                        <?php // Aggiungi qui altri pulsanti se necessario (es. modifica, segna come pagato, etc.) ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr><td colspan="10" class="text-center">Nessuna iscrizione trovata.</td></tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                    </div>
                </main>
                <?php include('../includes/footer.php');?>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
        <script src="../js/scripts.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/simple-datatables@latest" crossorigin="anonymous"></script>
        <!-- Inizializza DataTables -->
        <script>
            window.addEventListener('DOMContentLoaded', event => {
                const datatablesInscriptions = document.getElementById('datatablesInscriptions');
                if (datatablesInscriptions) {
                    new simpleDatatables.DataTable(datatablesInscriptions);
                }
            });
        </script>
    </body>
</html>
