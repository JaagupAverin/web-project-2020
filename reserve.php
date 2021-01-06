<?php
    require_once "common.php";

    $parent = "checkout.php"; // Path to the parent file 
        
    if (!$_POST || !$_POST['name'] || !$_POST['phone'] || !$_POST['checkout_list'])
        fatal_error('Unexpected redirection. Form data not provided.');

    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $checkout_list = $_POST['checkout_list'];
    $checkout_list = json_decode($checkout_list, true);

    if (empty($checkout_list))
        fatal_error('Unexpected redirection. Checkout list empty.');

    $conn = connect();

    $rollback_and_fatal = function($error) use (&$conn) {
        if (!$conn->rollback())
            $error .= " + ROLLBACK FAILED!";
        fatal_error($error);
    };

    // We're going to SELECT and INSERT several times, with each statement relying on the previous,
    // so wrap everything in a single transaction for assuring synchronization, and allowing rollbacks.
    $conn->begin_transaction();

    // Prepare SELECT statement to select only foods with specific IDs:
    $in = str_repeat("?,", count($checkout_list) - 1) . '?';
    $stmt = $conn->prepare(
        "SELECT food_id, food_quantity, food_price * (100 - food_sale) / 100 AS full_price
            FROM food 
            WHERE food_id IN ($in)") or $rollback_and_fatal("Failed to prepare(food select): " . $conn->error);
    
    // Insert the specific IDs into the statement:
    $stmt->bind_param(str_repeat('i', count($checkout_list)), ...array_keys($checkout_list));
    if (!$stmt->execute())
        $rollback_and_fatal("Failed to execute(food select): " . $stmt->error);

    // Read results:
    $result = $stmt->get_result() or $rollback_and_fatal("Failed to get results(food select): " . $conn->error);
    $foods = $result->fetch_all();

    $remaining_quantities = array_combine(array_column($foods, 0), array_column($foods, 1));
    $full_prices          = array_combine(array_column($foods, 0), array_column($foods, 2));

    // Go over all desired items, and modify the quantities based on availability:
    $all_items_available = true;
    foreach ($checkout_list as $id => $desired_quantity)
    {
        $remaining_quantity = isset($remaining_quantities[$id]) ? $remaining_quantities[$id] : 0;
        if ($desired_quantity > $remaining_quantity)
        {
            $all_items_available = false;

            if ($remaining_quantity == 0)
                unset($checkout_list[$id]);
            else
                $checkout_list[$id] = $remaining_quantity;
        }
    }

    if ($all_items_available)
    {
        // Generate a random unique access code for the reservation:
        $access_code = "";
        while (true) {
            $access_code = strtoupper(substr(md5(microtime()), 0, 5));

            $count_sql = "SELECT 1 FROM reservation WHERE access_code = '$access_code';";
            $count = $conn->query($count_sql) or $rollback_and_fatal("Failed to query($access_code): " . $conn->error);

            if ($count->num_rows == 0) {
                break;
            }
        }

        // Insert general reservation into reservation table:
        $insert_stmt = $conn->prepare(
            "INSERT INTO reservation
            (access_code, customer_name, customer_contact) VALUES (?, ?, ?);")
            or $rollback_and_fatal("Failed to prepare(reservation insertion): " . $conn->error);

        $insert_stmt->bind_param('sss', $access_code, $name, $phone);
        if (!$insert_stmt->execute())
            $rollback_and_fatal("Failed to execute(reservation insertion): " . $stmt->error);

        $reservation_id = $conn->insert_id;
        
        foreach ($checkout_list as $food_id => $quantity)
        {
            // Insert individual foods into food_reservation table:
            $insert2_stmt = $conn->prepare(
                "INSERT INTO food_reservation
                (reservation_id, food_id, food_quantity, food_price) VALUES (?, ?, ?, ?);")
                or $rollback_and_fatal("Failed to prepare(food_reservation insertion): " . $conn->error);
            
            $insert2_stmt->bind_param('iiid', $reservation_id, $food_id, $quantity, $full_prices[$food_id]);
            if (!$insert2_stmt->execute())
                $rollback_and_fatal("Failed to execute(food_reservation insertion): " . $stmt->error);

            // Insert new quantity into food table:
            $modify_food_stmt = $conn->prepare(
                "UPDATE food SET food_quantity = ? WHERE food_id = ?;")
                or $rollback_and_fatal("Failed to prepare(food insertion): " . $conn->error);
            
            $quantity_after = $remaining_quantities[$food_id] - $quantity;
            $modify_food_stmt->bind_param('ii', $quantity_after, $food_id);
            if (!$modify_food_stmt->execute())
                $rollback_and_fatal("Failed to execute(food insertion): " . $stmt->error);
        }
        
        if (!$conn->commit())
            $rollback_and_fatal("Commit failed: " . $conn->error);
        
        $_SESSION['reset_basket'] = true;

        /*------------------------------------------------------------------*/
        // In the real world, we would also send this access link to the customer's contact phone.
        /*------------------------------------------------------------------*/
        header("Location: reservation.php?a=$access_code");
        die();
    }
    else
    {
        if (!$conn->rollback())
            fatal_error("Rollback failed: " . $conn->error);

        $_SESSION['checkout_list'] = json_encode($checkout_list);
        return_with_error("Some items were no longer available and were removed! Please review your order.", $parent);
    }
?>