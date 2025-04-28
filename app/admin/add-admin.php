<?php
session_start();
include_once('../includes/config.php'); // Database connection

// Check if admin is logged in
if (strlen($_SESSION['adminid'] ?? 0) == 0) {
    header('location:logout.php');
    exit(); // Stop script execution
}

// Code for adding a new admin
if (isset($_POST['submit'])) {
    $username = trim($_POST['username']); // Trim whitespace
    $password = $_POST['password'];
    $confirm_password = $_POST['confirmpassword'];

    // --- Server-side validation ---
    if (empty($username) || empty($password) || empty($confirm_password)) {
        echo "<script>alert('Tutti i campi sono obbligatori.');</script>";
    } elseif ($password !== $confirm_password) {
        echo "<script>alert('Le password non corrispondono.');</script>";
    }
    // Optional: Add password complexity check here if desired
    // elseif (!preg_match('/^(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{6,}$/', $password)) {
    //    echo "<script>alert('La password deve contenere almeno un numero, una lettera maiuscola e minuscola, e avere almeno 6 caratteri.');</script>";
    // }
    else {
        // Check if username already exists using prepared statement
        $stmt_check = mysqli_prepare($con, "SELECT id FROM admin WHERE username = ?");
        if ($stmt_check) {
            mysqli_stmt_bind_param($stmt_check, "s", $username);
            mysqli_stmt_execute($stmt_check);
            mysqli_stmt_store_result($stmt_check);
            $row_count = mysqli_stmt_num_rows($stmt_check);
            mysqli_stmt_close($stmt_check);

            if ($row_count > 0) {
                echo "<script>alert('Username già esistente. Scegline un altro.');</script>";
            } else {
                // Hash the password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Insert new admin using prepared statement
                $stmt_insert = mysqli_prepare($con, "INSERT INTO admin (username, password) VALUES (?, ?)");
                if ($stmt_insert) {
                    mysqli_stmt_bind_param($stmt_insert, "ss", $username, $hashed_password);
                    $success = mysqli_stmt_execute($stmt_insert);
                    mysqli_stmt_close($stmt_insert);

                    if ($success) {
                        echo "<script>alert('Nuovo amministratore aggiunto con successo.');</script>";
                        echo "<script>window.location.href='manage-admin.php'</script>"; // Redirect to manage page
                        exit(); // Stop script execution after redirect
                    } else {
                        echo "<script>alert('Errore durante l'aggiunta dell'amministratore.');</script>";
                        error_log("Admin Add Error: " . mysqli_error($con)); // Log detailed error
                    }
                } else {
                    echo "<script>alert('Errore nella preparazione della query di inserimento.');</script>";
                    error_log("Admin Add Prepare Error: " . mysqli_error($con)); // Log detailed error
                }
            }
        } else {
            echo "<script>alert('Errore nella preparazione della query di controllo username.');</script>";
            error_log("Admin Check Username Prepare Error: " . mysqli_error($con)); // Log detailed error
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
        <meta name="description" content="Aggiungi Nuovo Amministratore" />
        <meta name="author" content="" />
        <title>Aggiungi Amministratore | Sistema Admin</title>
        <link href="../css/styles.css" rel="stylesheet" />
        <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/js/all.min.js" crossorigin="anonymous"></script>
        <script type="text/javascript">
            // Basic client-side check (server-side validation is crucial)
            function checkpass() {
                if (document.addadmin.password.value !== document.addadmin.confirmpassword.value) {
                    alert('Le password non corrispondono.');
                    document.addadmin.confirmpassword.focus();
                    return false;
                }
                // Optional: Add client-side complexity check if needed
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
                        <h1 class="mt-4">Aggiungi Nuovo Amministratore</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="manage-admin.php">Gestisci Amministratori</a></li>
                            <li class="breadcrumb-item active">Aggiungi Amministratore</li>
                        </ol>

                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-user-plus me-1"></i>
                                Inserisci Dettagli Amministratore
                            </div>
                            <div class="card-body">
                                <form method="post" name="addadmin" onsubmit="return checkpass();">

                                    <div class="form-floating mb-3">
                                        <input class="form-control" id="username" name="username" type="text" placeholder="Inserisci username" required />
                                        <label for="username">Username</label>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <div class="form-floating mb-3 mb-md-0">
                                                <input class="form-control" id="password" name="password" type="password" placeholder="Crea una password" required
                                                pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{6,}"
                                                title="Almeno un numero, una maiuscola, una minuscola, min 6 caratteri"/>
                                                <label for="password">Password</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-floating mb-3 mb-md-0">
                                                <input class="form-control" id="confirmpassword" name="confirmpassword" type="password" placeholder="Conferma la password" required />
                                                <label for="confirmpassword">Conferma Password</label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mt-4 mb-0">
                                        <div class="d-grid">
                                            <button type="submit" class="btn btn-primary btn-block" name="submit">Aggiungi Amministratore</button>
                                        </div>
                                    </div>
                                </form>
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
