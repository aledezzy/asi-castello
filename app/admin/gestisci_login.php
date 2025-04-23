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

// --- Gestione Rimozione Tentativo di Login (GET) ---
if (isset($_GET['del_id'])) {
    $attempt_id_to_delete = filter_input(INPUT_GET, 'del_id', FILTER_VALIDATE_INT);
    if ($attempt_id_to_delete) {
        $stmt_delete = mysqli_prepare($con, "DELETE FROM login_attempts WHERE id = ?");
        if ($stmt_delete) {
            mysqli_stmt_bind_param($stmt_delete, "i", $attempt_id_to_delete);
            if (mysqli_stmt_execute($stmt_delete)) {
                $messaggio = "Tentativo di login (ID: " . $attempt_id_to_delete . ") rimosso con successo.";
                $messaggio_tipo = 'success';
            } else {
                $messaggio = "Errore durante la rimozione del tentativo di login.";
                $messaggio_tipo = 'danger';
                error_log("Admin Login Attempt Deletion Error (ID: $attempt_id_to_delete): " . mysqli_stmt_error($stmt_delete));
            }
            mysqli_stmt_close($stmt_delete);
        } else {
            $messaggio = "Errore nella preparazione della query di rimozione.";
            $messaggio_tipo = 'danger';
            error_log("Admin Login Attempt Delete Prepare Error: " . mysqli_error($con));
        }
        // Ricarica la pagina senza il parametro GET
        header("Location: gestisci_login.php?msg=" . urlencode($messaggio) . "&msg_type=" . $messaggio_tipo);
        exit();
    }
}

// Recupera messaggio dalla URL se presente (dopo redirect)
if (isset($_GET['msg'])) {
    $messaggio = $_GET['msg'];
    $messaggio_tipo = $_GET['msg_type'] ?? 'info';
}

// --- Recupero Tutti i Tentativi di Login ---
$login_attempts = [];
// Seleziona i dati e fai un LEFT JOIN con users per ottenere il nome utente se disponibile
$sql_attempts = "SELECT
                     la.id,
                     la.user_id,
                     la.email_attempted,
                     la.ip_address,
                     la.attempt_timestamp,
                     la.success,
                     u.fname,
                     u.lname
                 FROM
                     login_attempts la
                 LEFT JOIN
                     users u ON la.user_id = u.id
                 ORDER BY
                     la.attempt_timestamp DESC"; // Ordina per più recente

$result_attempts = mysqli_query($con, $sql_attempts);
if ($result_attempts) {
    while ($row = mysqli_fetch_assoc($result_attempts)) {
        $login_attempts[] = $row;
    }
    mysqli_free_result($result_attempts);
} else {
    $messaggio .= "<br>Errore nel recupero dei tentativi di login: " . mysqli_error($con);
    if($messaggio_tipo != 'danger') $messaggio_tipo = 'warning';
    error_log("Fetch Login Attempts Error: " . mysqli_error($con));
}

mysqli_close($con);
?>
<!DOCTYPE html>
<html lang="it">
    <head>
        <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="description" content="Gestione Tentativi di Login" />
        <meta name="author" content="" />
        <title>Gestisci Tentativi Login | Sistema Admin</title>
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@latest/dist/style.css" rel="stylesheet" />
        <link href="../css/styles.css" rel="stylesheet" />
        <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/js/all.min.js" crossorigin="anonymous"></script>
         <style>
            #datatablesLoginAttempts td,
            #datatablesLoginAttempts th {
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
                        <h1 class="mt-4">Gestisci Tentativi di Login</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Tentativi Login</li>
                        </ol>

                        <?php if (!empty($messaggio)): ?>
                            <div class="alert alert-<?php echo htmlspecialchars($messaggio_tipo); ?> alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($messaggio); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-history me-1"></i>
                                Storico Tentativi di Login
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="datatablesLoginAttempts"> <!-- ID univoco per datatables -->
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Utente</th>
                                                <th>Email Tentata</th>
                                                <th>Indirizzo IP</th>
                                                <th>Data/Ora</th>
                                                <th>Esito</th>
                                                <th>Azione</th>
                                            </tr>
                                        </thead>
                                        <tfoot>
                                            <tr>
                                                <th>ID</th>
                                                <th>Utente</th>
                                                <th>Email Tentata</th>
                                                <th>Indirizzo IP</th>
                                                <th>Data/Ora</th>
                                                <th>Esito</th>
                                                <th>Azione</th>
                                            </tr>
                                        </tfoot>
                                        <tbody>
                                            <?php if (!empty($login_attempts)): ?>
                                                <?php foreach ($login_attempts as $attempt): ?>
                                                <tr>
                                                    <td><?php echo $attempt['id']; ?></td>
                                                    <td>
                                                        <?php
                                                        if ($attempt['user_id']) {
                                                            echo htmlspecialchars($attempt['fname'] . ' ' . $attempt['lname']);
                                                            echo ' <small>(ID: ' . $attempt['user_id'] . ')</small>';
                                                        } else {
                                                            echo '<span class="text-muted">N/A</span>';
                                                        }
                                                        ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($attempt['email_attempted'] ?: '-'); ?></td>
                                                    <td><?php echo htmlspecialchars($attempt['ip_address']); ?></td>
                                                    <td><?php echo htmlspecialchars(date("d/m/Y H:i:s", strtotime($attempt['attempt_timestamp']))); ?></td>
                                                    <td>
                                                        <?php if ($attempt['success'] == 1): ?>
                                                            <span class="badge bg-success">Successo</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger">Fallito</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <a href="gestisci_login.php?del_id=<?php echo $attempt['id']; ?>"
                                                           onClick="return confirm('Sei sicuro di voler cancellare questo tentativo di login (ID: <?php echo $attempt['id']; ?>)?');"
                                                           title="Cancella Tentativo" class="btn btn-danger btn-sm">
                                                            <i class="fa fa-trash" aria-hidden="true"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr><td colspan="7" class="text-center">Nessun tentativo di login registrato.</td></tr>
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
                const datatablesLoginAttempts = document.getElementById('datatablesLoginAttempts');
                if (datatablesLoginAttempts) {
                    new simpleDatatables.DataTable(datatablesLoginAttempts);
                }
            });
        </script>
    </body>
</html>
