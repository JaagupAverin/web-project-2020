<?php
    require_once "common.php";

    if (!$_POST || !$_POST['food_ids'] || !$_POST['access_code'])
        fatal_error("Unexpected redirection. Form data not provided.");

    // Indicates if its the restaurant doing the cancelling:
    $restaurant_email = isset($_POST['restaurant']) ? $_POST['restaurant'] : "";
    $as_restaurant = !empty($restaurant_email) && $restaurant_email == $_SESSION['restaurant_email'];

    $new_state = 0;
    if ($as_restaurant) {
        if ($_POST['action'] == "Cancel selected")
            $new_state = 3;
        else if ($_POST['action'] == "Fulfill selected")
            $new_state = 4;
        else
            fatal_error("Unknown action: " . $_POST['action']);
    }
    else
        $new_state = 1;

    $food_ids = $_POST['food_ids'];
    $access_code = $_POST['access_code'];
    
    if (empty($food_ids)) 
        fatal_error("Unexpected redirection. No food IDs specified for cancellation.");
    foreach($food_ids as $i => &$id) {
        $id = intval($id);
    }
    
    $conn = connect();

    $rollback_and_fatal = function($error) use (&$conn) {
        if (!$conn->rollback())
            $error .= " + ROLLBACK FAILED!";
        fatal_error($error);
    };

    // We're going to SELECT and INSERT several times, with each statement relying on the previous,
    // so wrap everything in a single transaction for assuring synchronization, and allowing rollbacks.
    $conn->begin_transaction();

    // Convert access_code to reservation_id:
    $access_stmt = $conn->prepare("SELECT reservation_id FROM reservation WHERE access_code=?;") or $rollback_and_fatal("Failed to prepare(access_code): " . $conn->error);

    $access_stmt->bind_param('s', $access_code);
    if (!$access_stmt->execute())
        $rollback_and_fatal("Failed to execute(access_code):" . $conn->error);

    $access_result = $access_stmt->get_result() or $rollback_and_fatal("Could not get results(access_code):" . $conn->error);
    if ($access_result->num_rows == 0)
        $rollback_and_fatal("No reservation corresponds to access code: " . $access_code);
    
    $reservation_id = $access_result->fetch_all(MYSQLI_ASSOC)[0]['reservation_id'];

    // Cancel all specified foods from given reservation:
    $in = implode(",", $food_ids);
    $conn->query("UPDATE food_reservation SET reservation_state = $new_state
                 WHERE food_id IN ($in) AND reservation_id = $reservation_id;")
                 or $rollback_and_fatal("Failed to set reservation_state: " . $conn->error);
    
    // Get all the newly cancelled foods...
    $cancelled_foods = $conn->query(
        "SELECT food_id, food_quantity FROM food_reservation
        WHERE food_id IN ($in) AND reservation_id = $reservation_id;")
        or $rollback_and_fatal("Failed to query cancelled foods: " . $conn->error);
    $cancelled_foods = $cancelled_foods->fetch_all(MYSQLI_ASSOC);

    // ...and restore their quantities (into the 'food' table):
    $restore_quantities_sql = "";
    foreach ($cancelled_foods as $food) {
        $food_id = $food['food_id'];
        $reserved_quantity = $food['food_quantity'];
        $restore_quantities_sql .=
            "UPDATE food SET food_quantity = food_quantity + $reserved_quantity WHERE food_id = $food_id; ";
    }
    $conn->multi_query($restore_quantities_sql) or $rollback_and_fatal("Failed to restore food quantities: " . $conn->error);
    while ($conn->next_result()) {;} // flush multi_queries

    // Check the individual state of every food in the whole reservation:
    $all_states = $conn->query(
        "SELECT reservation_state FROM food_reservation
        WHERE reservation_id=$reservation_id") or $rollback_and_fatal("Failed to query new reservation states: " . $conn->error);

    if ($all_states->num_rows == 0)
        $rollback_and_fatal("Unexpected 0. Reservation must have at least 1 food.");
    $all_states = array_column($all_states->fetch_all(), 0);

    if (!$conn->commit())
        $rollback_and_fatal("Commit failed: " . $conn->error);

    /*------------------------------------------------------------------*/
    // In the real world, we would also send a message to customer's phone that their order has been modified.
    /*------------------------------------------------------------------*/

    $return_to = "reservation.php?a=$access_code";
    if ($as_restaurant)
        $return_to .= "&r=$restaurant_email";
    header("Location: $return_to");
    die();
?>