<?php
// Non avviare la sessione qui se non è strettamente necessario per questa pagina
// session_start();
include 'includes/config.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

$message = ''; // Per mostrare messaggi all'utente
$message_type = 'info'; // Può essere 'info', 'success', 'danger'

if (isset($_POST['send'])) {
    $femail = trim($_POST['femail']);

    if (empty($femail) || !filter_var($femail, FILTER_VALIDATE_EMAIL)) {
        $message = 'Per favore, inserisci un indirizzo email valido.';
        $message_type = 'danger';
    } else {
        // 1. Verifica se l'email esiste
        $stmt_fetch = mysqli_prepare($con, "SELECT id, fname FROM users WHERE email = ?");
        if ($stmt_fetch) {
            mysqli_stmt_bind_param($stmt_fetch, "s", $femail);
            mysqli_stmt_execute($stmt_fetch);
            $result = mysqli_stmt_get_result($stmt_fetch);
            $user = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt_fetch);

            if ($user) {
                // 2. Genera un token sicuro e univoco
                try {
                    $token = bin2hex(random_bytes(32)); // Token grezzo
                    $tokenHash = hash('sha256', $token); // Hash del token per lo storage
                    $expiry = date("Y-m-d H:i:s", time() + 3600); // Scadenza tra 1 ora
                    $userId = $user['id'];
                    $fname = $user['fname'];

                    // 3. Salva l'hash del token e la scadenza nel database
                    $stmt_update = mysqli_prepare($con, "UPDATE users SET reset_token_hash = ?, reset_token_expires_at = ? WHERE id = ?");
                    if ($stmt_update) {
                        mysqli_stmt_bind_param($stmt_update, "ssi", $tokenHash, $expiry, $userId);
                        if (mysqli_stmt_execute($stmt_update)) {
                            mysqli_stmt_close($stmt_update);

                            // 4. Invia l'email con il link di reset (contenente il token NON hashato)
                            $mail = new PHPMailer(true); // Pass true per abilitare le eccezioni
                            try {
                                // Impostazioni Server SMTP
                                $mail->isSMTP();
                                $mail->Host = SMTP_HOST;
                                $mail->SMTPAuth = true;
                                $mail->Username = SMTP_USER;
                                $mail->Password = SMTP_PASS;
                                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                                $mail->Port = SMTP_PORT;
                                $mail->CharSet = 'UTF-8';


                                // Mittente e Destinatario
                                // --- USA IL TUO INDIRIZZO E NOME QUI ---
                                $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME); // Sostituisci con il tuo nome e email
                                // --- ----------------------------- ---
                                $mail->addAddress($femail, $fname); // Aggiungi destinatario

                                // Contenuto
                                $mail->isHTML(true);
                                $mail->Subject = 'Richiesta di Reset Password';

                                // --- MODIFICA QUESTO URL BASE ---
                                $resetLinkBase = APP_BASE_URL; // Cambia con il tuo URL reale!
                                // --- ------------------------ ---
                                $resetLink = $resetLinkBase . "reset-password.php?token=" . urlencode($token) . "&email=" . urlencode($femail);

                                $bodyContent = "Gentile " . htmlspecialchars($fname) . ",<br><br>";
                                $bodyContent .= "Abbiamo ricevuto una richiesta di reset della password per il tuo account.<br>";
                                $bodyContent .= "Clicca sul seguente link per impostare una nuova password:<br>";
                                $bodyContent .= '<a href="' . $resetLink . '">' . $resetLink . '</a><br><br>';
                                $bodyContent .= "Se non hai richiesto tu il reset, ignora questa email.<br>";
                                $bodyContent .= "Questo link scadrà tra 1 ora.<br><br>";
                                $bodyContent .= "Cordiali saluti,<br>Il Team";

                                $mail->Body = $bodyContent;
                                $mail->AltBody = "Gentile " . htmlspecialchars($fname) . ",\n\nAbbiamo ricevuto una richiesta di reset della password per il tuo account.\nCopia e incolla il seguente link nel tuo browser per impostare una nuova password:\n" . $resetLink . "\n\nSe non hai richiesto tu il reset, ignora questa email.\nQuesto link scadrà tra 1 ora.\n\nCordiali saluti,\nIl Team"; // Corpo alternativo per client non HTML

                                $mail->send();
                                $message = 'Se esiste un account associato a ' . htmlspecialchars($femail) . ', abbiamo inviato un link per il reset della password.';
                                $message_type = 'success';

                            } catch (Exception $e) {
                                $message = "Il messaggio non può essere inviato. Errore Mailer: {$mail->ErrorInfo}";
                                $message_type = 'danger';
                                // Logga l'errore per debug interno invece di mostrarlo all'utente
                                error_log("Mailer Error [Password Recovery]: " . $mail->ErrorInfo);
                            }
                        } else {
                            mysqli_stmt_close($stmt_update);
                            $message = 'Errore durante l\'aggiornamento del token di reset.';
                            $message_type = 'danger';
                            error_log("Password Recovery Token Update Error: " . mysqli_error($con));
                        }
                    } else {
                        $message = 'Errore nella preparazione dell\'aggiornamento del token.';
                        $message_type = 'danger';
                        error_log("Password Recovery Prepare Update Error: " . mysqli_error($con));
                    }
                } catch (Exception $e) {
                    $message = 'Errore nella generazione del token sicuro.';
                    $message_type = 'danger';
                    error_log("Password Recovery Token Generation Error: " . $e->getMessage());
                }
            } else {
                // Email non trovata - Mostra lo stesso messaggio generico per non rivelare quali email esistono
                $message = 'Se esiste un account associato a ' . htmlspecialchars($femail) . ', abbiamo inviato un link per il reset della password.';
                $message_type = 'success'; // O 'info' se preferisci
            }
        } else {
            $message = 'Errore nella preparazione della query di verifica email.';
            $message_type = 'danger';
            error_log("Password Recovery Prepare Check Error: " . mysqli_error($con));
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
        <meta name="description" content="Recupero Password" />
        <meta name="author" content="" />
        <title>Recupero Password | Sistema di Registrazione e Login</title>
        <link href="css/styles.css" rel="stylesheet" />
        <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/js/all.min.js" crossorigin="anonymous"></script>
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
                                        <h2>Sistema di Registrazione e Login</h2>
                                        <hr />
                                        <h3 class="text-center font-weight-light my-4">Recupero Password</h3>
                                    </div>
                                    <div class="card-body">

                                        <?php if (!empty($message)): ?>
                                            <div class="alert alert-<?php echo $message_type; ?>" role="alert">
                                                <?php echo $message; ?>
                                            </div>
                                        <?php endif; ?>

                                        <div class="small mb-3 text-muted">Inserisci il tuo indirizzo email. Se l'account esiste, ti invieremo un link per reimpostare la password.</div>
                                        <form method="post">
                                            <div class="form-floating mb-3">
                                                <input class="form-control" id="inputEmail" name="femail" type="email" placeholder="nome@esempio.com" required />
                                                <label for="inputEmail">Indirizzo email</label>
                                            </div>
                                            <div class="d-flex align-items-center justify-content-between mt-4 mb-0">
                                                <a class="small" href="login.php">Torna al login</a>
                                                <button class="btn btn-primary" type="submit" name="send">Invia Link Reset</button>
                                            </div>
                                        </form>
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
       <?php include 'includes/footer.php';?>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
        <script src="js/scripts.js"></script>
    </body>
</html>
