<?php session_start();
include_once('includes/config.php'); // Assicurati che config.php definisca le costanti RECAPTCHA
// Includi PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php';

if (strlen($_SESSION['id'] ?? 0) == 0) {
  header('location:logout.php');
  exit();
} else {

    $userid = $_SESSION['id'];
    $error_message = '';
    $success_message = '';

    if(isset($_POST['update']))
    {
        // --- INIZIO VERIFICA reCAPTCHA ---
        $recaptcha_response = $_POST['g-recaptcha-response'] ?? null;
        $recaptcha_valid = false;

        if ($recaptcha_response) {
            $recaptcha_url = 'https://www.google.com/recaptcha/api/siteverify';
            $recaptcha_data = [
                'secret'   => RECAPTCHA_SECRET_KEY, // Usa la costante da config.php
                'response' => $recaptcha_response,
                'remoteip' => $_SERVER['REMOTE_ADDR'] // Opzionale ma consigliato
            ];

            // Usa cURL per la richiesta POST
            $options = [
                'http' => [
                    'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                    'method'  => 'POST',
                    'content' => http_build_query($recaptcha_data),
                ],
            ];
            $context  = stream_context_create($options);
            $verify_response = file_get_contents($recaptcha_url, false, $context);
            $response_data = json_decode($verify_response);

            if ($response_data && $response_data->success) {
                $recaptcha_valid = true;
            } else {
                // Logga l'errore reCAPTCHA per debug, se presente
                if (isset($response_data->{'error-codes'})) {
                    error_log("reCAPTCHA verification failed: " . implode(', ', $response_data->{'error-codes'}));
                }
                $error_message = "Verifica CAPTCHA fallita. Riprova.";
            }
        } else {
            $error_message = "Per favore, completa la verifica CAPTCHA.";
        }
        // --- FINE VERIFICA reCAPTCHA ---

        // Procedi solo se il CAPTCHA è valido
        if ($recaptcha_valid) {
            $currentpassword_form = $_POST['currentpassword'];
            $newpassword = $_POST['newpassword'];
            $confirmpassword = $_POST['confirmpassword'];

            // Controlli password (come prima)
            if (empty($currentpassword_form) || empty($newpassword) || empty($confirmpassword)) {
                $error_message = "Tutti i campi password sono obbligatori.";
            } elseif ($newpassword !== $confirmpassword) {
                $error_message = "La nuova password e la conferma non corrispondono.";
            } else {
                // 1. Recupera password corrente e email utente (come prima)
                $stmt_fetch = mysqli_prepare($con, "SELECT password, email, fname FROM users WHERE id = ?");
                if ($stmt_fetch) {
                    mysqli_stmt_bind_param($stmt_fetch, "i", $userid);
                    mysqli_stmt_execute($stmt_fetch);
                    $result_fetch = mysqli_stmt_get_result($stmt_fetch);
                    $user_data = mysqli_fetch_assoc($result_fetch);
                    mysqli_stmt_close($stmt_fetch);

                    if ($user_data) {
                        $current_hashed_password_db = $user_data['password'];
                        $user_email = $user_data['email'];
                        $user_fname = $user_data['fname'];

                        // 2. Verifica la password corrente (come prima)
                        if (password_verify($currentpassword_form, $current_hashed_password_db)) {

                            // 3. Genera token, hash e scadenza (come prima)
                            try {
                                $token = bin2hex(random_bytes(32));
                                $tokenHash = hash('sha256', $token);
                                $expiry = date("Y-m-d H:i:s", time() + 1800); // Scadenza tra 30 minuti

                                // 4. Hash della NUOVA password (come prima)
                                $new_hashed_password = password_hash($newpassword, PASSWORD_DEFAULT);

                                // 5. Salva hash nuova password, hash token e scadenza nel DB (come prima)
                                $stmt_update_temp = mysqli_prepare($con,
                                    "UPDATE users SET
                                        new_password_hash = ?,
                                        change_password_token_hash = ?,
                                        change_password_token_expires_at = ?
                                     WHERE id = ?"
                                );
                                if ($stmt_update_temp) {
                                    mysqli_stmt_bind_param($stmt_update_temp, "sssi",
                                        $new_hashed_password,
                                        $tokenHash,
                                        $expiry,
                                        $userid
                                    );

                                    if (mysqli_stmt_execute($stmt_update_temp)) {
                                        mysqli_stmt_close($stmt_update_temp);

                                        // 6. Invia email di conferma (come prima)
                                        $mail = new PHPMailer(true);
                                        try {
                                            // Impostazioni Server...
                                            $mail->isSMTP();
                                            $mail->Host = 'smtp.gmail.com';
                                            $mail->SMTPAuth = true;
                                            $mail->Username = 'dezuani.fotovoltaico@gmail.com';
                                            $mail->Password = 'ymzf ceed cgvr tpga';
                                            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                                            $mail->Port = 587;
                                            $mail->CharSet = 'UTF-8';

                                            // Mittente e Destinatario...
                                            $mail->setFrom('dezuani.fotovoltaico@gmail.com', 'Asi-Castello');
                                            $mail->addAddress($user_email, $user_fname);

                                            // Contenuto...
                                            $mail->isHTML(true);
                                            $mail->Subject = 'Conferma Modifica Password';
                                            $confirmLinkBase = "http://localhost/asi-castello/";
                                            $confirmLink = $confirmLinkBase . "confirm-password-change.php?token=" . urlencode($token) . "&uid=" . $userid;
                                            // ... (corpo email come prima) ...
                                            $bodyContent = "Gentile " . htmlspecialchars($user_fname) . ",<br><br>";
                                            $bodyContent .= "Hai richiesto una modifica della password per il tuo account.<br>";
                                            $bodyContent .= "Per confermare questa modifica, clicca sul seguente link:<br>";
                                            $bodyContent .= '<a href="' . $confirmLink . '">' . $confirmLink . '</a><br><br>';
                                            $bodyContent .= "Se non hai richiesto tu questa modifica, ignora questa email e la tua password rimarrà invariata.<br>";
                                            $bodyContent .= "Questo link scadrà tra 30 minuti.<br><br>";
                                            $bodyContent .= "Cordiali saluti,<br>Il Team Asi-Castello";
                                            $mail->Body = $bodyContent;
                                            $mail->AltBody = "Gentile " . htmlspecialchars($user_fname) . ",\n\nHai richiesto una modifica della password.\nPer confermare, visita il seguente link:\n" . $confirmLink . "\n\nSe non hai richiesto tu la modifica, ignora questa email.\nIl link scadrà tra 30 minuti.\n\nCordiali saluti,\nIl Team Asi-Castello";


                                            $mail->send();

                                            // Messaggio per l'utente (come prima)
                                            $success_message = 'Modifica password avviata. Controlla la tua email (' . htmlspecialchars($user_email) . ') per il link di conferma.';
                                            $_POST['currentpassword'] = '';
                                            $_POST['newpassword'] = '';
                                            $_POST['confirmpassword'] = '';

                                        } catch (Exception $e) {
                                            $error_message = "Errore nell'invio dell'email di conferma. La password NON è stata modificata. Errore Mailer: {$mail->ErrorInfo}";
                                            error_log("Mailer Error [Change Password Confirm]: " . $mail->ErrorInfo);
                                            mysqli_query($con, "UPDATE users SET new_password_hash = NULL, change_password_token_hash = NULL, change_password_token_expires_at = NULL WHERE id = $userid");
                                        }

                                    } else {
                                        $error_message = "Errore durante il salvataggio temporaneo della richiesta.";
                                        error_log("User Change Password Temp Update Error: " . mysqli_stmt_error($stmt_update_temp));
                                    }
                                } else {
                                    $error_message = "Errore nella preparazione della query di salvataggio temporaneo.";
                                    error_log("User Change Password Prepare Temp Update Error: " . mysqli_error($con));
                                }

                            } catch (Exception $e) {
                                $error_message = 'Errore nella generazione del token sicuro.';
                                error_log("Change Password Token Generation Error: " . $e->getMessage());
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
            } // Fine controlli password
        } // Fine if ($recaptcha_valid)
    } // Fine if(isset($_POST['update']))
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
        <!-- Script API reCAPTCHA -->
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
        <script language="javascript" type="text/javascript">
            function valid()
            {
                if(document.changepassword.newpassword.value !== document.changepassword.confirmpassword.value)
                {
                    alert("La nuova password e la conferma non corrispondono!");
                    document.changepassword.confirmpassword.focus();
                    return false;
                }
                // Aggiungi qui altri controlli JS se vuoi
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
                            <?php if (empty($success_message) || strpos($success_message, 'Controlla la tua email') === false): ?>
                                <form method="post" name="changepassword" onSubmit="return valid();">
                                    <div class="card-body">
                                        <table class="table table-bordered">
                                           <tr>
                                            <th>Password Corrente</th>
                                               <td><input class="form-control" id="currentpassword" name="currentpassword" type="password" value="<?php echo htmlspecialchars($_POST['currentpassword'] ?? ''); ?>" required /></td>
                                           </tr>
                                           <tr>
                                               <th>Nuova Password</th>
                                               <td><input class="form-control" id="newpassword" name="newpassword" type="password" value="<?php echo htmlspecialchars($_POST['newpassword'] ?? ''); ?>" required /></td>
                                           </tr>
                                           <tr>
                                               <th>Conferma Nuova Password</th>
                                               <td><input class="form-control" id="confirmpassword" name="confirmpassword" type="password" value="<?php echo htmlspecialchars($_POST['confirmpassword'] ?? ''); ?>" required /></td>
                                           </tr>
                                           <!-- Riga per reCAPTCHA -->
                                           <tr>
                                               <td colspan="2" style="text-align:center;">
                                                   <div class="g-recaptcha d-inline-block" data-sitekey="<?php echo RECAPTCHA_SITE_KEY; // Usa la costante da config.php ?>"></div>
                                               </td>
                                           </tr>
                                           <tr>
                                               <td colspan="2" style="text-align:center ;"><button type="submit" class="btn btn-primary btn-block" name="update">Avvia Modifica</button></td>
                                           </tr>
                                        </tbody>
                                        </table>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </main>
                <?php include('includes/footer.php');?>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
        <script src="js/scripts.js"></script>
    </body>
</html>
<?php } ?>
