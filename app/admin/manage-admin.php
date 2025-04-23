<?php
session_start();
include_once('../includes/config.php');

// Check if admin is logged in
if (strlen($_SESSION['adminid'] ?? 0) == 0) { // Use null coalescing operator for safety
    header('location:logout.php');
    exit(); // Add exit after header redirect
} else {
    $current_admin_id = $_SESSION['adminid']; // Store the current admin's ID

    // Code for deleting an admin
    if (isset($_GET['delid'])) { // Use a different parameter name like 'delid' to avoid confusion
        $adminid_to_delete = intval($_GET['delid']); // Sanitize the input ID

        // Prevent admin from deleting themselves
        if ($adminid_to_delete == $current_admin_id) {
            echo "<script>alert('Errore: Non puoi eliminare il tuo account.');</script>";
            // Corrected redirect filename to match assumed filename
            echo "<script>window.location.href='manage-admins.php'</script>";
        } else {
            // Use prepared statement for deletion
            $stmt_delete = mysqli_prepare($con, "DELETE FROM admin WHERE id = ?");
            if ($stmt_delete) {
                mysqli_stmt_bind_param($stmt_delete, "i", $adminid_to_delete);
                $success = mysqli_stmt_execute($stmt_delete);
                mysqli_stmt_close($stmt_delete);

                if ($success) {
                    echo "<script>alert('Amministratore eliminato con successo.');</script>";
                     // Corrected redirect filename
                    echo "<script>window.location.href='manage-admins.php'</script>";
                } else {
                    echo "<script>alert('Errore durante l'eliminazione dell'amministratore.');</script>";
                    // Optional: Log the error mysqli_error($con)
                    error_log("Admin Deletion Error: " . mysqli_error($con)); // Example logging
                }
            } else {
                 echo "<script>alert('Errore nella preparazione della query di eliminazione.');</script>";
                 // Optional: Log the error mysqli_error($con)
                 error_log("Admin Delete Prepare Error: " . mysqli_error($con)); // Example logging
            }
        }
    }
?>
<!DOCTYPE html>
<html lang="it">
    <head>
        <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="description" content="Gestione Amministratori" />
        <meta name="author" content="" />
        <title>Gestisci Amministratori | Sistema Admin</title>
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
                        <h1 class="mt-4">Gestisci Amministratori</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Gestisci Amministratori</li>
                        </ol>

                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-users-cog me-1"></i>
                                Dettagli Amministratori Registrati
                            </div>
                            <div class="card-body">
                                <table id="datatablesSimple">
                                    <thead>
                                        <tr>
                                             <th>#</th>
                                             <th>Username</th>
                                             <th>Azione</th> 
                                        </tr>
                                    </thead>
                                    <tfoot>
                                        <tr>
                                             <th>#</th>
                                             <th>Username</th>
                                             <th>Azione</th>
                                        </tr>
                                    </tfoot>
                                    <tbody>
                                        <?php
                                        // Fetch admin data (id and username only)
                                        // No need for prepared statement here unless table is huge, but good practice
                                        $stmt_select = mysqli_prepare($con, "SELECT id, username FROM admin");
                                        if ($stmt_select) {
                                            mysqli_stmt_execute($stmt_select);
                                            $result = mysqli_stmt_get_result($stmt_select);
                                            $cnt = 1;
                                            while ($row = mysqli_fetch_assoc($result)) {
                                        ?>
                                        <tr>
                                            <td><?php echo $cnt; ?></td>
                                            <td><?php echo htmlspecialchars($row['username']); ?></td>
                                            <td>
                                                <?php
                                                // Hide actions for the currently logged-in admin
                                                if ($row['id'] != $current_admin_id) {
                                                ?>
                            
                                                    <a href="edit-admin.php?id=<?php echo $row['id']; ?>" title="Modifica">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    &nbsp;
                                                    
                                                    <a href="manage-admins.php?delid=<?php echo $row['id']; ?>"
                                                       onClick="return confirm('Sei sicuro di voler eliminare questo amministratore?');"
                                                       title="Elimina">
                                                        <i class="fa fa-trash" aria-hidden="true"></i>
                                                    </a>
                                                <?php } else {
                                                    echo "<i>(Account Corrente)</i>"; // Indicate current admin
                                                    // Optionally add a link to edit own profile
                                                    // <a href="admin-profile.php" title="Modifica Profilo"><i class="fas fa-user-edit"></i></a>
                                                } ?>
                                            </td>
                                        </tr>
                                        <?php
                                                $cnt++; // Increment counter
                                            }
                                            mysqli_stmt_close($stmt_select);
                                        } else {
                                            // Adjusted colspan for the error message
                                            echo "<tr><td colspan='3'>Errore nel recuperare i dati degli amministratori.</td></tr>";
                                            error_log("Admin Select Prepare Error: " . mysqli_error($con)); // Example logging
                                        }
                                        ?>
                                    </tbody>
                                </table>
                                 
                                 <div class="mt-3">
                                     <a href="add-admin.php" class="btn btn-primary"><i class="fas fa-user-plus"></i> Aggiungi Nuovo Amministratore</a>
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
        <script src="../js/datatables-simple-demo.js"></script>
    </body>
</html>
<?php } ?>
