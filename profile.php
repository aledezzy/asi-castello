<?php session_start();
include_once('includes/config.php');

if (strlen($_SESSION['id'] ?? 0) == 0) {
  header('location:logout.php');
  exit();
} else {

?>
<!DOCTYPE html>
<html lang="it">
    <head>
        <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="description" content="Profilo Utente" />
        <meta name="author" content="" />
        <title>Profilo | Sistema Registrazione e Login</title>
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@latest/dist/style.css" rel="stylesheet" />
        <link href="css/styles.css" rel="stylesheet" />
        <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/js/all.min.js" crossorigin="anonymous"></script>
    </head>
    <body class="sb-nav-fixed">
      <?php include_once('includes/navbar.php');?>
        <div id="layoutSidenav">
          <?php include_once('includes/sidebar.php');?>
            <div id="layoutSidenav_content">
                <main>
                    <div class="container-fluid px-4">

<?php
$userid = $_SESSION['id'];
$user_data = null; // Inizializza la variabile

$stmt = mysqli_prepare($con, "SELECT fname, lname, email, contactno, posting_date FROM users WHERE id = ?");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $userid);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user_data = mysqli_fetch_assoc($result); // Ottieni i dati dell'utente
    mysqli_stmt_close($stmt);
} else {
    // Gestisci l'errore se la query non può essere preparata
    error_log("Profile Prepare Error: " . mysqli_error($con));
    // Potresti mostrare un messaggio di errore all'utente
}

if ($user_data) { // Controlla se i dati sono stati recuperati con successo
?>
                        <h1 class="mt-4">Profilo di <?php echo htmlspecialchars($user_data['fname']);?></h1>
                        <div class="card mb-4">

                            <div class="card-body">
                                <a href="edit-profile.php" class="btn btn-primary btn-sm mb-3">Modifica Profilo</a>
                                <table class="table table-bordered">
                                   <tr>
                                    <th>Nome</th>
                                       <td><?php echo htmlspecialchars($user_data['fname']);?></td>
                                   </tr>
                                   <tr>
                                       <th>Cognome</th>
                                       <td><?php echo htmlspecialchars($user_data['lname']);?></td>
                                   </tr>
                                   <tr>
                                       <th>Email</th>
                                       <td colspan="3"><?php echo htmlspecialchars($user_data['email']);?></td>
                                   </tr>
                                     <tr>
                                       <th>Contatto</th>
                                       <td colspan="3"><?php echo htmlspecialchars($user_data['contactno']);?></td>
                                   </tr>

                                        <tr>
                                       <th>Data Registrazione</th>
                                       <td colspan="3"><?php echo htmlspecialchars($user_data['posting_date']);?></td>
                                   </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
<?php
} else {
    // Mostra un messaggio se l'utente non è stato trovato (improbabile se loggato)
    echo '<div class="alert alert-warning">Impossibile caricare i dati del profilo.</div>';
}
?>

                    </div>
                </main>
          <?php include('includes/footer.php');?>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
        <script src="js/scripts.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.8.0/Chart.min.js" crossorigin="anonymous"></script>
        <script src="assets/demo/chart-area-demo.js"></script>
        <script src="assets/demo/chart-bar-demo.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/simple-datatables@latest" crossorigin="anonymous"></script>
        <script src="js/datatables-simple-demo.js"></script>
    </body>
</html>
<?php } ?>
