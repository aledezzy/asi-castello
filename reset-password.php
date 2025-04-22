<?php
include('includes/config.php');

$token = $_GET['token'] ?? null;
$email = $_GET['email'] ?? null;

$error_message = '';
$success_message = '';
$show_form = false; // Controlla se mostrare il form di reset

if (!$token || !$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error_message = "Link non valido o parametri mancanti.";
} else {
    // 1. Trova l'utente e verifica il token e la scadenza
    $current_time = date("Y-m-d H:i:s");
    $stmt_check = mysqli_prepare($con, "SELECT id, reset_token_hash, reset_token_expires_at FROM users WHERE email = ?");

    if ($stmt_check) {
        mysqli_stmt_bind_param($stmt_check, "s", $email);
        mysqli_stmt_execute($stmt_check);
        $result = mysqli_stmt_get_result($stmt_check);
        $user = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt_check);

        if ($user) {
            $tokenHash_from_db = $user['reset_token_hash'];
            $expiry_from_db = $user['reset_token_expires_at'];
            $userId = $user['id'];

            // Verifica l'hash del token fornito con quello nel DB
            // Nota: hash() produce un output esadecimale, quindi non serve bin2hex qui
            $tokenHash_from_url = hash('sha256', $token);

            if ($tokenHash_from_db !== null && hash_equals($tokenHash_from_db, $tokenHash_from_url)) {
                // Token corrisponde, controlla la scadenza
                if (strtotime($expiry_from_db) > strtotime($current_time)) {
                    // Token valido e non scaduto, mostra il form
                    $show_form = true;
                } else {
                    $error_message = "Il link di reset è scaduto.";
                }
            } else {
                $error_message = "Link di reset non valido o già utilizzato.";
            }
        } else {
            $error_message = "Nessun utente trovato con questa email."; // O un messaggio più generico
        }
    } else {
        $error_message = "Errore nella preparazione della query di verifica.";
        error_log("Reset Password Prepare Check Error: " . mysqli_error($con));
    }
}

