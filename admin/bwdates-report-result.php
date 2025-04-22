<?php session_start();
include_once('../includes/config.php');

if (strlen($_SESSION['adminid'] ?? 0) == 0) {
  header('location:logout.php');
  exit();
} else {

    if(isset($_GET['id']))
    {
        $userid_to_delete = intval($_GET['id']);

        $stmt_delete = mysqli_prepare($con, "DELETE FROM users WHERE id = ?");
        if ($stmt_delete) {
            mysqli_stmt_bind_param($stmt_delete, "i", $userid_to_delete);
            $success = mysqli_stmt_execute($stmt_delete);
            mysqli_stmt_close($stmt_delete);

            if($success)
            {
                echo "<script>alert('Utente eliminato con successo.');</script>";

                // Ricarica la pagina corrente dopo l'eliminazione per aggiornare la vista
                // Potrebbe essere necessario passare nuovamente le date se si vuole mantenere lo stesso report
                // Per semplicità, reindirizziamo a manage-users.php
                echo "<script>window.location.href='manage-users.php'</script>";
                exit();
            } else {
                 echo "<script>alert('Errore durante l'eliminazione dell'utente.');</script>";
                 error_log("User Deletion Error (Report Page): " . mysqli_error($con));
            }
        } else {
            echo "<script>alert('Errore nella preparazione della query di eliminazione.');</script>";
            error_log("User Delete Prepare Error (Report Page): " . mysqli_error($con));
        }
    }

    $fdate = $_POST['fromdate'] ?? null;
    $tdate = $_POST['todate'] ?? null;

    // Validazione base delle date
    if (!$fdate || !$tdate || strtotime($fdate) > strtotime($tdate)) {
         // Reindirizza alla pagina di selezione se le date non sono valide o mancanti
         header('location: bwdates-report-ds.php?error=invalid_dates');
         exit();
    }

?>
<!DOCTYPE html>
<html lang="it">
    <head>
        <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="description" content="Risultati Report per Data" />
        <meta name="author" content="" />
        <title>Risultati Report per Data | Sistema Registrazione e Login</title>
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@latest/dist/style.css" rel="stylesheet" />
        <link href="../css/styles.css" rel="stylesheet" />
        <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/js/all.min.js" crossorigin="anonymous"></script>

    </head>
    <body class="sb-nav-fixed">
      <?php include_once('includes/navbar.php');?>
        <div id="layoutSidenav">
         <?php include_once('includes/sidebar.php');?>
            <div id="layoutSidenav_content">
                <main>
                    <div class="container-fluid px-4">
                        <h1 class="mt-4">Risultati Report per Data</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="bwdates-report-ds.php">Selezione Date Report</a></li>
                            <li class="breadcrumb-item active">Risultati Report per Data</li>
                        </ol>

                        <div class="card mb-4">
                            <div class="card-header" align="center" style="font-size:20px;">
                                <i class="fas fa-table me-1"></i>
                                Risultati Report dal <?php echo htmlspecialchars(date("d-m-Y", strtotime($fdate)));?> al <?php echo htmlspecialchars(date("d-m-Y", strtotime($tdate)));?>
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
                                             <th>Azione</th>
                                        </tr>
                                    </tfoot>
                                    <tbody>
                                        <?php
                                        $stmt_select = mysqli_prepare($con, "SELECT id, fname, lname, email, contactno, posting_date FROM users WHERE DATE(posting_date) BETWEEN ? AND ?");
                                        if ($stmt_select) {
                                            mysqli_stmt_bind_param($stmt_select, "ss", $fdate, $tdate);
                                            mysqli_stmt_execute($stmt_select);
                                            $result = mysqli_stmt_get_result($stmt_select);
                                            $cnt = 1;
                                            if(mysqli_num_rows($result) > 0) {
                                                while($row = mysqli_fetch_assoc($result))
                                                {
                                        ?>
                                        <tr>
                                            <td><?php echo $cnt;?></td>
                                            <td><?php echo htmlspecialchars($row['fname']);?></td>
                                            <td><?php echo htmlspecialchars($row['lname']);?></td>
                                            <td><?php echo htmlspecialchars($row['email']);?></td>
                                            <td><?php echo htmlspecialchars($row['contactno']);?></td>
                                            <td><?php echo htmlspecialchars($row['posting_date']);?></td>
                                            <td>
                                                <a href="user-profile.php?uid=<?php echo $row['id'];?>" title="Visualizza/Modifica Profilo">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                &nbsp;
                                                <a href="bwdates-report-result.php?id=<?php echo $row['id'];?>"
                                                   onClick="return confirm('Sei sicuro di voler eliminare questo utente?');" title="Elimina Utente">
                                                    <i class="fa fa-trash" aria-hidden="true"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php
                                                    $cnt=$cnt+1;
                                                }
                                            } else {
                                                echo "<tr><td colspan='7' class='text-center'>Nessun utente trovato per le date selezionate.</td></tr>";
                                            }
                                            mysqli_stmt_close($stmt_select);
                                        } else {
                                            echo "<tr><td colspan='7'>Errore nella preparazione della query di selezione.</td></tr>";
                                            error_log("User Report Select Prepare Error: " . mysqli_error($con));
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
