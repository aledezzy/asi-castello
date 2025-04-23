<?php session_start();
require_once('includes/config.php'); // Assicurati che config.php definisca le costanti RECAPTCHA

//Code for Registration
if(isset($_POST['submit']))
{
    // --- INIZIO VERIFICA reCAPTCHA ---
    $recaptcha_response = $_POST['g-recaptcha-response'] ?? null;
    $recaptcha_valid = false;
    $error_message = ''; // Variabile per messaggi di errore specifici del captcha

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
                error_log("reCAPTCHA verification failed (Signup): " . implode(', ', $response_data->{'error-codes'}));
            }
            $error_message = "Verifica CAPTCHA fallita. Riprova.";
        }
    } else {
        $error_message = "Per favore, completa la verifica CAPTCHA.";
    }
    // --- FINE VERIFICA reCAPTCHA ---

    // Procedi solo se il CAPTCHA è valido
    if ($recaptcha_valid) {
        $fname=$_POST['fname'];
        $lname=$_POST['lname'];
        $email=$_POST['email'];
        $password=$_POST['password'];
        $contact=$_POST['contact'];

        // --- Check if email already exists using prepared statement ---
        $stmt_check = mysqli_prepare($con, "SELECT id FROM users WHERE email=?");
        if ($stmt_check) {
            mysqli_stmt_bind_param($stmt_check, "s", $email);
            mysqli_stmt_execute($stmt_check);
            mysqli_stmt_store_result($stmt_check); // Store result to check num_rows
            $row_count = mysqli_stmt_num_rows($stmt_check);
            mysqli_stmt_close($stmt_check); // Close the statement

            if($row_count > 0)
            {
                echo "<script>alert('Email già in uso, riprova con un'altra mail o fai il login');</script>";
            } else {
                // --- Hash the password ---
                $hashed_password = password_hash($password, PASSWORD_DEFAULT); // Use default strong hashing algorithm

                // --- Insert new user using prepared statement ---
                $stmt_insert = mysqli_prepare($con, "INSERT INTO users(fname, lname, email, password, contactno) VALUES (?, ?, ?, ?, ?)");
                if ($stmt_insert) {
                    mysqli_stmt_bind_param($stmt_insert, "sssss", $fname, $lname, $email, $hashed_password, $contact);
                    $success = mysqli_stmt_execute($stmt_insert);
                    mysqli_stmt_close($stmt_insert); // Close the statement

                    if($success)
                    {
                        echo "<script>alert('Registrazione avvenuta con successo');</script>";
                        header("Location: login.php");
                        exit();
                    } else {
                         echo "<script>alert('Errore durante la registrazione. Riprova.');</script>";
                         // error_log("Signup Error: " . mysqli_error($con));
                    }
                } else {
                     echo "<script>alert('Errore nella preparazione della query di inserimento. Riprova più tardi.');</script>";
                     // error_log("Signup Prepare Insert Error: " . mysqli_error($con));
                }
            }
        } else {
            echo "<script>alert('Errore nella preparazione della query di controllo. Riprova più tardi.');</script>";
            // error_log("Signup Prepare Check Error: " . mysqli_error($con));
        }
    } else {
        // Se il captcha non è valido, mostra il messaggio di errore specifico del captcha
        if (!empty($error_message)) {
            echo "<script>alert('".addslashes($error_message)."');</script>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="description" content="" />
        <meta name="author" content="" />
        <title>User Signup | Sistema di Registrazione e Login</title>
        <link href="css/styles.css" rel="stylesheet" />
        <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/js/all.min.js" crossorigin="anonymous"></script>
        <!-- Script API reCAPTCHA -->
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
        <script type="text/javascript">
            function checkpass()
            {
                if(document.signup.password.value != document.signup.confirmpassword.value)
                {
                    alert('Password and Confirm Password field does not match');
                    document.signup.confirmpassword.focus();
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
                                        <h2 align="center">Registrazione e Login</h2>
                                        <hr />
                                        <h3 class="text-center font-weight-light my-4">Crea account</h3>
                                    </div>
                                    <div class="card-body">
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
                                                <input class="form-control" id="contact" name="contact" type="text" placeholder="Inserisci il tuo numero di contatto" value="<?php echo htmlspecialchars($_POST['contact'] ?? ''); ?>" required pattern="[0-9]{10}" title="10 caratteri numerici solo" maxlength="10" />
                                                <label for="inputcontact">Numero di contatto</label>
                                            </div>

                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <div class="form-floating mb-3 mb-md-0">
                                                        <input class="form-control" id="password" name="password" type="password" placeholder="Crea una password" pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{6,}" title="almeno un numero e una lettera maiuscola e minuscola, e almeno 6 o più caratteri" required/>
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
                                                <div class="g-recaptcha" data-sitekey="<?php echo RECAPTCHA_SITE_KEY; // Usa la costante da config.php ?>"></div>
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
            <?php include_once('includes/footer.php');?>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
        <script src="js/scripts.js"></script>
    </body>
</html>