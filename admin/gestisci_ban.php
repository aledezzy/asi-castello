<?php
session_start();
include_once('../includes/config.php');

// Verifica se l'admin è loggato
if (strlen($_SESSION['adminid'] ?? 0) == 0) {
    header('location:logout.php');
    exit();
}

$messaggio = '';
$messaggio_tipo = 'info';

// --- Gestione Rimozione Ban (Email) ---
if (isset($_GET['del_email_id'])) {
    // ... (codice rimozione email invariato) ...
    $email_id_to_delete = filter_input(INPUT_GET, 'del_email_id', FILTER_VALIDATE_INT);
    if ($email_id_to_delete) {
        $stmt_delete_email = mysqli_prepare($con, "DELETE FROM banned_emails WHERE id = ?");
        if ($stmt_delete_email) {
            mysqli_stmt_bind_param($stmt_delete_email, "i", $email_id_to_delete);
            if (mysqli_stmt_execute($stmt_delete_email)) {
                $messaggio = "Ban per l'email rimosso con successo.";
                $messaggio_tipo = 'success';
            } else {
                $messaggio = "Errore durante la rimozione del ban email.";
                $messaggio_tipo = 'danger';
                error_log("Email Ban Deletion Error: " . mysqli_stmt_error($stmt_delete_email));
            }
            mysqli_stmt_close($stmt_delete_email);
        } else {
            $messaggio = "Errore nella preparazione della query di rimozione email.";
            $messaggio_tipo = 'danger';
            error_log("Email Ban Delete Prepare Error: " . mysqli_error($con));
        }
        header("Location: gestisci_ban.php?msg=" . urlencode($messaggio) . "&msg_type=" . $messaggio_tipo);
        exit();
    }
}

// --- Gestione Rimozione Ban (IP) ---
if (isset($_GET['del_ip_id'])) {
    // ... (codice rimozione IP invariato) ...
     $ip_id_to_delete = filter_input(INPUT_GET, 'del_ip_id', FILTER_VALIDATE_INT);
    if ($ip_id_to_delete) {
        $stmt_delete_ip = mysqli_prepare($con, "DELETE FROM banned_ips WHERE id = ?");
        if ($stmt_delete_ip) {
            mysqli_stmt_bind_param($stmt_delete_ip, "i", $ip_id_to_delete);
            if (mysqli_stmt_execute($stmt_delete_ip)) {
                $messaggio = "Ban per l'indirizzo IP rimosso con successo.";
                $messaggio_tipo = 'success';
            } else {
                $messaggio = "Errore durante la rimozione del ban IP.";
                $messaggio_tipo = 'danger';
                error_log("IP Ban Deletion Error: " . mysqli_stmt_error($stmt_delete_ip));
            }
            mysqli_stmt_close($stmt_delete_ip);
        } else {
            $messaggio = "Errore nella preparazione della query di rimozione IP.";
            $messaggio_tipo = 'danger';
            error_log("IP Ban Delete Prepare Error: " . mysqli_error($con));
        }
        header("Location: gestisci_ban.php?msg=" . urlencode($messaggio) . "&msg_type=" . $messaggio_tipo);
        exit();
    }
}

