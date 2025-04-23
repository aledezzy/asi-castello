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
$id_socio_utente = null; // ID socio associato all'utente loggato
$mie_auto = []; // Array per le auto dell'utente

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
    error_log("User Gestisci Auto - Fetch User Error: " . mysqli_error($con));
}

// --- Gestione Rimozione Auto (GET) ---
if (isset($_GET['del_id']) && $id_socio_utente !== null) { // Solo se l'utente è un socio
    $auto_id_to_delete = filter_input(INPUT_GET, 'del_id', FILTER_VALIDATE_INT);
    if ($auto_id_to_delete) {
        // Verifica che l'auto appartenga al socio loggato prima di cancellare!
        $stmt_check_owner = mysqli_prepare($con, "SELECT id FROM auto WHERE id = ? AND id_socio = ?");
        if ($stmt_check_owner) {
            mysqli_stmt_bind_param($stmt_check_owner, "ii", $auto_id_to_delete, $id_socio_utente);
            mysqli_stmt_execute($stmt_check_owner);
            mysqli_stmt_store_result($stmt_check_owner);

            if (mysqli_stmt_num_rows($stmt_check_owner) > 0) {
                // L'auto appartiene all'utente, procedi con la cancellazione
                mysqli_stmt_close($stmt_check_owner); // Chiudi check

                // Cancella l'auto dal DB
                $stmt_delete = mysqli_prepare($con, "DELETE FROM auto WHERE id = ?");
                if ($stmt_delete) {
                    mysqli_stmt_bind_param($stmt_delete, "i", $auto_id_to_delete);
                    if (mysqli_stmt_execute($stmt_delete)) {
                        $messaggio = "Auto (ID: " . $auto_id_to_delete . ") rimossa con successo.";
                        $messaggio_tipo = 'success';
                    } else {
                        $messaggio = "Errore durante la rimozione dell'auto.";
                        $messaggio_tipo = 'danger';
                        error_log("User Auto Deletion Error (ID: $auto_id_to_delete): " . mysqli_stmt_error($stmt_delete));
                    }
                    mysqli_stmt_close($stmt_delete);
                } else {
                    $messaggio = "Errore nella preparazione della query di rimozione auto.";
                    $messaggio_tipo = 'danger';
                    error_log("User Auto Delete Prepare Error: " . mysqli_error($con));
                }
            } else {
                // L'auto non appartiene all'utente o non esiste
                mysqli_stmt_close($stmt_check_owner);
                $messaggio = "Non puoi cancellare questa auto.";
                $messaggio_tipo = 'danger';
            }
        } else {
             $messaggio = "Errore nella verifica proprietà auto.";
             $messaggio_tipo = 'danger';
             error_log("User Auto Delete Check Owner Prepare Error: " . mysqli_error($con));
        }

        // Ricarica la pagina senza il parametro GET
        header("Location: gestisci-auto.php?msg=" . urlencode($messaggio) . "&msg_type=" . $messaggio_tipo);
        exit();
    }
}

// Recupera messaggio dalla URL se presente (dopo redirect)
if (isset($_GET['msg']) && empty($messaggio)) {
    $messaggio = $_GET['msg'];
    $messaggio_tipo = $_GET['msg_type'] ?? 'info';
}

// --- Recupero Auto del Socio Loggato ---
if ($id_socio_utente !== null) { // Solo se l'utente è un socio
    $sql_mie_auto = "SELECT
                         id, marca, modello, targa, anno_immatricolazione,
                         has_certificazione_asi, targa_oro, data_inserimento
                     FROM
                         auto
                     WHERE
                         id_socio = ?
                     ORDER BY
                         marca, modello";

    $stmt_auto = mysqli_prepare($con, $sql_mie_auto);
    if ($stmt_auto) {
        mysqli_stmt_bind_param($stmt_auto, "i", $id_socio_utente);
        mysqli_stmt_execute($stmt_auto);
        $result_auto = mysqli_stmt_get_result($stmt_auto);
        while ($row = mysqli_fetch_assoc($result_auto)) {
            $mie_auto[] = $row;
        }
        mysqli_stmt_close($stmt_auto);
    } else {
        $messaggio .= "<br>Errore nel recupero delle tue auto: " . mysqli_error($con);
        if($messaggio_tipo != 'danger') $messaggio_tipo = 'warning';
        error_log("User Fetch Auto Error: " . mysqli_error($con));
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
        <meta name="description" content="Gestione Auto Personali" />
        <meta name="author" content="" />
        <title>Le Tue Auto | Sistema Utente</title>
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@latest/dist/style.css" rel="stylesheet" />
        <link href="css/styles.css" rel="stylesheet" />
        <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/js/all.min.js" crossorigin="anonymous"></script>
        <style>
            #datatablesMyAuto td,
            #datatablesMyAuto th {
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
                        <h1 class="mt-4">Le Tue Auto</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="welcome.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Le Tue Auto</li>
                        </ol>

                        <?php if (!empty($messaggio)): ?>
                            <div class="alert alert-<?php echo htmlspecialchars($messaggio_tipo); ?> alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($messaggio); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <?php if ($id_socio_utente === null && empty($messaggio)): // Mostra solo se non ci sono già errori ?>
                            <div class="alert alert-info">
                                Devi essere registrato come socio per poter gestire le tue auto. Contatta l'amministrazione se ritieni ci sia un errore.
                            </div>
                        <?php else: // L'utente è un socio (o c'è stato un errore nel recupero) ?>
                            <div class="card mb-4">
                                 <div class="card-header">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span><i class="fas fa-car me-1"></i> Elenco Auto Registrate</span>
                                        <a href="add-mia-auto.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Aggiungi Auto</a>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table id="datatablesMyAuto"> <!-- ID univoco per datatables -->
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
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
                                                <?php if (!empty($mie_auto)): ?>
                                                    <?php foreach ($mie_auto as $auto): ?>
                                                    <tr>
                                                        <td><?php echo $auto['id']; ?></td>
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
                                                            <a href="edit-mia-auto.php?id=<?php echo $auto['id']; ?>"
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
                                                <?php elseif($id_socio_utente !== null): // Mostra solo se l'utente è socio ma non ha auto ?>
                                                    <tr><td colspan="9" class="text-center">Non hai ancora registrato nessuna auto.</td></tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
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
        <script src="https://cdn.jsdelivr.net/npm/simple-datatables@latest" crossorigin="anonymous"></script>
        <!-- Inizializza DataTables -->
        <script>
            window.addEventListener('DOMContentLoaded', event => {
                const datatablesMyAuto = document.getElementById('datatablesMyAuto');
                if (datatablesMyAuto) {
                    new simpleDatatables.DataTable(datatablesMyAuto);
                }
            });
        </script>
    </body>
</html>
