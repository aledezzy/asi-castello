<?php
session_start();
include_once('../includes/config.php'); // Database connection

// Check if admin is logged in
if (strlen($_SESSION['adminid'] ?? 0) == 0) {
    header('location:logout.php');
    exit(); // Stop script execution
}

$admin_id_to_edit = 0; // Initialize
$current_username = '';
$error_message = '';
$success_message = '';

// --- Get the Admin ID from the URL ---
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $admin_id_to_edit = intval($_GET['id']);

    // Fetch current admin details
    $stmt_fetch = mysqli_prepare($con, "SELECT username FROM admin WHERE id = ?");
    if ($stmt_fetch) {
        mysqli_stmt_bind_param($stmt_fetch, "i", $admin_id_to_edit);
        mysqli_stmt_execute($stmt_fetch);
        $result = mysqli_stmt_get_result($stmt_fetch);
        $admin_data = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt_fetch);

        if ($admin_data) {
            $current_username = $admin_data['username'];
        } else {
            $error_message = "Amministratore non trovato.";
            // Optional: Redirect if admin not found
            // header('location: manage-admins.php'); exit();
        }
    } else {
        $error_message = "Errore nel recuperare i dati dell'amministratore.";
        error_log("Admin Fetch Prepare Error: " . mysqli_error($con));
    }

} else {
    // If no valid ID is provided, redirect back
    header('location: manage-admin.php');
    exit();
}


