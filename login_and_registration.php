<?php
    require_once "common.php";

    if (isset($_SESSION['restaurant_email']) && !empty($_SESSION['restaurant_email']))
        header("Location: restaurant.php");
?>

<!DOCTYPE html>

<html lang="en">
    <head>
        <meta charset="utf-8">
        <title>Log in for enterprise</title>
        <link rel="stylesheet" href="styles/login_and_registration.css">
    </head>

    <body>
        <header>
            <a href="index.php"><h1>Žraadlotäika</h1></a>
        </header>

        <section id="main">
        <section id="centered_main">
            <section id="login_and_register">
                <?php echo_error(basename(__FILE__)); ?>
                <section id="login">
                    <form method="POST" action="login.php">
                        <h3>Log in:</h3>
                        <label>E-mail:</label>
                        <input name="restaurant_email" type="email" required>

                        <label>Password:</label>
                        <input name="restaurant_password" type="password" minlength="8"  required>
                        <input type="submit" value="Sign in">
                    </form>
                </section>

                <section id="register">
                    <form method="POST" action="register.php">
                        <h3>Or register your enterprise:</h3>
                        <label>E-mail:</label>
                        <input id="email" name="restaurant_email" type="email" maxlength="50" required>

                        <label>Password:</label>
                        <input id="password" name="restaurant_password" type="password" minlength="8" maxlength="256" required>

                        <label>Confirm password:</label>
                        <input id="confirm_password" name="confirm_password" type="password" required>

                        <label>Restaurant name:</label>
                        <input id="name" name="restaurant_name" type="text" maxlength="100" required>
                        
                        <label>Website:</label>
                        <input id="website" name="restaurant_website" type="text" maxlength="500" required>

                        <input type="submit" value="Register">
                    </form>
                    <script src="scripts/registration.js"></script>
                </section>
            </section>
        </section>
        </section>

        <?php include("footer.html"); ?>
    </body>
    <script src="scripts/login_and_registration.js"></script>
</html>