// --- Gestione Aggiunta Ban Email (POST) ---
if (isset($_POST['ban_email_submit'])) {
    $email_to_ban = trim(filter_input(INPUT_POST, 'email_to_ban', FILTER_VALIDATE_EMAIL));
    $reason_email = trim($_POST['reason_email'] ?? '');

    if (!$email_to_ban) {
        $messaggio = "Formato email non valido.";
        $messaggio_tipo = 'danger';
    } else {
        // Controlla se l'email è già bannata
        $stmt_check = mysqli_prepare($con, "SELECT id FROM banned_emails WHERE email = ?");
        mysqli_stmt_bind_param($stmt_check, "s", $email_to_ban);
        mysqli_stmt_execute($stmt_check);
        mysqli_stmt_store_result($stmt_check);
        if (mysqli_stmt_num_rows($stmt_check) > 0) {
            $messaggio = "L'email '" . htmlspecialchars($email_to_ban) . "' è già presente nella lista dei ban.";
            $messaggio_tipo = 'warning';
        } else {
            // Inserisci il nuovo ban
            $stmt_insert = mysqli_prepare($con, "INSERT INTO banned_emails (email, reason, banned_at) VALUES (?, ?, NOW())");
            if ($stmt_insert) {
                mysqli_stmt_bind_param($stmt_insert, "ss", $email_to_ban, $reason_email);
                if (mysqli_stmt_execute($stmt_insert)) {
                    $messaggio = "Email '" . htmlspecialchars($email_to_ban) . "' bannata con successo.";
                    $messaggio_tipo = 'success';
                } else {
                    $messaggio = "Errore durante l'inserimento del ban email.";
                    $messaggio_tipo = 'danger';
                    error_log("Email Ban Insertion Error: " . mysqli_stmt_error($stmt_insert));
                }
                mysqli_stmt_close($stmt_insert);
            } else {
                $messaggio = "Errore nella preparazione della query di inserimento email.";
                $messaggio_tipo = 'danger';
                error_log("Email Ban Insert Prepare Error: " . mysqli_error($con));
            }
        }
        mysqli_stmt_close($stmt_check);
    }
}

// --- Gestione Aggiunta Ban IP (POST) ---
if (isset($_POST['ban_ip_submit'])) {
    $ip_to_ban = trim(filter_input(INPUT_POST, 'ip_to_ban', FILTER_VALIDATE_IP)); // Valida come IP
    $reason_ip = trim($_POST['reason_ip'] ?? '');

    if (!$ip_to_ban) {
        $messaggio = "Formato indirizzo IP non valido.";
        $messaggio_tipo = 'danger';
    } else {
        // Controlla se l'IP è già bannato
        $stmt_check = mysqli_prepare($con, "SELECT id FROM banned_ips WHERE ip_address = ?");
        mysqli_stmt_bind_param($stmt_check, "s", $ip_to_ban);
        mysqli_stmt_execute($stmt_check);
        mysqli_stmt_store_result($stmt_check);
        if (mysqli_stmt_num_rows($stmt_check) > 0) {
            $messaggio = "L'indirizzo IP '" . htmlspecialchars($ip_to_ban) . "' è già presente nella lista dei ban.";
            $messaggio_tipo = 'warning';
        } else {
            // Inserisci il nuovo ban
            $stmt_insert = mysqli_prepare($con, "INSERT INTO banned_ips (ip_address, reason, banned_at) VALUES (?, ?, NOW())");
            if ($stmt_insert) {
                mysqli_stmt_bind_param($stmt_insert, "ss", $ip_to_ban, $reason_ip);
                if (mysqli_stmt_execute($stmt_insert)) {
                    $messaggio = "Indirizzo IP '" . htmlspecialchars($ip_to_ban) . "' bannato con successo.";
                    $messaggio_tipo = 'success';
                } else {
                    $messaggio = "Errore durante l'inserimento del ban IP.";
                    $messaggio_tipo = 'danger';
                    error_log("IP Ban Insertion Error: " . mysqli_stmt_error($stmt_insert));
                }
                mysqli_stmt_close($stmt_insert);
            } else {
                $messaggio = "Errore nella preparazione della query di inserimento IP.";
                $messaggio_tipo = 'danger';
                error_log("IP Ban Insert Prepare Error: " . mysqli_error($con));
            }
        }
        mysqli_stmt_close($stmt_check);
    }
}


// Recupera messaggio dalla URL se presente (dopo redirect da cancellazione)
if (isset($_GET['msg']) && empty($messaggio)) { // Controlla se $messaggio è già stato impostato da un POST
    $messaggio = $_GET['msg'];
    $messaggio_tipo = $_GET['msg_type'] ?? 'info';
}