// 2. Gestione dell'invio del form (POST)
if ($show_form && isset($_POST['reset_password'])) {
    $new_password = $_POST['newpassword'];
    $confirm_password = $_POST['confirmpassword'];
    $hidden_token = $_POST['token']; // Recupera il token nascosto per sicurezza
    $hidden_email = $_POST['email']; // Recupera l'email nascosta per sicurezza

    // Verifica che il token e l'email nel form corrispondano a quelli iniziali
    if ($hidden_token !== $token || $hidden_email !== $email) {
         $error_message = "Errore di validazione del form. Riprova.";
         $show_form = false; // Nascondi il form se c'è discrepanza
    } elseif (empty($new_password) || empty($confirm_password)) {
        $error_message = "Entrambi i campi password sono obbligatori.";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "Le password non corrispondono.";
    }
    // Optional: Aggiungi controllo complessità password lato server
    // elseif (!preg_match('/^(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{6,}$/', $new_password)) {
    //    $error_message = 'La nuova password deve contenere almeno un numero, una lettera maiuscola e minuscola, e avere almeno 6 caratteri.';
    // }
    else {
        // Hash della nuova password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        // Aggiorna la password e cancella il token/scadenza nel DB
        $stmt_update = mysqli_prepare($con, "UPDATE users SET password = ?, reset_token_hash = NULL, reset_token_expires_at = NULL WHERE id = ?");
        if ($stmt_update) {
            mysqli_stmt_bind_param($stmt_update, "si", $hashed_password, $userId);
            if (mysqli_stmt_execute($stmt_update)) {
                $success_message = "Password aggiornata con successo! Ora puoi effettuare il login.";
                $show_form = false; // Nascondi il form dopo il successo
            } else {
                $error_message = "Errore durante l'aggiornamento della password.";
                error_log("Reset Password Update Error: " . mysqli_stmt_error($stmt_update));
            }
            mysqli_stmt_close($stmt_update);
        } else {
            $error_message = "Errore nella preparazione dell'aggiornamento password.";
            error_log("Reset Password Prepare Update Error: " . mysqli_error($con));
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
        <meta name="description" content="Reimposta Password" />
        <meta name="author" content="" />
        <title>Reimposta Password | Sistema di Registrazione e Login</title>
        <link href="css/styles.css" rel="stylesheet" />
        <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/js/all.min.js" crossorigin="anonymous"></script>
        <script type="text/javascript">
            function checkpass()
            {
                // Basic client-side check (server-side validation is crucial)
                if(document.resetform.newpassword.value !== document.resetform.confirmpassword.value)
                {
                    alert('Le password non corrispondono!');
                    document.resetform.confirmpassword.focus();
                    return false;
                }
                // Optional: Add client-side complexity check
                const passPattern = /^(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{6,}$/;
                if (!passPattern.test(document.resetform.newpassword.value)) {
                   alert('La nuova password deve contenere almeno un numero, una lettera maiuscola e minuscola, e avere almeno 6 caratteri.');
                   document.resetform.newpassword.focus();
                   return false;
                }
                return true;
            }
        </script>
    </head>
    <body class="bg-primary">
        <div id="layoutAuthentication">
            <div id="layoutAuthentication_content">
                <main>
                    <div class="container">
                        <div class="row justify-content-center">
                            <div class="col-lg-5">
                                <div class="card shadow-lg border-0 rounded-lg mt-5">
                                    <div class="card-header">
                                        <h2 align="center">Sistema di Registrazione e Login</h2>
                                        <hr />
                                        <h3 class="text-center font-weight-light my-4">Reimposta la tua Password</h3>
                                    </div>
                                    <div class="card-body">

                                        <?php if (!empty($error_message)): ?>
                                            <div class="alert alert-danger" role="alert">
                                                <?php echo htmlspecialchars($error_message); ?>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($success_message)): ?>
                                            <div class="alert alert-success" role="alert">
                                                <?php echo htmlspecialchars($success_message); ?>
                                            </div>
                                            <div class="text-center">
                                                 <a class="btn btn-primary" href="login.php">Vai al Login</a>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($show_form): ?>
                                            <form method="post" name="resetform" onsubmit="return checkpass();">
                                                <!-- Campi nascosti per passare token ed email anche nel POST -->
                                                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                                                <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">

                                                <div class="form-floating mb-3">
                                                    <input class="form-control" id="newpassword" name="newpassword" type="password" placeholder="Nuova Password" required />
                                                    <label for="newpassword">Nuova Password</label>
                                                    <!-- Optional: Aggiungi pattern per complessità -->
                                                    <!-- pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{6,}" title="Almeno 6 caratteri, con maiuscole, minuscole e numeri." -->
                                                </div>
                                                <div class="form-floating mb-3">
                                                    <input class="form-control" id="confirmpassword" name="confirmpassword" type="password" placeholder="Conferma Nuova Password" required />
                                                    <label for="confirmpassword">Conferma Nuova Password</label>
                                                </div>
                                                <div class="d-flex align-items-center justify-content-end mt-4 mb-0">
                                                    <button class="btn btn-primary" type="submit" name="reset_password">Reimposta Password</button>
                                                </div>
                                            </form>
                                        <?php elseif (empty($success_message) && empty($error_message)): ?>
                                            <!-- Mostra un messaggio di caricamento o attesa se necessario -->
                                            <div class="text-center">Verifica del link in corso...</div>
                                        <?php elseif (empty($success_message)): ?>
                                             <!-- Se c'è stato un errore ma non un successo, mostra link per tornare indietro -->
                                             <div class="text-center mt-3">
                                                 <a class="small" href="password-recovery.php">Richiedi un nuovo link</a><br>
                                                 <a class="small" href="login.php">Torna al login</a>
                                             </div>
                                        <?php endif; ?>

                                    </div>
                                    <div class="card-footer text-center py-3">
                                        <div class="small"><a href="signup.php">Hai bisogno di un account? Registrati!</a></div>
                                         <div class="small"><a href="index.php">Torna alla Home</a></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
       <?php include('includes/footer.php');?>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
        <script src="js/scripts.js"></script>
    </body>
</html>
