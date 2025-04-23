<?php
// Non serve session_start() qui, l'utente non è ancora loggato con la nuova password
include_once('includes/config.php');

$message = '';
$message_type = 'info'; // Default type

// 1. Recupera token e user ID dalla URL
$token = $_GET['token'] ?? null;
$user_id = filter_input(INPUT_GET, 'uid', FILTER_VALIDATE_INT);

if (!$token || !$user_id) {
    $message = "Link di conferma non valido o parametri mancanti.";
    $message_type = 'danger';
} else {
    // 2. Trova l'utente e verifica il token/scadenza/nuova password
    $current_time = date("Y-m-d H:i:s");
    $stmt_check = mysqli_prepare($con,
        "SELECT new_password_hash, change_password_token_hash, change_password_token_expires_at
         FROM users
         WHERE id = ?"
    );

    if ($stmt_check) {
        mysqli_stmt_bind_param($stmt_check, "i", $user_id);
        mysqli_stmt_execute($stmt_check);
        $result = mysqli_stmt_get_result($stmt_check);
        $user_data = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt_check);

        if ($user_data) {
            $new_password_hash_db = $user_data['new_password_hash'];
            $token_hash_db = $user_data['change_password_token_hash'];
            $expiry_db = $user_data['change_password_token_expires_at'];

            // 3. Verifica che ci sia una richiesta di cambio password pendente
            if ($new_password_hash_db === null || $token_hash_db === null || $expiry_db === null) {
                $message = "Nessuna richiesta di modifica password valida trovata o già utilizzata.";
                $message_type = 'warning';
            } else {
                // 4. Verifica l'hash del token
                $token_hash_from_url = hash('sha256', $token);

                if (hash_equals($token_hash_db, $token_hash_from_url)) {
                    // Token corrisponde, controlla la scadenza
                    if (strtotime($expiry_db) > strtotime($current_time)) {
                        // Token valido e non scaduto! Procedi con l'aggiornamento

                        // 5. Aggiorna la password e pulisci i campi temporanei
                        $stmt_update = mysqli_prepare($con,
                            "UPDATE users SET
                                password = ?,
                                new_password_hash = NULL,
                                change_password_token_hash = NULL,
                                change_password_token_expires_at = NULL
                             WHERE id = ?"
                        );

                        if ($stmt_update) {
                            mysqli_stmt_bind_param($stmt_update, "si", $new_password_hash_db, $user_id);
                            if (mysqli_stmt_execute($stmt_update)) {
                                $message = "Password modificata con successo! Ora puoi effettuare il login con la nuova password.";
                                $message_type = 'success';
                            } else {
                                $message = "Errore durante l'aggiornamento finale della password.";
                                $message_type = 'danger';
                                error_log("Confirm Change Password Update Error: " . mysqli_stmt_error($stmt_update));
                            }
                            mysqli_stmt_close($stmt_update);
                        } else {
                             $message = "Errore nella preparazione dell'aggiornamento finale della password.";
                             $message_type = 'danger';
                             error_log("Confirm Change Password Prepare Update Error: " . mysqli_error($con));
                        }
                    } else {
                        // Token scaduto
                        $message = "Il link di conferma per la modifica della password è scaduto. Richiedi nuovamente la modifica.";
                        $message_type = 'danger';
                        // Opzionale: pulire i campi temporanei anche se scaduto
                         mysqli_query($con, "UPDATE users SET new_password_hash = NULL, change_password_token_hash = NULL, change_password_token_expires_at = NULL WHERE id = $user_id");
                    }
                } else {
                    // Token non corrisponde
                    $message = "Link di conferma non valido o già utilizzato.";
                    $message_type = 'danger';
                }
            }
        } else {
            // Utente non trovato
            $message = "Utente non trovato.";
            $message_type = 'danger';
        }
    } else {
        $message = "Errore nella preparazione della query di verifica.";
        $message_type = 'danger';
        error_log("Confirm Change Password Prepare Check Error: " . mysqli_error($con));
    }
}

mysqli_close($con);
?>
<!DOCTYPE html>
<html lang="it">
    <head>
        <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="description" content="Conferma Modifica Password" />
        <meta name="author" content="" />
        <title>Conferma Modifica Password | Sistema Registrazione e Login</title>
        <link href="css/styles.css" rel="stylesheet" />
        <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/js/all.min.js" crossorigin="anonymous"></script>
    </head>
    <body class="bg-primary">
        <div id="layoutAuthentication">
            <div id="layoutAuthentication_content">
                <main>
                    <div class="container">
                        <div class="row justify-content-center">
                            <div class="col-lg-6"> 
                                <div class="card shadow-lg border-0 rounded-lg mt-5">
                                    <div class="card-header">
                                        <h2 align="center">Sistema di Registrazione e Login</h2>
                                        <hr />
                                        <h3 class="text-center font-weight-light my-4">Conferma Modifica Password</h3>
                                    </div>
                                    <div class="card-body text-center">

                                        <?php if (!empty($message)): ?>
                                            <div class="alert alert-<?php echo $message_type; ?>" role="alert">
                                                <?php echo htmlspecialchars($message); ?>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($message_type === 'success'): ?>
                                            <a href="login.php" class="btn btn-primary">Vai al Login</a>
                                        <?php elseif ($message_type === 'danger' || $message_type === 'warning'): ?>
                                             <a href="login.php" class="btn btn-secondary">Torna al Login</a>
                                             <?php // Potresti aggiungere un link per richiedere di nuovo il cambio se l'errore è 'scaduto' o 'non valido' ?>
                                             <?php if (strpos($message, 'scaduto') !== false || strpos($message, 'non valido') !== false): ?>
                                                <p class="mt-3 small">Se necessario, puoi <a href="login.php">accedere</a> e richiedere nuovamente la modifica.</p>
                                             <?php endif; ?>
                                        <?php else: ?>
                                             <p>Verifica del link in corso...</p>
                                        <?php endif; ?>

                                    </div>
                                    <div class="card-footer text-center py-3">
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
