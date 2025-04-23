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

// --- Gestione Rimozione Socio (GET) ---
if (isset($_GET['del_id'])) {
    $socio_id_to_delete = filter_input(INPUT_GET, 'del_id', FILTER_VALIDATE_INT);
    if ($socio_id_to_delete) {
        // Nota: Cancellare un socio imposterà a NULL id_socio in users e auto
        // a causa del vincolo FOREIGN KEY `ON DELETE SET NULL`.
        // Considera se vuoi prima cancellare le auto associate o gestire diversamente.

        // Prima cancella le auto associate (opzionale, ma più pulito)
        // $stmt_delete_auto = mysqli_prepare($con, "DELETE FROM auto WHERE id_socio = ?");
        // mysqli_stmt_bind_param($stmt_delete_auto, "i", $socio_id_to_delete);
        // mysqli_stmt_execute($stmt_delete_auto);
        // mysqli_stmt_close($stmt_delete_auto);

        // Poi cancella il socio
        $stmt_delete = mysqli_prepare($con, "DELETE FROM soci WHERE id = ?");
        if ($stmt_delete) {
            mysqli_stmt_bind_param($stmt_delete, "i", $socio_id_to_delete);
            if (mysqli_stmt_execute($stmt_delete)) {
                $messaggio = "Socio (ID: " . $socio_id_to_delete . ") rimosso con successo.";
                $messaggio_tipo = 'success';
                // Nota: l'associazione con l'utente viene rimossa automaticamente dal DB (SET NULL)
            } else {
                $messaggio = "Errore durante la rimozione del socio.";
                $messaggio_tipo = 'danger';
                error_log("Admin Socio Deletion Error (ID: $socio_id_to_delete): " . mysqli_stmt_error($stmt_delete));
            }
            mysqli_stmt_close($stmt_delete);
        } else {
            $messaggio = "Errore nella preparazione della query di rimozione socio.";
            $messaggio_tipo = 'danger';
            error_log("Admin Socio Delete Prepare Error: " . mysqli_error($con));
        }
        // Ricarica la pagina senza il parametro GET
        header("Location: gestisci-soci.php?msg=" . urlencode($messaggio) . "&msg_type=" . $messaggio_tipo);
        exit();
    }
}

// Recupera messaggio dalla URL se presente (dopo redirect)
if (isset($_GET['msg'])) {
    $messaggio = $_GET['msg'];
    $messaggio_tipo = $_GET['msg_type'] ?? 'info';
}

// --- Recupero Tutti i Soci con Info Utente Associato ---
$all_soci = [];
$sql_all_soci = "SELECT
                     s.id,
                     s.codice_fiscale,
                     s.nome,
                     s.cognome,
                     s.tessera_club_numero,
                     s.tessera_club_scadenza,
                     s.has_tessera_asi,
                     s.tessera_asi_numero,
                     s.data_iscrizione_club,
                     u.id AS id_utente_associato,
                     u.email AS email_utente_associato
                 FROM
                     soci s
                 LEFT JOIN
                     users u ON s.id = u.id_socio -- LEFT JOIN per includere soci non associati
                 ORDER BY
                     s.cognome, s.nome"; // Ordina per cognome, nome

$result_all = mysqli_query($con, $sql_all_soci);
if ($result_all) {
    while ($row = mysqli_fetch_assoc($result_all)) {
        $all_soci[] = $row;
    }
    mysqli_free_result($result_all);
} else {
    $messaggio .= "<br>Errore nel recupero dei soci: " . mysqli_error($con);
    if($messaggio_tipo != 'danger') $messaggio_tipo = 'warning';
    error_log("Fetch All Soci Error: " . mysqli_error($con));
}

