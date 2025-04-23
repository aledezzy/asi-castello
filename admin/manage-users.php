<?php session_start();
include_once('../includes/config.php');

// Verifica se l'admin è loggato
if (strlen($_SESSION['adminid'] ?? 0) == 0) {
  header('location:logout.php');
  exit();
} else {

    $messaggio = ''; // Per messaggi di stato
    $messaggio_tipo = 'info';

    // --- Gestione Eliminazione Utente ---
    if(isset($_GET['id']))
    {
        $userid_to_delete = intval($_GET['id']);
        // Aggiungi controllo per non eliminare se stessi (se necessario, ma qui è admin vs users)

        $stmt_delete = mysqli_prepare($con, "DELETE FROM users WHERE id = ?");
        if ($stmt_delete) {
            mysqli_stmt_bind_param($stmt_delete, "i", $userid_to_delete);
            if (mysqli_stmt_execute($stmt_delete)) {
                $messaggio = "Utente (ID: {$userid_to_delete}) eliminato con successo.";
                $messaggio_tipo = 'success';
            } else {
                 $messaggio = "Errore durante l'eliminazione dell'utente.";
                 $messaggio_tipo = 'danger';
                 error_log("User Deletion Error: " . mysqli_stmt_error($stmt_delete));
            }
            mysqli_stmt_close($stmt_delete);
        } else {
            $messaggio = "Errore nella preparazione della query di eliminazione.";
            $messaggio_tipo = 'danger';
            error_log("User Delete Prepare Error: " . mysqli_error($con));
        }
        // Redirect dopo l'azione
        header("Location: manage-users.php?msg=" . urlencode($messaggio) . "&msg_type=" . $messaggio_tipo);
        exit();
    }

    // --- Gestione Disabilitazione Utente ---
    if(isset($_GET['disable_id']))
    {
        $userid_to_disable = intval($_GET['disable_id']);
        $stmt_disable = mysqli_prepare($con, "UPDATE users SET is_active = 0 WHERE id = ?");
        if ($stmt_disable) {
            mysqli_stmt_bind_param($stmt_disable, "i", $userid_to_disable);
            if (mysqli_stmt_execute($stmt_disable)) {
                 $messaggio = "Utente (ID: {$userid_to_disable}) disabilitato con successo.";
                 $messaggio_tipo = 'success';
            } else {
                 $messaggio = "Errore durante la disabilitazione dell'utente.";
                 $messaggio_tipo = 'danger';
                 error_log("User Disable Error: " . mysqli_stmt_error($stmt_disable));
            }
            mysqli_stmt_close($stmt_disable);
        } else {
            $messaggio = "Errore nella preparazione della query di disabilitazione.";
            $messaggio_tipo = 'danger';
            error_log("User Disable Prepare Error: " . mysqli_error($con));
        }
        // Redirect dopo l'azione
        header("Location: manage-users.php?msg=" . urlencode($messaggio) . "&msg_type=" . $messaggio_tipo);
        exit();
    }

    // --- Gestione Abilitazione Utente ---
     if(isset($_GET['enable_id']))
    {
        $userid_to_enable = intval($_GET['enable_id']);
        $stmt_enable = mysqli_prepare($con, "UPDATE users SET is_active = 1 WHERE id = ?");
         if ($stmt_enable) {
            mysqli_stmt_bind_param($stmt_enable, "i", $userid_to_enable);
            if (mysqli_stmt_execute($stmt_enable)) {
                 $messaggio = "Utente (ID: {$userid_to_enable}) abilitato con successo.";
                 $messaggio_tipo = 'success';
            } else {
                 $messaggio = "Errore durante l'abilitazione dell'utente.";
                 $messaggio_tipo = 'danger';
                 error_log("User Enable Error: " . mysqli_stmt_error($stmt_enable));
            }
            mysqli_stmt_close($stmt_enable);
        } else {
            $messaggio = "Errore nella preparazione della query di abilitazione.";
            $messaggio_tipo = 'danger';
            error_log("User Enable Prepare Error: " . mysqli_error($con));
        }
        // Redirect dopo l'azione
        header("Location: manage-users.php?msg=" . urlencode($messaggio) . "&msg_type=" . $messaggio_tipo);
        exit();
    }

    // Recupera messaggio dalla URL se presente (dopo redirect)
    if (isset($_GET['msg']) && empty($messaggio)) {
        $messaggio = $_GET['msg'];
        $messaggio_tipo = $_GET['msg_type'] ?? 'info';
    }

?>
<!DOCTYPE html>
<html lang="it">
    <head>
        <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="description" content="Gestione Utenti Registrati" />
        <meta name="author" content="" />
        <title>Gestisci Utenti</title>
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@latest/dist/style.css" rel="stylesheet" />
        <link href="../css/styles.css" rel="stylesheet" />
        <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/js/all.min.js" crossorigin="anonymous"></script>
        <style>
             #datatablesSimple td, #datatablesSimple th { vertical-align: middle; }
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
                        <h1 class="mt-4">Gestisci Utenti</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Gestisci Utenti</li>
                        </ol>

                        <?php if (!empty($messaggio)): ?>
                            <div class="alert alert-<?php echo htmlspecialchars($messaggio_tipo); ?> alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($messaggio); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-table me-1"></i>
                                Dettagli Utenti Registrati
                            </div>
                            <div class="card-body">
                                <table id="datatablesSimple">
                                    <thead>
                                        <tr>
                                             <th>#</th>
                                             <th>Nome</th>
                                             <th>Cognome</th>
                                             <th>Email</th>
                                             <th>Contatto</th>
                                             <th>Data Reg.</th>
                                             <th>Stato</th> 
                                             <th>Azione</th>
                                        </tr>
                                    </thead>
                                    <tfoot>
                                        <tr>
                                             <th>#</th>
                                             <th>Nome</th>
                                             <th>Cognome</th>
                                             <th>Email</th>
                                             <th>Contatto</th>
                                             <th>Data Reg.</th>
                                             <th>Stato</th> 
                                             <th>Azione</th>
                                        </tr>
                                    </tfoot>
                                    <tbody>
                                        <?php
                                        // Seleziona anche is_active
                                        $stmt_select = mysqli_prepare($con, "SELECT id, fname, lname, email, contactno, posting_date, is_active FROM users");
                                        if ($stmt_select) {
                                            mysqli_stmt_execute($stmt_select);
                                            $result = mysqli_stmt_get_result($stmt_select);
                                            $cnt = 1;

                                            while($row = mysqli_fetch_assoc($result))
                                            {
                                        ?>
                                        <tr>
                                            <td><?php echo $cnt;?></td>
                                            <td><?php echo htmlspecialchars($row['fname']);?></td>
                                            <td><?php echo htmlspecialchars($row['lname']);?></td>
                                            <td><?php echo htmlspecialchars($row['email']);?></td>
                                            <td><?php echo htmlspecialchars($row['contactno']);?></td>
                                            <td><?php echo htmlspecialchars(date("d/m/Y H:i", strtotime($row['posting_date'])));?></td>
                                            <td> 
                                                <?php if ($row['is_active'] == 1): ?>
                                                    <span class="badge bg-success">Attivo</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Disabilitato</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                
                                                <a href="user-profile.php?uid=<?php echo $row['id'];?>" title="Visualizza/Modifica Profilo" class="me-2">
                                                    <i class="fas fa-edit text-primary"></i>
                                                </a>

                                                
                                                <?php if ($row['is_active'] == 1): ?>
                                                    <a href="manage-users.php?disable_id=<?php echo $row['id'];?>"
                                                       onClick="return confirm('Sei sicuro di voler disabilitare questo utente?');" title="Disabilita Utente" class="me-2">
                                                        <i class="fas fa-user-slash text-warning"></i>
                                                    </a>
                                                <?php else: ?>
                                                     <a href="manage-users.php?enable_id=<?php echo $row['id'];?>"
                                                       onClick="return confirm('Sei sicuro di voler abilitare questo utente?');" title="Abilita Utente" class="me-2">
                                                        <i class="fas fa-user-check text-success"></i>
                                                    </a>
                                                <?php endif; ?>

                                                
                                                <a href="manage-users.php?id=<?php echo $row['id'];?>"
                                                   onClick="return confirm('Sei sicuro di voler eliminare questo utente? ATTENZIONE: Azione irreversibile!');" title="Elimina Utente">
                                                    <i class="fa fa-trash text-danger" aria-hidden="true"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php
                                                $cnt=$cnt+1;
                                            }
                                            mysqli_stmt_close($stmt_select);
                                        } else {
                                            echo "<tr><td colspan='8'>Errore nel recuperare i dati degli utenti.</td></tr>"; // Aggiornato colspan
                                            error_log("User Select Prepare Error: " . mysqli_error($con));
                                        }
                                        ?>
                                    </tbody>
                                </table>
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
        <script src="../js/datatables-simple-demo.js"></script>
    </body>
</html>
<?php } ?>
