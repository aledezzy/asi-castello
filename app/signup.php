<?php session_start();
require_once 'includes/config.php'; // Assicurati che config.php definisca le costanti RECAPTCHA e le credenziali SMTP
// Includi PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php'; // Assicurati che il percorso sia corretto

//Codice per la Registrazione
if(isset($_POST['submit']))
{
    // --- INIZIO VERIFICA reCAPTCHA ---
    $recaptcha_response = $_POST['g-recaptcha-response'] ?? null;
    $recaptcha_valid = false;
    $error_message = ''; // Variabile per messaggi di errore specifici del captcha

    if ($recaptcha_response) {
        $recaptcha_url = 'https://www.google.com/recaptcha/api/siteverify';
        $recaptcha_data = [
            'secret'   => RECAPTCHA_SECRET_KEY,
            'response' => $recaptcha_response,
            'remoteip' => $_SERVER['REMOTE_ADDR']
        ];

        $options = [
            'http' => [
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($recaptcha_data),
            ],
        ];
        $context  = stream_context_create($options);
        $verify_response = @file_get_contents($recaptcha_url, false, $context); // Usa @ per sopprimere warning se la richiesta fallisce
        $response_data = $verify_response ? json_decode($verify_response) : null;

        if ($response_data && $response_data->success) {
            $recaptcha_valid = true;
        } else {
            if (isset($response_data->{'error-codes'})) {
                error_log("Verifica reCAPTCHA fallita (Registrazione): " . implode(', ', $response_data->{'error-codes'}));
            } else {
                 error_log("Verifica reCAPTCHA fallita (Registrazione): Risposta API non valida o errore connessione.");
            }
            $error_message = "Verifica CAPTCHA fallita. Riprova.";
        }
    } else {
        $error_message = "Per favore, completa la verifica CAPTCHA.";
    }
    // --- FINE VERIFICA reCAPTCHA ---

    // Procedi solo se il CAPTCHA è valido
    if ($recaptcha_valid) {
        $fname = trim($_POST['fname']); // Trim whitespace
        $lname = trim($_POST['lname']);
        $email = trim($_POST['email']);
        $password = $_POST['password']; // Non fare trim sulla password
        $contact = trim($_POST['contact']);

        // --- Validazione aggiuntiva (opzionale ma consigliata) ---
        if (empty($fname) || empty($lname) || empty($email) || empty($password) || empty($contact)) {
             $error_message = "Tutti i campi sono obbligatori.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
             $error_message = "Formato email non valido.";
        } elseif (!preg_match('/^[0-9]{10}$/', $contact)) { // Assumendo 10 cifre per contatto
             $error_message = "Il numero di contatto deve essere di 10 cifre.";
        } elseif (!preg_match('/^(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{6,}$/', $password)) {
             $error_message = "La password deve contenere almeno un numero, una lettera maiuscola e minuscola, e avere almeno 6 caratteri.";
        }
        // --- Fine Validazione Aggiuntiva ---

        // Procedi solo se non ci sono errori di validazione
        if (empty($error_message)) {
            // --- Controlla se l'email esiste già usando statement preparati ---
            $stmt_check = mysqli_prepare($con, "SELECT id FROM users WHERE email=?");
            if ($stmt_check) {
                mysqli_stmt_bind_param($stmt_check, "s", $email);
                mysqli_stmt_execute($stmt_check);
                mysqli_stmt_store_result($stmt_check);
                $row_count = mysqli_stmt_num_rows($stmt_check);
                mysqli_stmt_close($stmt_check);

                if($row_count > 0)
                {
                    // Usa $error_message invece di echo diretto
                    $error_message = 'Email già in uso, riprova con un\'altra mail o fai il login.';
                } else {
                    // --- Email non esiste, procedi con la registrazione ---
                    try {
                        // --- Genera Token di Attivazione ---
                        $activation_token_raw = bin2hex(random_bytes(32));
                        $activation_token_hash = hash('sha256', $activation_token_raw);
                        $is_active = 0; // Imposta utente come INATTIVO

                        // --- Hash della password ---
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                        // --- Inserisci nuovo utente (INATTIVO) con token ---
                        $stmt_insert = mysqli_prepare($con,
                            "INSERT INTO users(fname, lname, email, password, contactno, activation_token, is_active)
                             VALUES (?, ?, ?, ?, ?, ?, ?)"
                        );
                        if ($stmt_insert) {
                            mysqli_stmt_bind_param($stmt_insert, "ssssssi", // sssss + stringa (hash) + intero (is_active)
                                $fname,
                                $lname,
                                $email,
                                $hashed_password,
                                $contact,
                                $activation_token_hash, // Salva l'HASH
                                $is_active // Salva 0
                            );
                            $success = mysqli_stmt_execute($stmt_insert);
                            $new_user_id = mysqli_insert_id($con); // Ottieni ID nuovo utente
                            mysqli_stmt_close($stmt_insert);

                            if($success)
                            {
                                // --- Invia Email di Attivazione ---
                                $mail = new PHPMailer(true);
                                try {
                                    // Impostazioni Server (DA CONFIGURARE IN config.php o qui)
                                    $mail->isSMTP();
                                    $mail->Host = SMTP_HOST;
                                    $mail->SMTPAuth = true;
                                    $mail->Username = SMTP_USER;
                                    $mail->Password = SMTP_PASS;
                                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                                    $mail->Port = SMTP_PORT;
                                    $mail->CharSet = 'UTF-8';

                                    // Mittente e Destinatario
                                    $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME); // 'noreply@tuosito.com', 'Nome Tuo Sito'
                                    $mail->addAddress($email, $fname . ' ' . $lname);

                                    // Contenuto
                                    $mail->isHTML(true);
                                    $mail->Subject = 'Attiva il tuo account su ' . MAIL_FROM_NAME;

                                    // --- MODIFICA QUESTO URL BASE ---
                                    $activationLinkBase = APP_BASE_URL; // Cambia con il tuo URL reale!
                                    // --- ------------------------ ---
                                    $activationLink = $activationLinkBase . "activate-account.php?token=" . urlencode($activation_token_raw); // Usa il token RAW nel link

                                    $bodyContent = "Gentile " . htmlspecialchars($fname) . ",<br><br>";
                                    $bodyContent .= "Grazie per esserti registrato! Per completare la registrazione, clicca sul seguente link per attivare il tuo account:<br>";
                                    $bodyContent .= '<a href="' . $activationLink . '">' . $activationLink . '</a><br><br>';
                                    $bodyContent .= "Se non hai richiesto tu questa registrazione, ignora questa email.<br><br>";
                                    $bodyContent .= "Cordiali saluti,<br>Il Team di " . MAIL_FROM_NAME;

                                    $mail->Body = $bodyContent;
                                    $mail->AltBody = "Gentile " . htmlspecialchars($fname) . ",\n\nGrazie per esserti registrato! Per completare la registrazione, visita il seguente link:\n" . $activationLink . "\n\nSe non hai richiesto tu questa registrazione, ignora questa email.\n\nCordiali saluti,\nIl Team di " . MAIL_FROM_NAME;

                                    $mail->send();

                                    // Messaggio di successo per l'utente
                                    echo "<script>alert('Registrazione quasi completata! Controlla la tua email (".htmlspecialchars($email).") per il link di attivazione.');</script>";
                                    // Opzionale: reindirizza a una pagina di "Controlla Email" invece di mostrare il form di nuovo
                                    // header("Location: check-email.php");
                                    // exit();
                                    // Per ora, svuotiamo i campi POST per non ripopolare il form
                                     $_POST = array();


                                } catch (Exception $e) {
                                    // Errore invio email: Logga l'errore, informa l'utente (ma la registrazione DB è avvenuta)
                                    error_log("Mailer Error [Account Activation] per user ID $new_user_id: " . $mail->ErrorInfo);
                                    // Potresti voler cancellare l'utente o marcarlo per un reinvio manuale
                                    // mysqli_query($con, "DELETE FROM users WHERE id = $new_user_id"); // Drastico
                                    $error_message = "Registrazione avvenuta, ma errore nell'invio dell'email di attivazione. Contatta l'assistenza.";
                                }
                                // --- Fine Invio Email ---

                            } else {
                                 $error_message = 'Errore durante la registrazione. Riprova.';
                                 error_log("Errore Registrazione (Execute Insert): " . mysqli_error($con));
                            }
                        } else {
                             $error_message = 'Errore nella preparazione della query di inserimento. Riprova più tardi.';
                             error_log("Errore Preparazione Inserimento Registrazione: " . mysqli_error($con));
                        }
                    } catch (Exception $e) {
                         $error_message = 'Errore nella generazione del token di attivazione.';
                         error_log("Errore Generazione Token Attivazione: " . $e->getMessage());
                    }
                }
            } else {
                $error_message = 'Errore nella preparazione della query di controllo. Riprova più tardi.';
                error_log("Errore Preparazione Controllo Registrazione: " . mysqli_error($con));
            }
        } // Fine if(empty($error_message)) per validazione

    } else {
        // Se il captcha non è valido, $error_message è già impostato
        // Mostra l'errore captcha (o altri errori) dopo il form
    }
} // Fine if(isset($_POST['submit']))
?>
<!DOCTYPE html>
<html lang="it">
    <head>
        <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="description" content="Registrazione Utente" />
        <meta name="author" content="" />
        <title>Registrazione Utente | Sistema di Registrazione e Login</title>
        <link href="css/styles.css" rel="stylesheet" />
        <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/js/all.min.js" crossorigin="anonymous"></script>
        <!-- Script API reCAPTCHA -->
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
        <script type="text/javascript">
            function checkpass()
            {
                const password = document.signup.password.value;
                const confirmPassword = document.signup.confirmpassword.value;
                const passwordPattern = /^(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{6,}$/;

                if(password !== confirmPassword)
                {
                    alert('Il campo Password e Conferma Password non corrispondono!');
                    document.signup.confirmpassword.focus();
                    return false;
                }
                if (!passwordPattern.test(password)) {
                   alert('La password deve contenere almeno un numero, una lettera maiuscola e minuscola, e avere almeno 6 caratteri.');
                   document.signup.password.focus();
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
                            <div class="col-lg-7">
                                <div class="card shadow-lg border-0 rounded-lg mt-5">
                                    <div class="card-header">
                                        <h2>Registrazione e Login</h2>
                                        <hr />
                                        <h3 class="text-center font-weight-light my-4">Crea account</h3>
                                    </div>
                                    <div class="card-body">

                                        <?php if (!empty($error_message)): ?>
                                            <div class="alert alert-danger" role="alert">
                                                <?php echo htmlspecialchars($error_message); ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php
                                            // Mostra messaggio successo specifico se l'email è stata inviata
                                            if (isset($success) && $success && empty($error_message) && isset($mail) && $mail->isError()) {
                                                echo '<div class="alert alert-success" role="alert">Registrazione quasi completata! Controlla la tua email ('.htmlspecialchars($email).') per il link di attivazione.</div>';
                                            }
                                        ?>


                                        <form method="post" name="signup" onsubmit="return checkpass();">

                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <div class="form-floating mb-3 mb-md-0">
                                                        <input class="form-control" id="fname" name="fname" type="text" placeholder="Inserisci il tuo nome" value="<?php echo htmlspecialchars($_POST['fname'] ?? ''); ?>" required />
                                                        <label for="inputFirstName">Nome</label>
                                                    </div>
                                                </div>

                                                <div class="col-md-6">
                                                    <div class="form-floating">
                                                        <input class="form-control" id="lname" name="lname" type="text" placeholder="Inserisci il tuo cognome" value="<?php echo htmlspecialchars($_POST['lname'] ?? ''); ?>" required />
                                                        <label for="inputLastName">Cognome</label>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="form-floating mb-3">
                                                <input class="form-control" id="email" name="email" type="email" placeholder="Inserisci la tua email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required />
                                                <label for="inputEmail">Indirizzo email</label>
                                            </div>

                                            <div class="form-floating mb-3">
                                                <input class="form-control" id="contact" name="contact" type="text" placeholder="Inserisci il tuo numero di contatto" value="<?php echo htmlspecialchars($_POST['contact'] ?? ''); ?>" required pattern="[0-9]{10}" title="Solo 10 caratteri numerici" maxlength="10" />
                                                <label for="inputcontact">Numero di contatto</label>
                                            </div>

                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <div class="form-floating mb-3 mb-md-0">
                                                        <input class="form-control" id="password" name="password" type="password" placeholder="Crea una password" pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{6,}" title="Almeno un numero, una lettera maiuscola e minuscola, e almeno 6 caratteri" required/>
                                                        <label for="inputPassword">Password</label>
                                                    </div>
                                                </div>

                                                <div class="col-md-6">
                                                    <div class="form-floating mb-3 mb-md-0">
                                                        <input class="form-control" id="confirmpassword" name="confirmpassword" type="password" placeholder="Conferma la password" required />
                                                        <label for="inputPasswordConfirm">Conferma Password</label>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Widget reCAPTCHA -->
                                            <div class="mb-3 d-flex justify-content-center">
                                                <div class="g-recaptcha" data-sitekey="<?php echo RECAPTCHA_SITE_KEY; ?>"></div>
                                            </div>

                                            <div class="mt-4 mb-0">
                                                <div class="d-grid"><button type="submit" class="btn btn-primary btn-block" name="submit">Crea Account</button></div>
                                            </div>
                                        </form>
                                    </div>
                                    <div class="card-footer text-center py-3">
                                        <div class="small"><a href="login.php">Hai già un account? Vai al login</a></div>
                                        <div class="small"><a href="index.php">Torna alla Home</a></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
            <?php include_once 'includes/footer.php';?>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
        <script src="js/scripts.js"></script>
    </body>
</html>
