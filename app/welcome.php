<?php session_start();
include_once 'includes/config.php';


if (!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit;
} else {
    // Il controllo strlen($_SESSION['id']==0) era ridondante e errato.
    // if (empty($_SESSION['id'])) { // Questo sarebbe un controllo più corretto se il primo non ci fosse
    //   header('location:logout.php');
    //   exit();
    // }
    
    
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="description" content="" />
        <meta name="author" content="" />
        <title>Dashboard</title>
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@latest/dist/style.css" rel="stylesheet" />
        <link href="css/styles.css" rel="stylesheet" />
        <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/js/all.min.js" crossorigin="anonymous"></script>
    </head>
    <body class="sb-nav-fixed">
      <?php include_once 'includes/navbar.php';?>
        <div id="layoutSidenav">
          <?php include_once 'includes/sidebar.php';?>
            <div id="layoutSidenav_content">
                <main>
                    <div class="container-fluid px-4">
                        <h1 class="mt-4">Pannello utente</h1>
                        <hr />
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item active">Dashboard</li>
                        </ol>

<?php
$userid = $_SESSION['id'];
$userData = null; // Variabile per contenere i dati dell'utente

// Query per recuperare dati utente e dati socio (se presenti) usando prepared statement
$sql_user_socio = "SELECT u.id, u.fname, u.lname, u.id_socio, s.tessera_club_scadenza, s.tessera_club_numero
                   FROM users u
                   LEFT JOIN soci s ON u.id_socio = s.id
                   WHERE u.id = ?";

$stmt = mysqli_prepare($con, $sql_user_socio);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $userid);
    mysqli_stmt_execute($stmt);
    $query_exec_result = mysqli_stmt_get_result($stmt);
    $userData = mysqli_fetch_assoc($query_exec_result); // Usa fetch_assoc
    mysqli_stmt_close($stmt);
} else {
    error_log("Errore preparazione query welcome.php: " . mysqli_error($con));
    // Potresti voler mostrare un messaggio di errore generico all'utente
}

// --- Recupero Prossima Manifestazione ---
$prossima_manifestazione = null;
$sql_prossima_manif = "SELECT id, titolo, data_inizio
                       FROM manifestazioni
                       WHERE data_inizio >= NOW()
                       ORDER BY data_inizio ASC
                       LIMIT 1";
$result_prossima_manif = mysqli_query($con, $sql_prossima_manif);
if ($result_prossima_manif && mysqli_num_rows($result_prossima_manif) > 0) {
    $prossima_manifestazione = mysqli_fetch_assoc($result_prossima_manif);
} elseif (!$result_prossima_manif) {
    error_log("Errore query prossima manifestazione: " . mysqli_error($con));
}
// Non chiudiamo la connessione $con qui, verrà chiusa alla fine dello script se necessario o da altri include.

if ($userData) { // Controlla se i dati dell'utente sono stati caricati
?>
                        <div class="row" >
                            <div class="col-xl-5 col-md-6" >
                                <div class="card bg-primary text-white mb-4">
                                    <div class="card-body">Bentornato   <?php echo htmlspecialchars($userData['fname']).' '.htmlspecialchars($userData['lname']);?></div>
                                    <div class="card-footer d-flex align-items-center justify-content-between">
                                        <a class="small text-white stretched-link" href="profile.php">Visualizza Profilo</a>
                                        <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                                    </div>
                                </div>

                                
                            </div>
                        

                        <!-- Card uguale a quella sopra, messa di fianco, con link per iscriversi alle manifestazioni disponibili-->
                        <div class="col-xl-5 col-md-6" >
                                <div class="card bg-primary text-white mb-4">
                                    <div class="card-body">Iscriviti alle manifestazioni disponibili</div>
                                    <div class="card-footer d-flex align-items-center justify-content-between">
                                        <a class="small text-white stretched-link" href="manifestazioni.php">Visualizza Manifestazioni</a>
                                        <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-5 col-md-6" >
                                <div class="card bg-warning text-white mb-4">
                                    <div class="card-body">Visualizza le tue iscrizioni</div>
                                    <div class="card-footer d-flex align-items-center justify-content-between">
                                        <a class="small text-white stretched-link" href="iscrizioni.php">Visualizza Iscrizioni</a>
                                        <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                                    </div>
                                </div>
                            </div>
                    
                            <div class="col-xl-5 col-md-6">
                                <div class="card bg-warning text-white mb-4">
                                    <div class="card-body">Gestione Auto</div>
                                    <div class="card-footer d-flex align-items-center justify-content-between">
                                        <a class="small text-white stretched-link" href="gestisci-auto.php">Visualizza dettagli</a>
                                        <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-xl-5 col-md-6" >
                                <div class="card bg-primary text-white mb-4">
                                    <div class="card-body">Diventa socio</div>
                                    <div class="card-footer d-flex align-items-center justify-content-between">
                                        <a class="small text-white stretched-link" href="diventa-socio.php">Visualizza dettagli</a>
                                        <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                                    </div>
                                </div>
                            </div>

                            <?php // Card per Scadenza Tessera ?>
                            <?php if (!empty($userData['id_socio']) && !empty($userData['tessera_club_scadenza'])): ?>
                            <div class="col-xl-5 col-md-6">
                                <div class="card bg-info text-white mb-4">
                                    <div class="card-body">
                                        Scadenza Tessera Club:
                                        <strong><?php echo date("d/m/Y", strtotime($userData['tessera_club_scadenza'])); ?></strong>
                                        <?php if (!empty($userData['tessera_club_numero'])): ?>
                                            <br><small>(Numero: <?php echo htmlspecialchars($userData['tessera_club_numero']); ?>)</small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-footer d-flex align-items-center justify-content-between">
                                        <a class="small text-white stretched-link" href="profile.php">Dettagli Socio</a>
                                        <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php // Card per Prossima Manifestazione ?>
                            <?php if ($prossima_manifestazione): ?>
                            <div class="col-xl-5 col-md-6">
                                <div class="card bg-success text-white mb-4">
                                    <div class="card-body">
                                        Prossima Manifestazione:
                                        <h5 class="mt-1 mb-0"><?php echo htmlspecialchars($prossima_manifestazione['titolo']); ?></h5>
                                        <small>Data: <?php echo date("d/m/Y H:i", strtotime($prossima_manifestazione['data_inizio'])); ?></small>
                                    </div>
                                    <div class="card-footer d-flex align-items-center justify-content-between">
                                        <a class="small text-white stretched-link" href="manifestazioni.php#form-iscrizione-<?php echo $prossima_manifestazione['id']; ?>">Vedi Dettagli e Iscriviti</a>
                                        <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                        </div>
              
<?php
} else {
    echo '<div class="alert alert-danger">Errore nel caricamento dei dati utente.</div>';
} ?>
                    </div>
                </main>
          <?php include 'includes/footer.php';?>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
        <script src="js/scripts.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.8.0/Chart.min.js" crossorigin="anonymous"></script>
        <script src="assets/demo/chart-area-demo.js"></script>
        <script src="assets/demo/chart-bar-demo.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/simple-datatables@latest" crossorigin="anonymous"></script>
        <script src="js/datatables-simple-demo.js"></script>
    </body>
</html>
<?php } ?>
