<?php session_start();
include_once('../includes/config.php');

// Correzione: Verifica corretta della sessione adminid
if (strlen($_SESSION['adminid'] ?? 0) == 0) {
  header('location:logout.php');
  exit(); // Aggiungi exit dopo il redirect
} else {

    $adminid = $_SESSION['adminid']; // Ottieni l'ID dell'admin dalla sessione
    $error_message = '';
    $success_message = '';

    // Codice per il cambio password
    if(isset($_POST['update']))
    {
        $currentpassword_form = $_POST['currentpassword'];
        $newpassword = $_POST['newpassword'];
        $confirmpassword = $_POST['confirmpassword'];

        // Validazione base
        if (empty($currentpassword_form) || empty($newpassword) || empty($confirmpassword)) {
            $error_message = "Tutti i campi password sono obbligatori.";
        } elseif ($newpassword !== $confirmpassword) {
            $error_message = "La nuova password e la conferma non corrispondono.";
        }
        // Optional: Aggiungi controllo complessità password
        // elseif (!preg_match('/^(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{6,}$/', $newpassword)) {
        //    $error_message = 'La nuova password deve contenere almeno un numero, una lettera maiuscola e minuscola, e avere almeno 6 caratteri.';
        // }
        else {
            // 1. Recupera l'hash della password corrente dal DB usando prepared statement
            $stmt_fetch = mysqli_prepare($con, "SELECT password FROM admin WHERE id = ?");
            if ($stmt_fetch) {
                mysqli_stmt_bind_param($stmt_fetch, "i", $adminid);
                mysqli_stmt_execute($stmt_fetch);
                $result_fetch = mysqli_stmt_get_result($stmt_fetch);
                $admin_data = mysqli_fetch_assoc($result_fetch);
                mysqli_stmt_close($stmt_fetch);

                if ($admin_data) {
                    $current_hashed_password_db = $admin_data['password'];

                    // 2. Verifica la password corrente inserita con quella nel DB
                    if (password_verify($currentpassword_form, $current_hashed_password_db)) {

                        // 3. Crea l'hash della nuova password
                        $new_hashed_password = password_hash($newpassword, PASSWORD_DEFAULT);

                        // 4. Aggiorna la password nel DB usando prepared statement
                        $stmt_update = mysqli_prepare($con, "UPDATE admin SET password = ? WHERE id = ?");
                        if ($stmt_update) {
                            mysqli_stmt_bind_param($stmt_update, "si", $new_hashed_password, $adminid);
                            if (mysqli_stmt_execute($stmt_update)) {
                                $success_message = "Password modificata con successo!";
                                // Non reindirizzare subito, mostra il messaggio di successo
                                // echo "<script>alert('Password modificata con successo!');</script>";
                                // echo "<script type='text/javascript'> document.location = 'change-password.php'; </script>";
                            } else {
                                $error_message = "Errore durante l'aggiornamento della password.";
                                error_log("Admin Change Password Update Error: " . mysqli_stmt_error($stmt_update));
                            }
                            mysqli_stmt_close($stmt_update);
                        } else {
                            $error_message = "Errore nella preparazione della query di aggiornamento.";
                            error_log("Admin Change Password Prepare Update Error: " . mysqli_error($con));
                        }
                    } else {
                        // La password corrente inserita non corrisponde
                        $error_message = "La password corrente non è corretta.";
                    }
                } else {
                    // Non dovrebbe succedere se l'utente è loggato, ma per sicurezza
                    $error_message = "Errore nel recuperare i dati dell'amministratore.";
                }
            } else {
                 $error_message = "Errore nella preparazione della query di recupero password.";
                 error_log("Admin Change Password Prepare Fetch Error: " . mysqli_error($con));
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
        <meta name="description" content="Cambia Password Admin" />
        <meta name="author" content="" />
        <title>Cambia Password | Sistema Admin</title>
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@latest/dist/style.css" rel="stylesheet" />
        <link href="../css/styles.css" rel="stylesheet" />
        <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/js/all.min.js" crossorigin="anonymous"></script>
        <script type="text/javascript">
            function valid()
            {
                if(document.changepassword.newpassword.value !== document.changepassword.confirmpassword.value)
                {
                    alert("La nuova password e la conferma non corrispondono!");
                    document.changepassword.confirmpassword.focus();
                    return false;
                }
                // Optional: Aggiungi controllo complessità password lato client
                // const passPattern = /^(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{6,}$/;
                // if (!passPattern.test(document.changepassword.newpassword.value)) {
                //    alert('La nuova password deve contenere almeno un numero, una lettera maiuscola e minuscola, e avere almeno 6 caratteri.');
                //    document.changepassword.newpassword.focus();
                //    return false;
                // }
                return true;
            }
        </script>
    </head>
    <body class="sb-nav-fixed">
      <?php include_once('includes/navbar.php');?>
        <div id="layoutSidenav">
          <?php include_once('includes/sidebar.php');?>
            <div id="layoutSidenav_content">
                <main>
                    <div class="container-fluid px-4">
                        <h1 class="mt-4">Cambia Password</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Cambia Password</li>
                        </ol>

                        <?php if (!empty($error_message)): ?>
                            <div class="alert alert-danger" role="alert">
                                <?php echo htmlspecialchars($error_message); ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($success_message)): ?>
                            <div class="alert alert-success" role="alert">
                                <?php echo htmlspecialchars($success_message); ?>
                            </div>
                        <?php endif; ?>

                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-key me-1"></i>
                                Modifica la tua password
                            </div>
                            <form method="post" name="changepassword" onSubmit="return valid();">
                                <div class="card-body">
                                    <table class="table table-bordered">
                                       <tr>
                                        <th>Password Corrente</th>
                                           <td><input class="form-control" id="currentpassword" name="currentpassword" type="password" value="" required /></td>
                                       </tr>
                                       <tr>
                                           <th>Nuova Password</th>
                                           <td><input class="form-control" id="newpassword" name="newpassword" type="password" value="" required /></td>
                                       </tr>
                                       <tr>
                                           <th>Conferma Nuova Password</th>
                                           <td><input class="form-control" id="confirmpassword" name="confirmpassword" type="password" value="" required /></td>
                                       </tr>
                                       <tr>
                                           <td colspan="2" style="text-align:center ;"><button type="submit" class="btn btn-primary btn-block" name="update">Modifica</button></td>
                                       </tr>
                                    </tbody>
                                    </table>
                                </div>
                            </form>
                        </div>
                    </div>
                </main>
                <?php include('../includes/footer.php');?>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
        <script src="../js/scripts.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.8.0/Chart.min.js" crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/simple-datatables@latest" crossorigin="anonymous"></script>
        <script src="../js/datatables-simple-demo.js"></script>
    </body>
</html>
<?php } ?>
