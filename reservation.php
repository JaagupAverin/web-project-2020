<?php
    require_once "common.php";
    require_once "food_table.php";

    // a for access_code
    if (!isset($_GET['a']))
        fatal_error("Access code not provided.");
    $access_code = $_GET['a'];
    
    $conn = connect();

    // If the restaurant email is specified in the GET request, it means it is a restaurant
    // viewing the reservation, and we will therefore filter the results to only their foods.
    $restaurant = "";
    $restaurant_id = 0;
    if (isset($_GET['r']) && !empty($_GET['r'])) { // r for restaurant
        $restaurant = $_GET['r'];
        if ($_SESSION['restaurant_email'] != $restaurant)
            fatal_error("Logged in restaurant does not match the request.");

        $restaurant_id_result = $conn->query("SELECT restaurant_id FROM restaurant WHERE restaurant_email = '$restaurant'") or fatal_error("Failed to query restaurant id: " . $conn->error);
        $restaurant_id = $restaurant_id_result->fetch_all(MYSQLI_ASSOC)[0]['restaurant_id'];
    }
    $as_restaurant = !empty($restaurant);

    // Get reservation:
    $stmt = $conn->prepare("SELECT * FROM reservation WHERE access_code=?;") or fatal_error("Failed to prepare: " . $conn->error);
    $stmt->bind_param('s', $access_code);
    if (!$stmt->execute())
        fatal_error("Failed to execute: " . $stmt->error);

    $result = $stmt->get_result() or fatal_error("Failed to get results: " . $conn->error);
    if ($result->num_rows == 0)
        fatal_error("Invalid reservation access code: $access_code");
    $reservation = $result->fetch_all(MYSQLI_ASSOC)[0];

    $name = $reservation['customer_name'];
    $contact = $reservation['customer_contact'];
    $reservation_id = $reservation['reservation_id'];

    // Create reservation tables:
    $table_html = create_reservation_tables($reservation_id, $restaurant, $conn);

    // Mark new reservations as 'seen' if viewing as restaurant:
    if ($as_restaurant) {
        $conn->query(
            "UPDATE food_reservation 
                INNER JOIN food 
                    ON food.food_id = food_reservation.food_id 
                SET reservation_state = 2 
                    WHERE reservation_state = 0 
                        AND reservation_id = $reservation_id 
                        AND food.restaurant_id = $restaurant_id")
            or fatal_error("Failed to update food_reservation states(seen): " . $conn->error);

        // 'cancelled_by_customer' becomes just 'cancelled', also indicating that change has been seen:
        $conn->query(
            "UPDATE food_reservation
                INNER JOIN food 
                    ON food.food_id = food_reservation.food_id
                SET reservation_state = 3
                    WHERE reservation_state = 1
                        AND reservation_id = $reservation_id
                        AND food.restaurant_id = $restaurant_id")
            or fatal_error("Failed to update food_reservation states(cancel): " . $conn->error);
    }

    $conn->close();
?>

<!DOCTYPE html>

<html lang="en">
    <head>
        <meta charset="utf-8">
        <title>Reservation #<?php echo $reservation_id ?></title>
        <link rel="stylesheet" href="styles/reservation.css">
        <?php
            // Reset basket upon finished reservation:
            if (isset($_SESSION["reset_basket"])) {
                echo "<script> localStorage.removeItem('basket'); </script>";
                unset($_SESSION["reset_basket"]);
            }
        ?>
        <script src="scripts/reservation.js" async></script>
    </head>

    <body>
        <header>
            <a href="index.php"><h1>Žraadlotäika</h1></a>
            <?php
            if ($as_restaurant)
                echo '<a id="go_back_button" href="orders.php"><img src="./img/back.png"></a>';
            ?>
        </header>

        <section id="main">
        <section id="centered_main">
            <section id="reservation">
                <form id="cancel" onSubmit="return confirm('Confirm?');" onChange="onCheckboxChange();" action="conclude_reservation.php" method="POST">
                    <section id="tables">
                        <?php
                            echo_error(basename(__FILE__));
                            echo $table_html;
                        ?>
                    </section>
                    <section id="user_section">
                        <div id="name_div">
                            <p>This reservation is for:</p>
                            <p id="name"><?php echo $name ?></p>
                        </div>
                        <div id="contact_div">
                            <p>Contact:</p>
                            <p id="contact"><?php echo $contact ?></p>
                        </div>
                        <div id="reservation_id_div">
                            <p>Reservation:</p>
                            <p id="reservation_id">#<?php echo $reservation_id ?></p>
                        </div>
                    </section>
                    <input type="hidden" name="access_code" value="<?php echo $access_code; ?>">
                    <input type="hidden" name="restaurant" value="<?php echo $restaurant ?>">
                    <input type="submit" name="action" value="Cancel selected" id="cancel_button">
                    <?php
                    if ($as_restaurant)
                        echo '<input type="submit" name="action" value="Fulfill selected" id="fulfill_button"><span></span>';
                    ?>
                </form>
            </section>
        </section>
        </section>

        <?php include("footer.html"); ?>
    </body>
</html>