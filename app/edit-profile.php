<?php session_start();
include_once('includes/config.php');

if (strlen($_SESSION['id'] ?? 0) == 0) {
  header('location:logout.php');
  exit();
} else {

    $userid = $_SESSION['id'];
    $error_message = '';
    $success_message = '';

    if(isset($_POST['update']))
    {
        $fname = trim($_POST['fname']);
        $lname = trim($_POST['lname']);
        $contact = trim($_POST['contact']);

        if (empty($fname) || empty($lname) || empty($contact)) {
             $error_message = "Tutti i campi (Nome, Cognome, Contatto) sono obbligatori.";
        } elseif (!preg_match('/^[0-9]{10}$/', $contact)) {
             $error_message = "Il numero di contatto deve contenere esattamente 10 cifre numeriche.";
        } else {
            $stmt_update = mysqli_prepare($con, "UPDATE users SET fname = ?, lname = ?, contactno = ? WHERE id = ?");
            if ($stmt_update) {
                mysqli_stmt_bind_param($stmt_update, "sssi", $fname, $lname, $contact, $userid);
                if(mysqli_stmt_execute($stmt_update))
                {
                    echo "<script>alert('Profilo aggiornato con successo');</script>";
                    echo "<script type='text/javascript'> document.location = 'profile.php'; </script>";
                    exit();
                } else {
                    $error_message = "Errore durante l'aggiornamento del profilo.";
                    error_log("Profile Update Error: " . mysqli_stmt_error($stmt_update));
                }
                mysqli_stmt_close($stmt_update);
            } else {
                 $error_message = "Errore nella preparazione della query di aggiornamento.";
                 error_log("Profile Update Prepare Error: " . mysqli_error($con));
            }
        }
    }

    $user_data = null;
    $stmt_fetch = mysqli_prepare($con, "SELECT fname, lname, email, contactno, posting_date FROM users WHERE id = ?");
    if ($stmt_fetch) {
        mysqli_stmt_bind_param($stmt_fetch, "i", $userid);
        mysqli_stmt_execute($stmt_fetch);
        $result_fetch = mysqli_stmt_get_result($stmt_fetch);
        $user_data = mysqli_fetch_assoc($result_fetch);
        mysqli_stmt_close($stmt_fetch);
    } else {
        error_log("Profile Fetch Prepare Error: " . mysqli_error($con));
        $error_message = "Impossibile caricare i dati del profilo.";
    }

?>
<!DOCTYPE html>
<html lang="it">
    <head>
        <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="description" content="Modifica Profilo Utente" />
        <meta name="author" content="" />
        <title>Modifica Profilo | Sistema Registrazione e Login</title>
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

                    <?php if ($user_data): ?>
                        <h1 class="mt-4">Modifica Profilo di <?php echo htmlspecialchars($user_data['fname']);?></h1>

                        <?php if (!empty($error_message)): ?>
                            <div class="alert alert-danger" role="alert">
                                <?php echo htmlspecialchars($error_message); ?>
                            </div>
                        <?php endif; ?>

                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-user-edit me-1"></i>
                                Aggiorna i tuoi dettagli
                            </div>
                            <form method="post">
                                <div class="card-body">
                                    <table class="table table-bordered">
                                       <tr>
                                        <th>Nome</th>
                                           <td><input class="form-control" id="fname" name="fname" type="text" value="<?php echo htmlspecialchars(isset($_POST['fname']) ? $_POST['fname'] : $user_data['fname']);?>" required /></td>
                                       </tr>
                                       <tr>
                                           <th>Cognome</th>
                                           <td><input class="form-control" id="lname" name="lname" type="text" value="<?php echo htmlspecialchars(isset($_POST['lname']) ? $_POST['lname'] : $user_data['lname']);?>" required /></td>
                                       </tr>
                                             <tr>
                                           <th>Contatto</th>
                                           <td colspan="3"><input class="form-control" id="contact" name="contact" type="text" value="<?php echo htmlspecialchars(isset($_POST['contact']) ? $_POST['contact'] : $user_data['contactno']);?>" pattern="[0-9]{10}" title="Solo 10 caratteri numerici" maxlength="10" required /></td>
                                       </tr>
                                       <tr>
                                           <th>Email</th>
                                           <td colspan="3"><?php echo htmlspecialchars($user_data['email']);?> <small>(Non modificabile)</small></td>
                                       </tr>
                                            <tr>
                                           <th>Data Registrazione</th>
                                           <td colspan="3"><?php echo htmlspecialchars($user_data['posting_date']);?></td>
                                       </tr>
                                       <tr>
                                           <td colspan="4" style="text-align:center ;"><button type="submit" class="btn btn-primary btn-block" name="update">Aggiorna</button></td>
                                       </tr>
                                    </tbody>
                                    </table>
                                </div>
                            </form>
                        </div>
                    <?php else: ?>
                         <h1 class="mt-4">Errore</h1>
                         <div class="alert alert-danger">
                            <?php echo htmlspecialchars($error_message ?: 'Impossibile trovare i dati del profilo utente.'); ?>
                         </div>
                    <?php endif; ?>

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
