<?php
session_start();
include_once('../includes/config.php'); // Include configurazione DB e PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require '../vendor/autoload.php'; // Assicurati che il percorso a vendor/autoload.php sia corretto

// Verifica se l'admin è loggato
if (strlen($_SESSION['adminid'] ?? 0) == 0) {
    header('location:logout.php');
    exit();
}

$messaggio = '';
$messaggio_tipo = 'danger'; // Default a errore

// Recupera ID Manifestazione dalla URL
$manifestazione_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$manifestazione_id) {
    $messaggio = "ID manifestazione non valido.";
} else {
    // 1. Recupera dettagli manifestazione
    $stmt_manif = mysqli_prepare($con, "SELECT * FROM manifestazioni WHERE id = ?");
    $manifestazione = null;
    if ($stmt_manif) {
        mysqli_stmt_bind_param($stmt_manif, "i", $manifestazione_id);
        mysqli_stmt_execute($stmt_manif);
        $result_manif = mysqli_stmt_get_result($stmt_manif);
        $manifestazione = mysqli_fetch_assoc($result_manif);
        mysqli_stmt_close($stmt_manif);
    } else {
        $messaggio = "Errore nel recupero dettagli manifestazione: " . mysqli_error($con);
        error_log("Invia Email Manifestazione - Fetch Manifestazione Prepare Error: " . mysqli_error($con));
    }

    if (!$manifestazione) {
        $messaggio = "Manifestazione non trovata.";
    } else {
        // 2. Recupera email di TUTTI gli utenti ATTIVI
        $user_emails = [];
        $sql_users = "SELECT email FROM users WHERE is_active = 1"; // Seleziona solo utenti attivi
        $result_users = mysqli_query($con, $sql_users);
        if ($result_users) {
            while ($user = mysqli_fetch_assoc($result_users)) {
                if (!empty($user['email']) && filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
                    $user_emails[] = $user['email'];
                }
            }
            mysqli_free_result($result_users);
        } else {
             $messaggio = "Errore nel recupero degli indirizzi email degli utenti: " . mysqli_error($con);
             error_log("Invia Email Manifestazione - Fetch Users Error: " . mysqli_error($con));
        }

        if (empty($user_emails)) {
            $messaggio = "Nessun utente attivo trovato a cui inviare l'email.";
            $messaggio_tipo = 'warning';
        } else {
            // 3. Prepara e invia l'email usando PHPMailer con BCC
            $mail = new PHPMailer(true);
            try {
                // Impostazioni Server SMTP (come nelle altre pagine)
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'dezuani.fotovoltaico@gmail.com'; // Sostituisci
                $mail->Password = 'ymzf ceed cgvr tpga'; // Sostituisci
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;
                $mail->CharSet = 'UTF-8';

                // Mittente
                $mail->setFrom('dezuani.fotovoltaico@gmail.com', 'Asi-Castello'); // Sostituisci

                // Destinatari in BCC
                // ATTENZIONE: Molti provider limitano il numero di BCC per email.
                // Se hai molti utenti, questa soluzione potrebbe fallire o essere bloccata.
                // Considera l'invio in blocchi o l'uso di servizi email dedicati.
                foreach ($user_emails as $email) {
                    $mail->addBCC($email);
                }
                // Potresti aggiungere un destinatario principale fittizio o l'email del club in 'To'
                // $mail->addAddress('info@tuoclub.it', 'ASI Castello Info');

                // Contenuto Email
                $mail->isHTML(true);
                $mail->Subject = 'Invito Manifestazione: ' . htmlspecialchars($manifestazione['titolo']);

                // Costruisci il corpo dell'email con i dettagli
                $linkManifestazioni = "http://localhost/asi-castello/manifestazioni.php"; // Link alla pagina di iscrizione
                $body = "<h2>Gentili Appassionati,</h2>";
                $body .= "<p>Siete invitati a partecipare alla nostra prossima manifestazione:</p>";
                $body .= "<h3>" . htmlspecialchars($manifestazione['titolo']) . "</h3>";
                $body .= "<p><strong>Data e Ora:</strong> " . date("d/m/Y H:i", strtotime($manifestazione['data_inizio'])) . "</p>";
                if (!empty($manifestazione['luogo_ritrovo'])) {
                    $body .= "<p><strong>Luogo Ritrovo:</strong> " . htmlspecialchars($manifestazione['luogo_ritrovo']) . "</p>";
                }
                if ($manifestazione['quota_pranzo'] > 0) {
                     $body .= "<p><strong>Quota Pranzo:</strong> € " . number_format($manifestazione['quota_pranzo'], 2, ',', '.') . "</p>";
                }
                if (!empty($manifestazione['programma'])) {
                    $body .= "<div><strong>Programma:</strong><br>" . nl2br(htmlspecialchars($manifestazione['programma'])) . "</div><br>";
                }
                 if (!empty($manifestazione['note'])) {
                    $body .= "<div><strong>Note Aggiuntive:</strong><br>" . nl2br(htmlspecialchars($manifestazione['note'])) . "</div><br>";
                }
                $body .= "<p>Le iscrizioni chiudono il: " . date("d/m/Y H:i", strtotime($manifestazione['data_chiusura_iscrizioni'])) . "</p>";
                $body .= "<p><strong>Per iscriverti e per maggiori dettagli, visita la pagina delle manifestazioni sul nostro sito:</strong><br>";
                $body .= '<a href="' . $linkManifestazioni . '">' . $linkManifestazioni . '</a></p>';
                $body .= "<p>Vi aspettiamo numerosi!</p>";
                $body .= "<p>Cordiali saluti,<br>Il Team Asi-Castello</p>";

                $mail->Body = $body;
                // Crea un AltBody semplice
                $altBody = "Invito Manifestazione: " . htmlspecialchars($manifestazione['titolo']) . "\n\n";
                $altBody .= "Data: " . date("d/m/Y H:i", strtotime($manifestazione['data_inizio'])) . "\n";
                // ... aggiungi altri dettagli testuali ...
                $altBody .= "Iscriviti qui: " . $linkManifestazioni . "\n\n";
                $altBody .= "Cordiali saluti,\nIl Team Asi-Castello";
                $mail->AltBody = $altBody;


                $mail->send();
                $messaggio = 'Email per la manifestazione "' . htmlspecialchars($manifestazione['titolo']) . '" inviata con successo a ' . count($user_emails) . ' utenti.';
                $messaggio_tipo = 'success';

            } catch (Exception $e) {
                $messaggio = "Errore durante l'invio dell'email. Mailer Error: {$mail->ErrorInfo}";
                $messaggio_tipo = 'danger';
                error_log("Invia Email Manifestazione - Mailer Error: " . $mail->ErrorInfo);
            }
        }
    }
}

// 4. Redirect alla pagina di gestione con il messaggio
// Usa parametri diversi per il messaggio dell'email per distinguerlo da altri messaggi
header("Location: manage-manifestazioni.php?email_msg=" . urlencode($messaggio) . "&email_msg_type=" . $messaggio_tipo);
exit();
?>
