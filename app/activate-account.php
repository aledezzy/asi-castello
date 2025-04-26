<?php
include_once 'includes/config.php';

$message = '';
$message_type = 'info'; // Default type

// 1. Recupera il token RAW dalla URL
$token_raw = $_GET['token'] ?? null;

if (!$token_raw) {
    $message = "Link di attivazione non valido o mancante.";
    $message_type = 'danger';
} else {
    // 2. Crea l'hash del token ricevuto per confrontarlo con il DB
    $token_hash_from_url = hash('sha256', $token_raw);

    // 3. Cerca l'utente con quell'hash di token e che sia INATTIVO
    $stmt_check = mysqli_prepare($con,
        "SELECT id, is_active
         FROM users
         WHERE activation_token = ? AND is_active = 0" // Cerca token E utente inattivo
    );

    if ($stmt_check) {
        mysqli_stmt_bind_param($stmt_check, "s", $token_hash_from_url);
        mysqli_stmt_execute($stmt_check);
        $result = mysqli_stmt_get_result($stmt_check);
        $user_data = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt_check);

        if ($user_data) {
            // Utente trovato e inattivo! Procedi con l'attivazione
            $user_id_to_activate = $user_data['id'];

            // 4. Aggiorna l'utente: imposta is_active = 1 e cancella il token
            $stmt_update = mysqli_prepare($con,
                "UPDATE users SET
                    is_active = 1,
                    activation_token = NULL  -- Rimuovi il token dopo l'uso
                 WHERE id = ?"
            );

            if ($stmt_update) {
                mysqli_stmt_bind_param($stmt_update, "i", $user_id_to_activate);
                if (mysqli_stmt_execute($stmt_update)) {
                    $message = "Account attivato con successo! Ora puoi effettuare il login.";
                    $message_type = 'success';
                } else {
                    $message = "Errore durante l'attivazione dell'account.";
                    $message_type = 'danger';
                    error_log("Activate Account Update Error: " . mysqli_stmt_error($stmt_update));
                }
                mysqli_stmt_close($stmt_update);
            } else {
                 $message = "Errore nella preparazione dell'aggiornamento account.";
                 $message_type = 'danger';
                 error_log("Activate Account Prepare Update Error: " . mysqli_error($con));
            }
        } else {
            // Nessun utente trovato con quel token O l'utente è già attivo
            // Controlla se l'utente esiste ma è già attivo
            $stmt_already_active = mysqli_prepare($con, "SELECT id FROM users WHERE activation_token = ? AND is_active = 1");
            if ($stmt_already_active) {
                 mysqli_stmt_bind_param($stmt_already_active, "s", $token_hash_from_url);
                 mysqli_stmt_execute($stmt_already_active);
                 mysqli_stmt_store_result($stmt_already_active);
                 if (mysqli_stmt_num_rows($stmt_already_active) > 0) {
                     $message = "Questo account è già stato attivato.";
                     $message_type = 'warning';
                 } else {
                     $message = "Link di attivazione non valido o scaduto.";
                     $message_type = 'danger';
                 }
                 mysqli_stmt_close($stmt_already_active);
            } else {
                 $message = "Link di attivazione non valido o scaduto.";
                 $message_type = 'danger';
            }
        }
    } else {
        $message = "Errore nella preparazione della query di verifica.";
        $message_type = 'danger';
        error_log("Activate Account Prepare Check Error: " . mysqli_error($con));
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
        <meta name="description" content="Attivazione Account" />
        <meta name="author" content="" />
        <title>Attivazione Account | Sistema Registrazione e Login</title>
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
                                        <h2>Sistema di Registrazione e Login</h2>
                                        <hr />
                                        <h3 class="text-center font-weight-light my-4">Attivazione Account</h3>
                                    </div>
                                    <div class="card-body text-center">

                                        <?php if (!empty($message)): ?>
                                            <div class="alert alert-<?php echo $message_type; ?>" role="alert">
                                                <?php echo htmlspecialchars($message); ?>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($message_type === 'success'): ?>
                                            <a href="login.php" class="btn btn-primary">Vai al Login</a>
                                        <?php elseif ($message_type === 'warning'): // Già attivo ?>
                                             <a href="login.php" class="btn btn-secondary">Vai al Login</a>
                                        <?php elseif ($message_type === 'danger'): // Errore o non valido ?>
                                             <a href="signup.php" class="btn btn-info">Torna alla Registrazione</a>
                                        <?php else: // Messaggio 'info' iniziale o caricamento ?>
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
       <?php include 'includes/footer.php';?>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
        <script src="js/scripts.js"></script>
    </body>
</html>
