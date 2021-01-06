<!DOCTYPE html>

<html>
    <head>
        <meta charset="utf-8">
        <title>Error!</title>
        <link rel="stylesheet" href="styles/common.css">
    </head>

    <body>
        <section style="padding-left: 20px;">
            <h1>Something went wrong!</h1>
            <?php
                session_start();
                if (isset($_SESSION['fatal_error'])) {
                    echo "<p style='font-size: 20px'>" . $_SESSION['fatal_error'] . "</p>";
                    unset($_SESSION['fatal_error']);
                }
            ?>
            <a href="index.php">Return to index</a>
        </section>
    </body>
</html>