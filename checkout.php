<?php
    require_once "common.php";
    require_once "food_table.php";

    $parent = "customer.php";

    // If directed from the client-side (basket), we get the checkout list from POST;
    // If directed back from the server-side, we get the checkout list from SESSION.
    $list = isset($_POST['checkout_list']) ? $_POST['checkout_list'] : $_SESSION['checkout_list'];
    $list = json_decode($list, true);
    if (empty($list)) {
        header("Location: $parent");
        exit;
    }

    $conn = connect();
    $table_html = verify_and_create_checkout_table($list, $conn, basename(__FILE__));
    // If the entire list was emptied in verification, return to customer page and clear the error:
    if (empty($list)) {
        clear_error(basename(__FILE__));
        header("Location: $parent");
        exit;
    }

    $conn->close();
?>

<!DOCTYPE html>

<html lang="en">

    <head>
        <meta charset="utf-8">
        <title>Confirm your reservation</title>
        <link rel="stylesheet" href="styles/checkout.css">
    </head>

    <body>
        
        <header>
            <a href="index.php"><h1>Žraadlotäika</h1></a>
            <a id="go_back_button" href="customer.php"><img src="./img/back.png"></a>
        </header>

        <section id="main">
        <section id="centered_main">
            <section id="checkout">
                <section id="list">
                    <h1>Verify your reservation:</h1>
                    <?php echo_error(basename(__FILE__)); ?>
                    <?php echo $table_html ?>
                </section>
                <section id="form_section">
                    <form method="POST" action="reserve.php">
                        <input type="hidden" name="checkout_list" value=<?php echo json_encode($list); ?>>
                        <div id="input_fields">
                            <label for="name">Who is this reservation for?</label>
                            <input type="text" name="name" id="name" placeholder="Your name..." maxlength="100" required>

                            <label for="phone">Just in case, how can we contact you?</label>
                            <input type="tel" name="phone" id="phone" placeholder="Your phone..." maxlength="16" required>
                        </div>
                        <input id="submit" type="submit" value="Confirm Reservation">
                    </form>
                </section>
            </section>
        </section>
        </section>

        <?php include("footer.html"); ?>
    </body>
</html>