mysqli_close($con);
?>
<!DOCTYPE html>
<html lang="it">
    <head>
        <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="description" content="Gestione Soci Club" />
        <meta name="author" content="" />
        <title>Gestisci Soci | Sistema Admin</title>
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@latest/dist/style.css" rel="stylesheet" />
        <link href="../css/styles.css" rel="stylesheet" />
        <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/js/all.min.js" crossorigin="anonymous"></script>
        <style>
            #datatablesSoci td,
            #datatablesSoci th {
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
                        <h1 class="mt-4">Gestisci Soci</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Gestisci Soci</li>
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
                                    <span><i class="fas fa-users me-1"></i> Elenco Soci Registrati</span>
                                    <a href="add-socio.php" class="btn btn-primary btn-sm"><i class="fas fa-user-plus"></i> Aggiungi Socio</a>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="datatablesSoci"> <!-- ID univoco per datatables -->
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Cognome</th>
                                                <th>Nome</th>
                                                <th>Cod. Fiscale</th>
                                                <th>Tess. Club</th>
                                                <th>Scad. Club</th>
                                                <th>Tess. ASI</th>
                                                <th>Num. ASI</th>
                                                <th>Iscr. Club</th>
                                                <th>Utente Associato</th>
                                                <th>Azione</th>
                                            </tr>
                                        </thead>
                                        <tfoot>
                                            <tr>
                                                <th>ID</th>
                                                <th>Cognome</th>
                                                <th>Nome</th>
                                                <th>Cod. Fiscale</th>
                                                <th>Tess. Club</th>
                                                <th>Scad. Club</th>
                                                <th>Tess. ASI</th>
                                                <th>Num. ASI</th>
                                                <th>Iscr. Club</th>
                                                <th>Utente Associato</th>
                                                <th>Azione</th>
                                            </tr>
                                        </tfoot>
                                        <tbody>
                                            <?php if (!empty($all_soci)): ?>
                                                <?php foreach ($all_soci as $socio): ?>
                                                <tr>
                                                    <td><?php echo $socio['id']; ?></td>
                                                    <td><?php echo htmlspecialchars($socio['cognome']); ?></td>
                                                    <td><?php echo htmlspecialchars($socio['nome']); ?></td>
                                                    <td><?php echo htmlspecialchars($socio['codice_fiscale']); ?></td>
                                                    <td><?php echo htmlspecialchars($socio['tessera_club_numero'] ?: '-'); ?></td>
                                                    <td><?php echo $socio['tessera_club_scadenza'] ? htmlspecialchars(date("d/m/Y", strtotime($socio['tessera_club_scadenza']))) : '-'; ?></td>
                                                    <td>
                                                        <?php if ($socio['has_tessera_asi'] == 1): ?>
                                                            <span class="badge bg-success">Sì</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">No</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($socio['tessera_asi_numero'] ?: '-'); ?></td>
                                                    <td><?php echo htmlspecialchars(date("d/m/Y", strtotime($socio['data_iscrizione_club']))); ?></td>
                                                    <td>
                                                        <?php if ($socio['id_utente_associato']): ?>
                                                            <a href="user-profile.php?uid=<?php echo $socio['id_utente_associato']; ?>" title="Vai al profilo utente">
                                                                <?php echo htmlspecialchars($socio['email_utente_associato']); ?>
                                                            </a>
                                                        <?php else: ?>
                                                            <span class="text-muted">Non associato</span>
                                                            <?php // Potresti aggiungere qui un link per associare ?>
                                                            <!-- <a href="associate-socio-user.php?socio_id=<?php echo $socio['id']; ?>" class="btn btn-link btn-sm p-0">Associa</a> -->
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <a href="edit-socio.php?id=<?php echo $socio['id']; ?>"
                                                           title="Modifica Socio" class="btn btn-primary btn-sm me-1">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="gestisci-soci.php?del_id=<?php echo $socio['id']; ?>"
                                                           onClick="return confirm('Sei sicuro di voler cancellare questo socio (ID: <?php echo $socio['id']; ?>)? Verrà dissociato da eventuali utenti e le sue auto verranno rimosse dal sistema.');"
                                                           title="Cancella Socio" class="btn btn-danger btn-sm">
                                                            <i class="fa fa-trash" aria-hidden="true"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr><td colspan="11" class="text-center">Nessun socio trovato.</td></tr>
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
                const datatablesSoci = document.getElementById('datatablesSoci');
                if (datatablesSoci) {
                    new simpleDatatables.DataTable(datatablesSoci);
                }
            });
        </script>
    </body>
</html>