// --- Recupero Dati Ban (invariato) ---
$banned_emails = [];
$banned_ips = [];
// ... (codice recupero dati esistente) ...
// Email Bannate
$result_emails = mysqli_query($con, "SELECT id, email, reason, banned_at FROM banned_emails ORDER BY banned_at DESC");
if ($result_emails) {
    while ($row = mysqli_fetch_assoc($result_emails)) {
        $banned_emails[] = $row;
    }
    mysqli_free_result($result_emails);
} else {
    $messaggio .= "<br>Errore nel recupero delle email bannate: " . mysqli_error($con);
    if($messaggio_tipo != 'danger') $messaggio_tipo = 'warning';
    error_log("Fetch Banned Emails Error: " . mysqli_error($con));
}

// IP Bannati
$result_ips = mysqli_query($con, "SELECT id, ip_address, reason, banned_at FROM banned_ips ORDER BY banned_at DESC");
if ($result_ips) {
    while ($row = mysqli_fetch_assoc($result_ips)) {
        $banned_ips[] = $row;
    }
    mysqli_free_result($result_ips);
} else {
     $messaggio .= "<br>Errore nel recupero degli IP bannati: " . mysqli_error($con);
     if($messaggio_tipo != 'danger') $messaggio_tipo = 'warning';
     error_log("Fetch Banned IPs Error: " . mysqli_error($con));
}


