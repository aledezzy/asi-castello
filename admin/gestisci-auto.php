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

// --- Gestione Rimozione Auto (GET) ---
if (isset($_GET['del_id'])) {
    $auto_id_to_delete = filter_input(INPUT_GET, 'del_id', FILTER_VALIDATE_INT);
    if ($auto_id_to_delete) {
        // Prima di cancellare l'auto, potresti voler gestire la foto associata (cancellarla dal server)
        // $stmt_get_foto = mysqli_prepare($con, "SELECT foto FROM auto WHERE id = ?");
        // ... recupera percorso foto ...
        // if ($percorso_foto && file_exists($percorso_foto)) { unlink($percorso_foto); }

        // Cancella l'auto dal DB
        // Nota: Se ci sono iscrizioni collegate a questa auto tramite `id_auto_socio`,
        // il vincolo `ON DELETE SET NULL` imposterà quel campo a NULL nelle iscrizioni.
        $stmt_delete = mysqli_prepare($con, "DELETE FROM auto WHERE id = ?");
        if ($stmt_delete) {
            mysqli_stmt_bind_param($stmt_delete, "i", $auto_id_to_delete);
            if (mysqli_stmt_execute($stmt_delete)) {
                $messaggio = "Auto (ID: " . $auto_id_to_delete . ") rimossa con successo.";
                $messaggio_tipo = 'success';
            } else {
                $messaggio = "Errore durante la rimozione dell'auto.";
                $messaggio_tipo = 'danger';
                error_log("Admin Auto Deletion Error (ID: $auto_id_to_delete): " . mysqli_stmt_error($stmt_delete));
            }
            mysqli_stmt_close($stmt_delete);
        } else {
            $messaggio = "Errore nella preparazione della query di rimozione auto.";
            $messaggio_tipo = 'danger';
            error_log("Admin Auto Delete Prepare Error: " . mysqli_error($con));
        }
        // Ricarica la pagina senza il parametro GET
        header("Location: gestisci-auto.php?msg=" . urlencode($messaggio) . "&msg_type=" . $messaggio_tipo);
        exit();
    }
}

// Recupera messaggio dalla URL se presente (dopo redirect)
if (isset($_GET['msg'])) {
    $messaggio = $_GET['msg'];
    $messaggio_tipo = $_GET['msg_type'] ?? 'info';
}

// --- Recupero Tutte le Auto con Info Socio ---
$all_auto = [];
$sql_all_auto = "SELECT
                     a.id,
                     a.marca,
                     a.modello,
                     a.targa,
                     a.anno_immatricolazione,
                     a.has_certificazione_asi,
                     a.targa_oro,
                     a.data_inserimento,
                     s.id AS id_socio,
                     s.nome AS nome_socio,
                     s.cognome AS cognome_socio
                 FROM
                     auto a
                 JOIN
                     soci s ON a.id_socio = s.id
                 ORDER BY
                     s.cognome, s.nome, a.marca, a.modello"; // Ordina per socio, poi marca/modello

$result_all = mysqli_query($con, $sql_all_auto);
if ($result_all) {
    while ($row = mysqli_fetch_assoc($result_all)) {
        $all_auto[] = $row;
    }
    mysqli_free_result($result_all);
} else {
    $messaggio .= "<br>Errore nel recupero delle auto: " . mysqli_error($con);
    if($messaggio_tipo != 'danger') $messaggio_tipo = 'warning';
    error_log("Fetch All Auto Error: " . mysqli_error($con));
}

