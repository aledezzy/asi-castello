<?php session_start();
require_once('includes/config.php');

//Code for Registration
if(isset($_POST['submit']))
{
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
                    // Redirect using PHP header is generally preferred over JS for this
                    header("Location: login.php");
                    exit(); // Important: stop script execution after redirect
                    // echo "<script type='text/javascript'> document.location = 'login.php'; </script>";
                } else {
                     // Provide a more generic error for the user
                     echo "<script>alert('Errore durante la registrazione. Riprova.');</script>";
                     // Log the detailed error for debugging (optional)
                     // error_log("Signup Error: " . mysqli_error($con));
                }
            } else {
                 echo "<script>alert('Errore nella preparazione della query di inserimento. Riprova più tardi.');</script>";
                 // Log the error (optional)
                 // error_log("Signup Prepare Insert Error: " . mysqli_error($con));
            }
        }
    } else {
        echo "<script>alert('Errore nella preparazione della query di controllo. Riprova più tardi.');</script>";
        // Log the error (optional)
        // error_log("Signup Prepare Check Error: " . mysqli_error($con));
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
        <script type="text/javascript">
            function checkpass()
            {
                // Basic client-side check (server-side validation is still essential)
                if(document.signup.password.value != document.signup.confirmpassword.value)
                {
                    alert('Password and Confirm Password field does not match');
                    document.signup.confirmpassword.focus();
                    return false;
                }
                // You might add checks for the pattern here too, but server-side is more reliable
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
                                                        <input class="form-control" id="fname" name="fname" type="text" placeholder="Inserisci il tuo nome" required />
                                                        <label for="inputFirstName">Nome</label>
                                                    </div>
                                                </div>

                                                <div class="col-md-6">
                                                    <div class="form-floating">
                                                        <input class="form-control" id="lname" name="lname" type="text" placeholder="Inserisci il tuo cognome" required />
                                                        <label for="inputLastName">Cognome</label>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="form-floating mb-3">
                                                <input class="form-control" id="email" name="email" type="email" placeholder="Inserisci la tua email" required />
                                                <label for="inputEmail">Indirizzo email</label>
                                            </div>

                                            <div class="form-floating mb-3">
                                                <input class="form-control" id="contact" name="contact" type="text" placeholder="Inserisci il tuo numero di contatto" required pattern="[0-9]{10}" title="10 caratteri numerici solo" maxlength="10" />
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

