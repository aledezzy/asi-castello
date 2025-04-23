<?php session_start();

// Code for login
include 'includes/config.php';

// --- Costanti per il blocco ---
define('MAX_IP_ATTEMPTS', 5); // Max tentativi falliti per IP
define('MAX_PASSWORD_ATTEMPTS', 5); // Max tentativi falliti per password (email corretta)
define('ATTEMPT_TIME_WINDOW_MINUTES', 15); // Intervallo di tempo (in minuti) per contare i tentativi

$login_error_message = ''; // Variabile specifica per errori di login

if(isset($_POST['login']))
{
    $useremail = $_POST['uemail'];
    $password_from_form = $_POST['password']; // Password entered by user
    $ip_address = $_SERVER['REMOTE_ADDR']; // Get user IP address
    $attempt_timestamp = date('Y-m-d H:i:s'); // Get current timestamp for logging
    $time_limit_sql = date('Y-m-d H:i:s', time() - (ATTEMPT_TIME_WINDOW_MINUTES * 60)); // Calcola tempo limite per query

    // Variables for logging
    $log_user_id = null;
    $log_success = 0; // Default to failure
    $login_denied_reason = ''; // To store reason for denial if applicable

    // --- CHECK 0: IP GIA' BANNATO ---
    $stmt_check_ip_ban = mysqli_prepare($con, "SELECT id FROM banned_ips WHERE ip_address = ?");
    if ($stmt_check_ip_ban) {
        mysqli_stmt_bind_param($stmt_check_ip_ban, "s", $ip_address);
        mysqli_stmt_execute($stmt_check_ip_ban);
        mysqli_stmt_store_result($stmt_check_ip_ban);
        if (mysqli_stmt_num_rows($stmt_check_ip_ban) > 0) {
            $login_error_message = 'Accesso negato dal tuo indirizzo IP (bloccato).';
            $login_denied_reason = 'IP Already Banned';
            $log_success = 0; // Logga comunque il tentativo bloccato
        }
        mysqli_stmt_close($stmt_check_ip_ban);
    } else {
        error_log("Check Banned IP (Existing) Prepare Error: " . mysqli_error($con));
        // Considera se bloccare o meno in caso di errore DB
    }

    // --- CHECK 1: TENTATIVI FALLITI PER IP (se non già bannato) ---
    if (empty($login_error_message)) {
        $stmt_count_ip = mysqli_prepare($con, "SELECT COUNT(*) as failed_count FROM login_attempts WHERE ip_address = ? AND success = 0 AND attempt_timestamp > ?");
        if ($stmt_count_ip) {
            mysqli_stmt_bind_param($stmt_count_ip, "ss", $ip_address, $time_limit_sql);
            mysqli_stmt_execute($stmt_count_ip);
            $result_count_ip = mysqli_stmt_get_result($stmt_count_ip);
            $row_count_ip = mysqli_fetch_assoc($result_count_ip);
            mysqli_stmt_close($stmt_count_ip);

            if ($row_count_ip && $row_count_ip['failed_count'] >= MAX_IP_ATTEMPTS) {
                // Troppi tentativi falliti dall'IP -> Banna l'IP
                $stmt_ban_ip = mysqli_prepare($con, "INSERT INTO banned_ips (ip_address, reason, banned_at) VALUES (?, 'Troppi tentativi falliti', NOW()) ON DUPLICATE KEY UPDATE reason='Troppi tentativi falliti', banned_at=NOW()");
                if ($stmt_ban_ip) {
                    mysqli_stmt_bind_param($stmt_ban_ip, "s", $ip_address);
                    mysqli_stmt_execute($stmt_ban_ip);
                    mysqli_stmt_close($stmt_ban_ip);
                    $login_error_message = 'Accesso bloccato temporaneamente per troppi tentativi falliti da questo indirizzo IP.';
                    $login_denied_reason = 'IP Banned (Too Many Attempts)';
                    $log_success = 0;
                } else {
                    error_log("Ban IP Prepare Error: " . mysqli_error($con));
                }
            }
        } else {
            error_log("Count IP Attempts Prepare Error: " . mysqli_error($con));
        }
    }

    // --- CHECK 2: EMAIL GIA' BANNATA (se IP non bannato) ---
    if (empty($login_error_message)) {
        $stmt_check_email_ban = mysqli_prepare($con, "SELECT id FROM banned_emails WHERE email = ?");
        if ($stmt_check_email_ban) {
            mysqli_stmt_bind_param($stmt_check_email_ban, "s", $useremail);
            mysqli_stmt_execute($stmt_check_email_ban);
            mysqli_stmt_store_result($stmt_check_email_ban);
            if (mysqli_stmt_num_rows($stmt_check_email_ban) > 0) {
                $login_error_message = 'Questo indirizzo email è stato bloccato.';
                $login_denied_reason = 'Email Already Banned';
                $log_success = 0;
            }
            mysqli_stmt_close($stmt_check_email_ban);
        } else {
            error_log("Check Banned Email (Existing) Prepare Error: " . mysqli_error($con));
        }
    }

    // --- Proceed with Login Attempt only if not already denied ---
    if (empty($login_error_message)) {
        // Use prepared statement to prevent SQL injection - Fetch is_active as well
        $stmt = mysqli_prepare($con, "SELECT id, fname, password, is_active FROM users WHERE email=?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $useremail);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $user_data = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt); // Close statement after fetching

            // Verify the password
            if($user_data && password_verify($password_from_form, $user_data['password']))
            {
                // Password is correct, now check if account is active
                if ($user_data['is_active'] == 1) {
                    // Account is active - Login successful
                    $log_user_id = $user_data['id'];
                    $log_success = 1;

                    // --- LOG SUCCESSFUL ATTEMPT ---
                    // (Codice log successo invariato)
                    $stmt_log = mysqli_prepare($con, "INSERT INTO login_attempts (user_id, email_attempted, ip_address, attempt_timestamp, success) VALUES (?, ?, ?, ?, ?)");
                    if ($stmt_log) {
                        mysqli_stmt_bind_param($stmt_log, "isssi", $log_user_id, $useremail, $ip_address, $attempt_timestamp, $log_success);
                        mysqli_stmt_execute($stmt_log);
                        mysqli_stmt_close($stmt_log);
                    } else {
                        error_log("Login Log Prepare Error (Success): " . mysqli_error($con));
                    }
                    // --- END LOG ---

                    // Set session variables and redirect
                    $_SESSION['id'] = $user_data['id'];
                    $_SESSION['name'] = $user_data['fname'];
                    header("location:welcome.php");
                    exit(); // Add exit after redirect

                } else {
                    // Account is not active (manualmente o per blocco precedente)
                    $log_user_id = $user_data['id']; // Log the user ID
                    $log_success = 0;
                    $login_error_message = 'Il tuo account non è attivo o è stato bloccato. Contatta l\'amministrazione.';
                    $login_denied_reason = 'Account Inactive/Locked';
                }

            }
            else // Email trovata ma password errata OPPURE Email non trovata
            {
                $log_success = 0;
                if ($user_data) { // Email trovata, password errata
                    $log_user_id = $user_data['id'];
                    $login_denied_reason = 'Invalid Credentials (Wrong Password)';

                    // --- CHECK 3: TENTATIVI FALLITI PER PASSWORD ---
                    $stmt_count_pass = mysqli_prepare($con, "SELECT COUNT(*) as failed_count FROM login_attempts WHERE user_id = ? AND success = 0 AND attempt_timestamp > ?");
                    if ($stmt_count_pass) {
                        mysqli_stmt_bind_param($stmt_count_pass, "is", $log_user_id, $time_limit_sql);
                        mysqli_stmt_execute($stmt_count_pass);
                        $result_count_pass = mysqli_stmt_get_result($stmt_count_pass);
                        $row_count_pass = mysqli_fetch_assoc($result_count_pass);
                        mysqli_stmt_close($stmt_count_pass);

                        // Nota: il conteggio include il tentativo fallito CORRENTE che verrà loggato dopo
                        if ($row_count_pass && ($row_count_pass['failed_count'] + 1) >= MAX_PASSWORD_ATTEMPTS) {
                            // Troppi tentativi falliti per questa email -> Banna email e disabilita account
                            $commit_ban = true;

                            // Banna Email
                            $stmt_ban_email = mysqli_prepare($con, "INSERT INTO banned_emails (email, reason, banned_at) VALUES (?, 'Troppi tentativi password falliti', NOW()) ON DUPLICATE KEY UPDATE reason='Troppi tentativi password falliti', banned_at=NOW()");
                            if ($stmt_ban_email) {
                                mysqli_stmt_bind_param($stmt_ban_email, "s", $useremail);
                                if (!mysqli_stmt_execute($stmt_ban_email)) {
                                    $commit_ban = false;
                                    error_log("Ban Email Prepare Error: " . mysqli_stmt_error($stmt_ban_email));
                                }
                                mysqli_stmt_close($stmt_ban_email);
                            } else {
                                 $commit_ban = false;
                                 error_log("Ban Email Prepare Error: " . mysqli_error($con));
                            }

                            // Disabilita Account
                            if ($commit_ban) {
                                $stmt_disable = mysqli_prepare($con, "UPDATE users SET is_active = 0 WHERE id = ?");
                                if ($stmt_disable) {
                                    mysqli_stmt_bind_param($stmt_disable, "i", $log_user_id);
                                    if (!mysqli_stmt_execute($stmt_disable)) {
                                        // Non consideriamo questo un errore fatale per il messaggio, ma logghiamo
                                        error_log("Disable User Account Error: " . mysqli_stmt_error($stmt_disable));
                                    }
                                    mysqli_stmt_close($stmt_disable);
                                } else {
                                     error_log("Disable User Account Prepare Error: " . mysqli_error($con));
                                }
                            }

                            $login_error_message = 'Account bloccato per troppi tentativi di password errati. Contatta l\'amministrazione.';
                            $login_denied_reason = 'Email Banned & Account Disabled (Too Many Attempts)';

                        } else {
                            // Meno di N tentativi falliti, errore standard
                            $login_error_message = 'Email o password sbagliate';
                        }
                    } else {
                         error_log("Count Password Attempts Prepare Error: " . mysqli_error($con));
                         $login_error_message = 'Email o password sbagliate'; // Errore standard in caso di fallimento query conteggio
                    }

                } else { // Email non trovata
                    $login_error_message = 'Email o password sbagliate';
                    $login_denied_reason = 'Invalid Credentials (Email Not Found)';
                    $log_user_id = null;
                }
            }

        } else {
            // Error preparing the SELECT statement
            $login_error_message = 'Errore nel sistema di login. Riprova più tardi.';
            $login_denied_reason = 'DB Error (User Fetch)';
            error_log("Login Prepare Error: " . mysqli_error($con)); // Log DB error
            $log_success = 0; // Ensure failure is logged
            $log_user_id = null; // Cannot determine user ID
        }
    } // End if (empty($login_error_message)) for initial checks

    // --- LOG FAILED ATTEMPT (Consolidated) ---
    // Logga SEMPRE il tentativo fallito, indipendentemente dal motivo del blocco
    if ($log_success == 0) {
        // Aggiungi qui $login_denied_reason se modifichi la tabella login_attempts
        $stmt_log = mysqli_prepare($con, "INSERT INTO login_attempts (user_id, email_attempted, ip_address, attempt_timestamp, success) VALUES (?, ?, ?, ?, ?)");
         if ($stmt_log) {
            mysqli_stmt_bind_param($stmt_log, "isssi", $log_user_id, $useremail, $ip_address, $attempt_timestamp, $log_success);
            mysqli_stmt_execute($stmt_log);
            mysqli_stmt_close($stmt_log);
        } else {
            error_log("Login Log Prepare Error (Failure/Denied): " . mysqli_error($con)); // Log DB error
        }
    }
    // --- END LOG ---

} // End if(isset($_POST['login']))

