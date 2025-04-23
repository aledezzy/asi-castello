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

// --- Gestione Rimozione Manifestazione (GET) ---
// ... (codice rimozione invariato) ...
if (isset($_GET['del_id'])) {
    $manifestazione_id_to_delete = filter_input(INPUT_GET, 'del_id', FILTER_VALIDATE_INT);
    if ($manifestazione_id_to_delete) {
        // Nota: Cancellare una manifestazione cancellerà anche le iscrizioni collegate
        // a causa del vincolo FOREIGN KEY `ON DELETE CASCADE` nella tabella `iscrizioni_manifestazioni`.
        // Assicurati che questo sia il comportamento desiderato.
        $stmt_delete = mysqli_prepare($con, "DELETE FROM manifestazioni WHERE id = ?");
        if ($stmt_delete) {
            mysqli_stmt_bind_param($stmt_delete, "i", $manifestazione_id_to_delete);
            if (mysqli_stmt_execute($stmt_delete)) {
                $messaggio = "Manifestazione (ID: " . $manifestazione_id_to_delete . ") e relative iscrizioni rimosse con successo.";
                $messaggio_tipo = 'success';
            } else {
                $messaggio = "Errore durante la rimozione della manifestazione.";
                $messaggio_tipo = 'danger';
                error_log("Admin Manifestazione Deletion Error (ID: $manifestazione_id_to_delete): " . mysqli_stmt_error($stmt_delete));
            }
            mysqli_stmt_close($stmt_delete);
        } else {
            $messaggio = "Errore nella preparazione della query di rimozione manifestazione.";
            $messaggio_tipo = 'danger';
            error_log("Admin Manifestazione Delete Prepare Error: " . mysqli_error($con));
        }
        // Ricarica la pagina senza il parametro GET
        header("Location: manage-manifestazioni.php?msg=" . urlencode($messaggio) . "&msg_type=" . $messaggio_tipo);
        exit();
    }
}


// Recupera messaggio dalla URL se presente (dopo redirect)
// Modifica: Controlla se il messaggio viene da invia_email_manifestazione.php
if (isset($_GET['email_msg'])) {
    $messaggio = $_GET['email_msg'];
    $messaggio_tipo = $_GET['email_msg_type'] ?? 'info';
} elseif (isset($_GET['msg'])) {
    $messaggio = $_GET['msg'];
    $messaggio_tipo = $_GET['msg_type'] ?? 'info';
}


// --- Recupero Tutte le Manifestazioni ---
// ... (codice recupero manifestazioni invariato) ...
$all_manifestazioni = [];
$sql_all_manifestazioni = "SELECT
                               id,
                               titolo,
                               data_inizio,
                               data_creazione,
                               data_chiusura_iscrizioni,
                               luogo_ritrovo,
                               quota_pranzo
                           FROM
                               manifestazioni
                           ORDER BY
                               data_inizio DESC"; // Ordina per data inizio più recente

$result_all = mysqli_query($con, $sql_all_manifestazioni);
if ($result_all) {
    while ($row = mysqli_fetch_assoc($result_all)) {
        $all_manifestazioni[] = $row;
    }
    mysqli_free_result($result_all);
} else {
    $messaggio .= "<br>Errore nel recupero delle manifestazioni: " . mysqli_error($con);
    if($messaggio_tipo != 'danger') $messaggio_tipo = 'warning';
    error_log("Fetch All Manifestazioni Error: " . mysqli_error($con));
}


