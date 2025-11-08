    <?php
    session_start();

    // If the 'user_id' is NOT set in the session, it means the user is not logged in.
    if (!isset($_SESSION['user_id'])) {
        // Redirect them back to the login page.
        header("Location: Home.html");
        exit(); // Stop the script from running any further
    }
    ?>
    