mysqli_close($con);
?>
<!DOCTYPE html>
<html lang="it">
    <head>
        <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="description" content="Gestione Ban Email e IP" />
        <meta name="author" content="" />
        <title>Gestisci Ban | Sistema Admin</title>
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@latest/dist/style.css" rel="stylesheet" />
        <link href="../css/styles.css" rel="stylesheet" />
        <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/js/all.min.js" crossorigin="anonymous"></script>
        <style>
            /* ... (stili esistenti) ... */
            .form-ban { margin-bottom: 1.5rem; padding: 1rem; border: 1px solid #dee2e6; border-radius: .25rem; background-color: #f8f9fa; }
            .form-ban label { font-weight: bold; }
        </style>
    </head>
    <body class="sb-nav-fixed">
      <?php include_once('includes/navbar.php');?>
        <div id="layoutSidenav">
         <?php include_once('includes/sidebar.php');?>
            <div id="layoutSidenav_content">
                <main>
                    <div class="container-fluid px-4">
                        <h1 class="mt-4">Gestisci Ban</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Gestisci Ban</li>
                        </ol>

                        <?php if (!empty($messaggio)): ?>
                            <div class="alert alert-<?php echo htmlspecialchars($messaggio_tipo); ?> alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($messaggio); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Form Aggiungi Ban Email -->
                        <div class="form-ban">
                            <h5>Banna Nuova Email</h5>
                            <form method="post" action="gestisci_ban.php" class="row g-3 align-items-end">
                                <div class="col-md-5">
                                    <label for="email_to_ban" class="form-label">Email da bannare</label>
                                    <input type="email" class="form-control" id="email_to_ban" name="email_to_ban" required>
                                </div>
                                <div class="col-md-5">
                                    <label for="reason_email" class="form-label">Motivo (opzionale)</label>
                                    <input type="text" class="form-control" id="reason_email" name="reason_email">
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" name="ban_email_submit" class="btn btn-warning w-100">Banna Email</button>
                                </div>
                            </form>
                        </div>

                        <!-- Form Aggiungi Ban IP -->
                         <div class="form-ban">
                            <h5>Banna Nuovo Indirizzo IP</h5>
                            <form method="post" action="gestisci_ban.php" class="row g-3 align-items-end">
                                <div class="col-md-5">
                                    <label for="ip_to_ban" class="form-label">Indirizzo IP da bannare</label>
                                    <input type="text" class="form-control" id="ip_to_ban" name="ip_to_ban" required>
                                </div>
                                <div class="col-md-5">
                                    <label for="reason_ip" class="form-label">Motivo (opzionale)</label>
                                    <input type="text" class="form-control" id="reason_ip" name="reason_ip">
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" name="ban_ip_submit" class="btn btn-danger w-100">Banna IP</button>
                                </div>
                            </form>
                        </div>

                        <!-- Tabella Email Bannate (invariata) -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-envelope-open-text me-1"></i>
                                Email Bannate
                            </div>
                            <div class="card-body">
                                <table id="datatablesSimpleEmails">
                                    <thead>
                                        <tr>
                                             <th>#</th>
                                             <th>Email</th>
                                             <th>Motivo</th>
                                             <th>Data Ban</th>
                                             <th>Azione</th>
                                        </tr>
                                    </thead>
                                    <tfoot>
                                        <tr>
                                             <th>#</th>
                                             <th>Email</th>
                                             <th>Motivo</th>
                                             <th>Data Ban</th>
                                             <th>Azione</th>
                                        </tr>
                                    </tfoot>
                                    <tbody>
                                        <?php if (!empty($banned_emails)): ?>
                                            <?php $cnt_email = 1; ?>
                                            <?php foreach ($banned_emails as $email_ban): ?>
                                            <tr>
                                                <td><?php echo $cnt_email++; ?></td>
                                                <td><?php echo htmlspecialchars($email_ban['email']); ?></td>
                                                <td><?php echo htmlspecialchars($email_ban['reason'] ?: 'N/D'); ?></td>
                                                <td><?php echo htmlspecialchars(date("d/m/Y H:i", strtotime($email_ban['banned_at']))); ?></td>
                                                <td>
                                                    <a href="gestisci_ban.php?del_email_id=<?php echo $email_ban['id']; ?>"
                                                       onClick="return confirm('Sei sicuro di voler rimuovere il ban per questa email?');"
                                                       title="Rimuovi Ban" class="btn btn-danger btn-sm">
                                                        <i class="fa fa-trash" aria-hidden="true"></i> Rimuovi
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td colspan="5" class="text-center">Nessuna email attualmente bannata.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Tabella IP Bannati (invariata) -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-network-wired me-1"></i>
                                Indirizzi IP Bannati
                            </div>
                            <div class="card-body">
                                <table id="datatablesSimpleIPs">
                                    <thead>
                                        <tr>
                                             <th>#</th>
                                             <th>Indirizzo IP</th>
                                             <th>Motivo</th>
                                             <th>Data Ban</th>
                                             <th>Azione</th>
                                        </tr>
                                    </thead>
                                    <tfoot>
                                        <tr>
                                             <th>#</th>
                                             <th>Indirizzo IP</th>
                                             <th>Motivo</th>
                                             <th>Data Ban</th>
                                             <th>Azione</th>
                                        </tr>
                                    </tfoot>
                                    <tbody>
                                        <?php if (!empty($banned_ips)): ?>
                                            <?php $cnt_ip = 1; ?>
                                            <?php foreach ($banned_ips as $ip_ban): ?>
                                            <tr>
                                                <td><?php echo $cnt_ip++; ?></td>
                                                <td><?php echo htmlspecialchars($ip_ban['ip_address']); ?></td>
                                                <td><?php echo htmlspecialchars($ip_ban['reason'] ?: 'N/D'); ?></td>
                                                <td><?php echo htmlspecialchars(date("d/m/Y H:i", strtotime($ip_ban['banned_at']))); ?></td>
                                                <td>
                                                    <a href="gestisci_ban.php?del_ip_id=<?php echo $ip_ban['id']; ?>"
                                                       onClick="return confirm('Sei sicuro di voler rimuovere il ban per questo IP?');"
                                                       title="Rimuovi Ban" class="btn btn-danger btn-sm">
                                                        <i class="fa fa-trash" aria-hidden="true"></i> Rimuovi
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td colspan="5" class="text-center">Nessun indirizzo IP attualmente bannato.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>
                </main>
                <?php include('../includes/footer.php');?>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
        <script src="../js/scripts.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/simple-datatables@latest" crossorigin="anonymous"></script>
        <!-- Inizializza DataTables (invariato) -->
        <script>
            window.addEventListener('DOMContentLoaded', event => {
                const datatablesSimpleEmails = document.getElementById('datatablesSimpleEmails');
                if (datatablesSimpleEmails) {
                    new simpleDatatables.DataTable(datatablesSimpleEmails);
                }
                const datatablesSimpleIPs = document.getElementById('datatablesSimpleIPs');
                if (datatablesSimpleIPs) {
                    new simpleDatatables.DataTable(datatablesSimpleIPs);
                }
            });
        </script>
    </body>
</html>
