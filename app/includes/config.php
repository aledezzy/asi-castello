<?php
define('DB_SERVER','127.0.0.1');
define('DB_USER','root');
define('DB_PASS' ,'');
define('DB_NAME', 'loginsystem');
// Chiavi Google reCAPTCHA v2 Checkbox
define('RECAPTCHA_SITE_KEY', '6Ld5eyErAAAAAN3fWiUC1f53DDe9pAnZRXvZ8bF-'); // Sostituisci con la tua Site Key
define('RECAPTCHA_SECRET_KEY', '6Ld5eyErAAAAAJXol4GEEqDvbzoBnTArf8xjx-CS'); // Sostituisci con la tua Secret Key



$con = mysqli_connect(DB_SERVER,DB_USER,DB_PASS,DB_NAME, 3306);

// Check connection
if (mysqli_connect_errno())
{
echo "Failed to connect to MySQL: " . mysqli_connect_error();
 }

?>
