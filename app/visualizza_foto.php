<?php
// visualizza_foto.php

define('UPLOAD_DIR', 'uploads/auto_foto/'); 


$filename = filter_input(INPUT_GET, 'file'); 


$filename_cleaned = basename(str_replace('..', '', $filename ?? ''));

if (!$filename || $filename !== $filename_cleaned || empty($filename_cleaned)) {
    http_response_code(400); // Bad Request
    echo "Nome file non valido.";
    exit;
}
// --- Fine Validazione Nome File ---
$filepath = UPLOAD_DIR . $filename_cleaned; 


if (file_exists($filepath) && is_readable($filepath)) {

    // Determina il tipo MIME corretto leggendo il file (più sicuro dell'estensione)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if (!$finfo) {
        http_response_code(500);
        error_log("Impossibile aprire finfo.");
        echo "Errore interno del server.";
        exit;
    }
    $mime_type = finfo_file($finfo, $filepath);
    finfo_close($finfo);

    
    $allowed_display_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($mime_type, $allowed_display_types)) {
        http_response_code(403); // Forbidden
        error_log("Tentativo di accesso a file non immagine: $filepath con MIME: $mime_type");
        echo "Tipo file non consentito per la visualizzazione.";
        exit;
    }

    // Imposta l'header Content-Type corretto
    header('Content-Type: ' . $mime_type);
    // Imposta la lunghezza del contenuto per ottimizzare il caricamento
    header('Content-Length: ' . filesize($filepath));
    // Opzionale: Aggiungi header per il caching per migliorare le prestazioni
    header('Cache-Control: max-age=86400'); // Cache per 1 giorno
    header('Pragma: cache');
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');

    
    if (ob_get_level()) {
        ob_end_clean();
    }
    readfile($filepath);
    exit; // Termina lo script dopo aver inviato il file

} else {
    
    http_response_code(404);
    error_log("File non trovato o non leggibile: $filepath"); // Logga l'errore
    // Potresti mostrare un'immagine placeholder invece di un messaggio di testo
    // header('Content-Type: image/png');
    // readfile('path/to/placeholder_image.png');
    echo "Immagine non trovata.";
    exit;
}
