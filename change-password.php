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
        $currentpassword_form = $_POST['currentpassword'];
        $newpassword = $_POST['newpassword'];
        $confirmpassword = $_POST['confirmpassword'];

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

            $stmt_fetch = mysqli_prepare($con, "SELECT password FROM users WHERE id = ?");
            if ($stmt_fetch) {
                mysqli_stmt_bind_param($stmt_fetch, "i", $userid);
                mysqli_stmt_execute($stmt_fetch);
                $result_fetch = mysqli_stmt_get_result($stmt_fetch);
                $user_data = mysqli_fetch_assoc($result_fetch);
                mysqli_stmt_close($stmt_fetch);

                if ($user_data) {
                    $current_hashed_password_db = $user_data['password'];

                    if (password_verify($currentpassword_form, $current_hashed_password_db)) {

                        $new_hashed_password = password_hash($newpassword, PASSWORD_DEFAULT);

                        $stmt_update = mysqli_prepare($con, "UPDATE users SET password = ? WHERE id = ?");
                        if ($stmt_update) {
                            mysqli_stmt_bind_param($stmt_update, "si", $new_hashed_password, $userid);
                            if (mysqli_stmt_execute($stmt_update)) {
                                $success_message = "Password modificata con successo!";

                            } else {
                                $error_message = "Errore durante l'aggiornamento della password.";
                                error_log("User Change Password Update Error: " . mysqli_stmt_error($stmt_update));
                            }
                            mysqli_stmt_close($stmt_update);
                        } else {
                            $error_message = "Errore nella preparazione della query di aggiornamento.";
                            error_log("User Change Password Prepare Update Error: " . mysqli_error($con));
                        }
                    } else {

                        $error_message = "La password corrente non è corretta.";
                    }
                } else {

                    $error_message = "Errore nel recuperare i dati dell'utente.";
                }
            } else {
                 $error_message = "Errore nella preparazione della query di recupero password.";
                 error_log("User Change Password Prepare Fetch Error: " . mysqli_error($con));
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
        <meta name="description" content="Cambia Password Utente" />
        <meta name="author" content="" />
        <title>Cambia Password | Sistema Registrazione e Login</title>
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@latest/dist/style.css" rel="stylesheet" />
        <link href="css/styles.css" rel="stylesheet" />
        <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/js/all.min.js" crossorigin="anonymous"></script>
        <script language="javascript" type="text/javascript">
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
                            <li class="breadcrumb-item"><a href="welcome.php">Dashboard</a></li>
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
