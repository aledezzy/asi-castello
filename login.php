<?php session_start();

// Code for login
include 'includes/config.php';
if(isset($_POST['login']))
{
    $useremail = $_POST['uemail'];
    $password_from_form = $_POST['password']; // Password entered by user

    // Use prepared statement to prevent SQL injection
    // Select the hashed password along with id and fname
    $stmt = mysqli_prepare($con, "SELECT id, fname, password FROM users WHERE email=?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $useremail);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user_data = mysqli_fetch_assoc($result); // Use fetch_assoc for clarity

        // Verify the password
        if($user_data && password_verify($password_from_form, $user_data['password']))
        {
            // Password is correct, set session variables
            $_SESSION['id'] = $user_data['id'];
            $_SESSION['name'] = $user_data['fname'];
            header("location:welcome.php");
            exit(); // Add exit after redirect
        }
        else
        {
            // Invalid email or password
            echo "<script>alert('Username o password sbagliate');</script>";
        }
        mysqli_stmt_close($stmt);
    } else {
        echo "<script>alert('Errore nella preparazione della query di login. Riprova più tardi.');</script>";
        // Log the error (optional)
        // error_log("Login Prepare Error: " . mysqli_error($con));
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
        <title>User Login</title>
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
                                        <h2 align="center">Login Asi</h2>
                                        <hr />
                                        <h3 class="text-center font-weight-light my-4">Login utente</h3>
                                    </div>
                                    <div class="card-body">

                                        <form method="post">

                                            <div class="form-floating mb-3">
                                                <input class="form-control" name="uemail" type="email" placeholder="inserisci la tua email" required/>
                                                <label for="inputEmail">Indirizzo email</label>
                                            </div>

                                            <div class="form-floating mb-3">
                                                <input class="form-control" name="password" type="password" placeholder="Password" required />
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
    </body>
</html>