mysqli_close($con); // Chiudi qui la connessione, verrà riaperta nello script di invio email
?>
<!DOCTYPE html>
<html lang="it">
    <head>
        <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="description" content="Gestione Manifestazioni" />
        <meta name="author" content="" />
        <title>Gestisci Manifestazioni | Sistema Admin</title>
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@latest/dist/style.css" rel="stylesheet" />
        <link href="../css/styles.css" rel="stylesheet" />
        <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/js/all.min.js" crossorigin="anonymous"></script>
        <style>
            #datatablesManifestazioni td,
            #datatablesManifestazioni th {
                vertical-align: middle;
                font-size: 0.9rem;
            }
            .table-responsive { margin-top: 1rem; }
            .action-buttons a { margin-bottom: 5px; display: inline-block; } /* Spazio tra bottoni */
        </style>
    </head>
    <body class="sb-nav-fixed">
      <?php include_once('includes/navbar.php');?>
        <div id="layoutSidenav">
         <?php include_once('includes/sidebar.php');?>
            <div id="layoutSidenav_content">
                <main>
                    <div class="container-fluid px-4">
                        <h1 class="mt-4">Gestisci Manifestazioni</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Gestisci Manifestazioni</li>
                        </ol>

                        <?php if (!empty($messaggio)): ?>
                            <div class="alert alert-<?php echo htmlspecialchars($messaggio_tipo); ?> alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars(urldecode($messaggio)); // Decodifica messaggio da URL ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <div class="card mb-4">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-calendar-alt me-1"></i> Elenco Manifestazioni</span>
                                    <a href="add-manifestazione.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Aggiungi Manifestazione</a>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="datatablesManifestazioni">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Titolo</th>
                                                <th>Data Inizio</th>
                                                <th>Chiusura Iscrizioni</th>
                                                <th>Luogo Ritrovo</th>
                                                <th>Quota Pranzo (€)</th>
                                                <th>Data Creazione</th>
                                                <th>Azioni</th>
                                                <th>Azioni Email</th>
                                            </tr>
                                        </thead>
                                        <tfoot>
                                            <tr>
                                                <th>ID</th>
                                                <th>Titolo</th>
                                                <th>Data Inizio</th>
                                                <th>Chiusura Iscrizioni</th>
                                                <th>Luogo Ritrovo</th>
                                                <th>Quota Pranzo (€)</th>
                                                <th>Data Creazione</th>
                                                <th>Azioni</th>
                                                <th>Azioni Email</th>
                                            </tr>
                                        </tfoot>
                                        <tbody>
                                            <?php if (!empty($all_manifestazioni)): ?>
                                                <?php foreach ($all_manifestazioni as $manif): ?>
                                                <tr>
                                                    <td><?php echo $manif['id']; ?></td>
                                                    <td><?php echo htmlspecialchars($manif['titolo']); ?></td>
                                                    <td><?php echo htmlspecialchars(date("d/m/Y H:i", strtotime($manif['data_inizio']))); ?></td>
                                                    <td><?php echo htmlspecialchars(date("d/m/Y H:i", strtotime($manif['data_chiusura_iscrizioni']))); ?></td>
                                                    <td><?php echo htmlspecialchars($manif['luogo_ritrovo'] ?: '-'); ?></td>
                                                    <td><?php echo htmlspecialchars(number_format($manif['quota_pranzo'] ?? 0, 2, ',', '.')); ?></td>
                                                    <td><?php echo htmlspecialchars(date("d/m/Y H:i", strtotime($manif['data_creazione']))); ?></td>
                                                    <td class="action-buttons"> 
                                                        <a href="edit-manifestazione.php?id=<?php echo $manif['id']; ?>"
                                                           title="Modifica Manifestazione" class="btn btn-primary btn-sm me-1">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="manage-manifestazioni.php?del_id=<?php echo $manif['id']; ?>"
                                                           onClick="return confirm('Sei sicuro di voler cancellare questa manifestazione (ID: <?php echo $manif['id']; ?>)? ATTENZIONE: verranno cancellate anche tutte le iscrizioni associate!');"
                                                           title="Cancella Manifestazione" class="btn btn-danger btn-sm">
                                                            <i class="fa fa-trash" aria-hidden="true"></i>
                                                        </a>
                                                    </td>
                                                    <td class="action-buttons">
                                                        <a href="invia_email_manifestazione.php?id=<?php echo $manif['id']; ?>"
                                                           onClick="return confirm('Sei sicuro di voler inviare l\'email per la manifestazione \"<?php echo htmlspecialchars(addslashes($manif['titolo'])); ?>\" a tutti gli utenti registrati?');"
                                                           title="Invia Email a Tutti gli Utenti" class="btn btn-info btn-sm">
                                                            <i class="fas fa-envelope"></i> Invia Email
                                                        </a>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr><td colspan="9" class="text-center">Nessuna manifestazione trovata.</td></tr>
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
                const datatablesManifestazioni = document.getElementById('datatablesManifestazioni');
                if (datatablesManifestazioni) {
                    new simpleDatatables.DataTable(datatablesManifestazioni);
                }
            });
        </script>
    </body>
</html>