mysqli_close($con);
?>
<!DOCTYPE html>
<html lang="it">
    <head>
        <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="description" content="Gestione Auto Soci" />
        <meta name="author" content="" />
        <title>Gestisci Auto | Sistema Admin</title>
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@latest/dist/style.css" rel="stylesheet" />
        <link href="../css/styles.css" rel="stylesheet" />
        <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/js/all.min.js" crossorigin="anonymous"></script>
        <style>
            #datatablesAuto td,
            #datatablesAuto th {
                vertical-align: middle;
                font-size: 0.9rem;
            }
            .table-responsive { margin-top: 1rem; }
            .badge { font-size: 0.8em; }
        </style>
    </head>
    <body class="sb-nav-fixed">
      <?php include_once('includes/navbar.php');?>
        <div id="layoutSidenav">
         <?php include_once('includes/sidebar.php');?>
            <div id="layoutSidenav_content">
                <main>
                    <div class="container-fluid px-4">
                        <h1 class="mt-4">Gestisci Auto Soci</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Gestisci Auto</li>
                        </ol>

                        <?php if (!empty($messaggio)): ?>
                            <div class="alert alert-<?php echo htmlspecialchars($messaggio_tipo); ?> alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($messaggio); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <div class="card mb-4">
                             <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-car me-1"></i> Elenco Auto Registrate</span>
                                    <a href="add-auto.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Aggiungi Auto</a>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="datatablesAuto"> <!-- ID univoco per datatables -->
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Socio</th>
                                                <th>Marca</th>
                                                <th>Modello</th>
                                                <th>Targa</th>
                                                <th>Anno</th>
                                                <th>ASI</th>
                                                <th>Targa Oro</th>
                                                <th>Data Ins.</th>
                                                <th>Azione</th>
                                            </tr>
                                        </thead>
                                        <tfoot>
                                            <tr>
                                                <th>ID</th>
                                                <th>Socio</th>
                                                <th>Marca</th>
                                                <th>Modello</th>
                                                <th>Targa</th>
                                                <th>Anno</th>
                                                <th>ASI</th>
                                                <th>Targa Oro</th>
                                                <th>Data Ins.</th>
                                                <th>Azione</th>
                                            </tr>
                                        </tfoot>
                                        <tbody>
                                            <?php if (!empty($all_auto)): ?>
                                                <?php foreach ($all_auto as $auto): ?>
                                                <tr>
                                                    <td><?php echo $auto['id']; ?></td>
                                                    <td>
                                                        <?php echo htmlspecialchars($auto['cognome_socio'] . ' ' . $auto['nome_socio']); ?>
                                                        <small>(ID: <?php echo $auto['id_socio']; ?>)</small>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($auto['marca']); ?></td>
                                                    <td><?php echo htmlspecialchars($auto['modello']); ?></td>
                                                    <td><?php echo htmlspecialchars($auto['targa']); ?></td>
                                                    <td><?php echo htmlspecialchars($auto['anno_immatricolazione'] ?: '-'); ?></td>
                                                    <td>
                                                        <?php if ($auto['has_certificazione_asi'] == 1): ?>
                                                            <span class="badge bg-success">Sì</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">No</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($auto['targa_oro'] == 1): ?>
                                                            <span class="badge bg-warning text-dark">Sì</span>
                                                        <?php else: ?>
                                                             <span class="badge bg-secondary">No</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars(date("d/m/Y", strtotime($auto['data_inserimento']))); ?></td>
                                                    <td>
                                                        <a href="edit-auto.php?id=<?php echo $auto['id']; ?>"
                                                           title="Modifica Auto" class="btn btn-primary btn-sm me-1">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="gestisci-auto.php?del_id=<?php echo $auto['id']; ?>"
                                                           onClick="return confirm('Sei sicuro di voler cancellare questa auto (ID: <?php echo $auto['id']; ?>)?');"
                                                           title="Cancella Auto" class="btn btn-danger btn-sm">
                                                            <i class="fa fa-trash" aria-hidden="true"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr><td colspan="10" class="text-center">Nessuna auto trovata.</td></tr>
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
                const datatablesAuto = document.getElementById('datatablesAuto');
                if (datatablesAuto) {
                    new simpleDatatables.DataTable(datatablesAuto);
                }
            });
        </script>
    </body>
</html>
