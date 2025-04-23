<?php session_start();
include_once('../includes/config.php');

// Code for login
if(isset($_POST['login']))
{
    $adminusername = $_POST['username'];
    $password_from_form = $_POST['password']; // Get plain password from form

    // Use prepared statement to prevent SQL injection
    $stmt = mysqli_prepare($con, "SELECT id, password FROM admin WHERE username = ?");

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $adminusername);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $admin_data = mysqli_fetch_assoc($result); // Fetch the admin data
        mysqli_stmt_close($stmt); // Close the statement

        // Verify the password using password_verify
        // Check if admin_data was found AND if the password matches the hash
        if ($admin_data && password_verify($password_from_form, $admin_data['password']))
        {
            // Password is correct, set session variables
            $_SESSION['login'] = $adminusername; // Store username in session
            $_SESSION['adminid'] = $admin_data['id']; // Store admin ID in session

            // Redirect to dashboard
            header("Location: dashboard.php"); // Use PHP header for redirection
            exit(); // Important: stop script execution after redirect
        }
        else
        {
            // Invalid username or password
            echo "<script>alert('Username o password non validi');</script>";
            // It's generally better not to redirect immediately after a failed login attempt
            // Let the user see the form again.
            // echo "<script>window.location.href='index.php'</script>";
            // exit();
        }
    } else {
        // Error preparing the statement
        echo "<script>alert('Errore del database. Riprova più tardi.');</script>";
        error_log("Admin Login Prepare Error: " . mysqli_error($con)); // Log the error
    }
}
?>

<!DOCTYPE html>
<html lang="it"> 
    <head>
        <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="description" content="Accesso Area Riservata Amministratori" />
        <meta name="author" content="" />
        <title>Accesso Admin | Asi</title>
        <link href="../css/styles.css" rel="stylesheet" />
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
                                        <h2 align="center">Accesso Admin | Asi</h2>
                                        <hr />
                                        <h3 class="text-center font-weight-light my-4">Accesso Admin</h3>
                                    </div>
                                    <div class="card-body">

                                        <form method="post">

                                            <div class="form-floating mb-3">

                                                <input class="form-control" name="username" type="text" placeholder="Username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required/>
                                                <label for="inputEmail">Username</label>
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
                                        <div class="small"><a href="../index.php">Torna alla home</a></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
            <?php include('../includes/footer.php');?>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
        <script src="../js/scripts.js"></script>
    </body>
</html>
