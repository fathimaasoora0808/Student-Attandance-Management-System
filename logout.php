<?php
// Starts or resumes the current session to access its data.
session_start();
// Destroys all data registered to a session, effectively logging the user out.
session_destroy();
// Redirects the user's browser to the login page after the session has been destroyed.
header("Location: login.php");
// Stops the script from executing any further.
exit();
?>