?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <!-- ... (head invariato) ... -->
        <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="description" content="" />
        <meta name="author" content="" />
        <title>User Login</title>
        <link href="css/styles.css" rel="stylesheet" />
        <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/js/all.min.js" crossorigin="anonymous"></script>
        <style>
            /* Stile per il messaggio di timeout */
            #timeout-message {
                display: none; /* Nascosto di default */
                color: red;
                margin-top: 10px;
                text-align: center;
            }
        </style>
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
                                        <h2 align="center">Login Asi</h2>
                                        <hr />
                                        <h3 class="text-center font-weight-light my-4">Login utente</h3>
                                    </div>
                                    <div class="card-body">

                                        <?php if (!empty($login_error_message)): ?>
                                            <div class="alert alert-danger" role="alert">
                                                <?php echo htmlspecialchars($login_error_message); ?>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Aggiungi un div per il messaggio di timeout -->
                                        <div id="timeout-message" class="alert alert-warning" role="alert">
                                            Tempo scaduto per il login. Il form è stato resettato.
                                        </div>

                                        <form method="post" id="loginForm">

                                            <div class="form-floating mb-3">
                                                <input class="form-control" id="uemail" name="uemail" type="email" placeholder="inserisci la tua email" required value="<?php echo isset($_POST['uemail']) ? htmlspecialchars($_POST['uemail']) : ''; // Mantieni email in caso di errore ?>"/>
                                                <label for="inputEmail">Indirizzo email</label>
                                            </div>

                                            <div class="form-floating mb-3">
                                                <input class="form-control" id="password" name="password" type="password" placeholder="Password" required />
                                                <label for="inputPassword">Password</label>
                                            </div>

                                            <div class="d-flex align-items-center justify-content-between mt-4 mb-0">
                                                <a class="small" href="password-recovery.php">Password dimenticata?</a>
                                                <button class="btn btn-primary" name="login" type="submit">Login</button>
                                            </div>
                                        </form>
                                    </div>
                                    <div class="card-footer text-center py-3">
                                        <div class="small"><a href="signup.php">Non hai un account? Creane uno!</a></div>
                                        <div class="small"><a href="index.php">Torna alla home</a></div>
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

        <!-- Script per il Timeout del Form (invariato) -->
        <script>
            // ... (script timeout invariato) ...
             // Imposta il timeout in millisecondi (30 secondi)
            const timeoutDuration = 30000;
            let timeoutId; // Variabile per memorizzare l'ID del timeout

            // Funzione per resettare il form e mostrare il messaggio
            function handleTimeout() {
                const loginForm = document.getElementById('loginForm');
                const timeoutMessage = document.getElementById('timeout-message');

                if (loginForm) {
                    loginForm.reset(); // Resetta i campi del form
                }
                if (timeoutMessage) {
                    timeoutMessage.style.display = 'block'; // Mostra il messaggio di timeout
                }
            }

            // Funzione per avviare il timer
            function startTimeout() {
                clearTimeout(timeoutId);
                timeoutId = setTimeout(handleTimeout, timeoutDuration);
            }

            // Funzione per cancellare il timer (chiamata al submit)
            function clearTimeoutOnSubmit() {
                clearTimeout(timeoutId);
            }

            // Avvia il timer quando la pagina è caricata
            window.onload = startTimeout;

            // Aggiungi un listener al form per cancellare il timer al submit
            const loginForm = document.getElementById('loginForm');
            if (loginForm) {
                loginForm.addEventListener('submit', clearTimeoutOnSubmit);
            }
        </script>
    </body>
</html>