// --- Code for updating admin details ---
if (isset($_POST['update'])) {
    $new_username = trim($_POST['username']);
    $new_password = $_POST['newpassword']; // Password can be empty if not changing
    $confirm_password = $_POST['confirmpassword'];

    $update_fields = [];
    $bind_types = "";
    $bind_params = [];
    $update_needed = false;

    // --- Server-side validation ---
    if (empty($new_username)) {
        $error_message = "L'username è obbligatorio.";
    } else {
        // 1. Check if username needs updating and if it's unique
        if ($new_username !== $current_username) {
            $stmt_check_user = mysqli_prepare($con, "SELECT id FROM admin WHERE username = ? AND id != ?");
            if ($stmt_check_user) {
                mysqli_stmt_bind_param($stmt_check_user, "si", $new_username, $admin_id_to_edit);
                mysqli_stmt_execute($stmt_check_user);
                mysqli_stmt_store_result($stmt_check_user);
                $user_exists = mysqli_stmt_num_rows($stmt_check_user) > 0;
                mysqli_stmt_close($stmt_check_user);

                if ($user_exists) {
                    $error_message = "Username già esistente. Scegline un altro.";
                } else {
                    $update_fields[] = "username = ?";
                    $bind_types .= "s";
                    $bind_params[] = $new_username;
                    $update_needed = true;
                }
            } else {
                 $error_message = "Errore nel controllo dell'username.";
                 error_log("Admin Check Username Update Prepare Error: " . mysqli_error($con));
            }
        }

        // 2. Check if password needs updating
        if (!empty($new_password)) {
            if ($new_password !== $confirm_password) {
                $error_message = "Le nuove password non corrispondono.";
            }
            // Optional: Add password complexity check here if desired
            // elseif (!preg_match('/^(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{6,}$/', $new_password)) {
            //    $error_message = 'La password deve contenere almeno un numero, una lettera maiuscola e minuscola, e avere almeno 6 caratteri.';
            // }
            else {
                // Hash the new password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_fields[] = "password = ?";
                $bind_types .= "s";
                $bind_params[] = $hashed_password;
                $update_needed = true;
            }
        }

        // 3. Perform update if needed and no errors occurred
        if ($update_needed && empty($error_message)) {
            $sql_update = "UPDATE admin SET " . implode(", ", $update_fields) . " WHERE id = ?";
            $bind_types .= "i";
            $bind_params[] = $admin_id_to_edit; // Add the ID at the end

            $stmt_update = mysqli_prepare($con, $sql_update);
            if ($stmt_update) {
                // Dynamically bind parameters
                mysqli_stmt_bind_param($stmt_update, $bind_types, ...$bind_params); // Use splat operator (...)

                if (mysqli_stmt_execute($stmt_update)) {
                    $success_message = "Dettagli amministratore aggiornati con successo.";
                    // Update current username in case it was changed, for display after refresh
                    $current_username = $new_username;
                    // Optionally redirect after a short delay or keep showing the success message
                     echo "<script>alert('Dettagli aggiornati!'); window.location.href='manage-admin.php';</script>";
                     exit();
                } else {
                    $error_message = "Errore durante l'aggiornamento dell'amministratore.";
                    error_log("Admin Update Error: " . mysqli_stmt_error($stmt_update));
                }
                mysqli_stmt_close($stmt_update);
            } else {
                $error_message = "Errore nella preparazione della query di aggiornamento.";
                error_log("Admin Update Prepare Error: " . mysqli_error($con));
            }
        } elseif (!$update_needed && empty($error_message)) {
             $error_message = "Nessuna modifica rilevata."; // Or just do nothing
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
        <meta name="description" content="Modifica Amministratore" />
        <meta name="author" content="" />
        <title>Modifica Amministratore | Sistema Admin</title>
        <link href="../css/styles.css" rel="stylesheet" />
        <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/js/all.min.js" crossorigin="anonymous"></script>
        <script type="text/javascript">
            // Basic client-side check (server-side validation is crucial)
            function checkPass() {
                const newPass = document.editadmin.newpassword.value;
                const confirmPass = document.editadmin.confirmpassword.value;

                // Only validate if new password field is not empty
                if (newPass !== "" && newPass !== confirmPass) {
                    alert('Le nuove password non corrispondono.');
                    document.editadmin.confirmpassword.focus();
                    return false;
                }
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
                        <h1 class="mt-4">Modifica Amministratore</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="manage-admin.php">Gestisci Amministratori</a></li>
                            <li class="breadcrumb-item active">Modifica Amministratore</li>
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

                        <?php // Only show form if admin data was found initially
                        if (!empty($current_username) || isset($_POST['update'])) { // Show form also if post failed, to retain values
                        ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-edit me-1"></i>
                                Modifica Dettagli per <?php echo htmlspecialchars($current_username); ?>
                            </div>
                            <div class="card-body">
                                <form method="post" name="editadmin" onsubmit="return checkPass();">
                                    
                                    <input type="hidden" name="admin_id" value="<?php echo $admin_id_to_edit; ?>">

                                    <div class="form-floating mb-3">
                                        <input class="form-control" id="username" name="username" type="text" placeholder="Modifica username" value="<?php echo htmlspecialchars(isset($_POST['username']) ? $_POST['username'] : $current_username); ?>" required />
                                        <label for="username">Username</label>
                                    </div>

                                    <hr>
                                    <p class="text-muted">Lasciare i campi password vuoti per non modificarla.</p>

                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <div class="form-floating mb-3 mb-md-0">
                                                <input class="form-control" id="newpassword" name="newpassword" type="password" placeholder="Nuova password (opzionale)" pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{6,}"
                                                title="Almeno un numero, una maiuscola, una minuscola, min 6 caratteri"
                                                 />
                                                <label for="newpassword">Nuova Password (opzionale)</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-floating mb-3 mb-md-0">
                                                <input class="form-control" id="confirmpassword" name="confirmpassword" type="password" placeholder="Conferma nuova password" />
                                                <label for="confirmpassword">Conferma Nuova Password</label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mt-4 mb-0">
                                        <div class="d-grid">
                                            <button type="submit" class="btn btn-primary btn-block" name="update">Aggiorna Dettagli</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <?php } // End of check if admin data was found ?>
                    </div>
                </main>
                <?php include('../includes/footer.php');?>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
        <script src="../js/scripts.js"></script>
    </body>
</html>
