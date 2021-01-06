<?php
    require_once "common.php";

    $TABLE_ITEM_TEMPLATE = file_get_contents("other/table_item_template.html");
    $TABLE_ITEM_CHECKBOXED_TEMPLATE = file_get_contents("other/table_item_checkboxed_template.html");

    // Creates a table row out of specified arguments.
    // If $checkbox_id is specified, a checkbox with that value will be included in the row.
    // (The checkboxes will have the name 'food_ids[]').
    function create_item_html($i, $name, $restaurant, $quantity, $price, $sum, $checkbox_id = "") {
        $item_html = empty($checkbox_id) ?
            $GLOBALS["TABLE_ITEM_TEMPLATE"] :
            $GLOBALS["TABLE_ITEM_CHECKBOXED_TEMPLATE"];

        $insert = function($what, $with) use (&$item_html)
        {
            $item_html = str_replace($what, $with, $item_html);
        };

        if (!empty($checkbox_id))
            $insert("'%ID%'", $checkbox_id);

        $insert("'%INDEX%'",      $i);
        $insert("'%NAME%'",       htmlspecialchars($name));
        $insert("'%RESTAURANT%'", htmlspecialchars($restaurant));
        $insert("'%QUANTITY%'",   $quantity);
        $insert("'%PRICE%'",      $price);
        $insert("'%SUM%'",        $sum);

        return $item_html;
    }

    // The tables will be sorted by restaurant names
    // (in order for foods of same restaurant to be grouped together)
    function compare_food_rows($food1, $food2) {
        return strcmp($food1["restaurant_name"], $food2["restaurant_name"]);
    }

    /**
    * Returns an HTML table representing the list of foods, where:
    * 
    * @param array $list is an associative array (food_id => quantity);
    * 
    * @param mixed $conn is a connection to the database from which additional information will be fetched;
    *
    * @param string $location is the filename of the caller; used for error reporting.
    *
    * @return string string that represents the HTML table
    */
    function verify_and_create_checkout_table(&$list, $conn, $location) {
        $html = "";

        // Prepare SELECT statement to select only foods with specific IDs:
        $in = str_repeat("?,", count($list) - 1) . '?';
        $stmt = $conn->prepare(
            "SELECT food_id, food_name, food_quantity, 
                food_price * (100 - food_sale) / 100 AS full_price, restaurant_name 
                FROM food INNER JOIN restaurant 
                    ON restaurant.restaurant_id=food.restaurant_id
                WHERE food_id IN ($in)") 
            or fatal_error("Failed to prepare: " . $conn->error);

        // Insert the specific IDs into the statement:
        $stmt->bind_param(str_repeat('i', count($list)), ...array_keys($list));
        if (!$stmt->execute())
            fatal_error("Failed to execute: " . $stmt->error);

        // Read results:
        $result = $stmt->get_result() or fatal_error("Failed to get results: " . $conn->error);
        $foods = $result->fetch_all(MYSQLI_ASSOC);
        usort($foods, "compare_food_rows");

        $all_items_available = true;
        $i = 1;
        $total_sum = 0;

        $html .= "<table>";
        $html .= create_item_html("#", "Name", "Restaurant", "Qty.", "Price (€)", "Sum (€)");
        foreach ($foods as $food) {
            $quantity = $list[$food["food_id"]];
            $available = $food["food_quantity"];
            if ($quantity > $available)
            {
                $all_items_available = false;
                if ($available == 0)
                {
                    unset($list[$food["food_id"]]);
                    continue;
                }
                $list[$food["food_id"]] = $available;
                $quantity = $available;
            }

            $sum = $food["full_price"] * $quantity;
            $html .= create_item_html(
                $i,
                $food["food_name"],
                $food["restaurant_name"],
                $quantity,
                number_format(floatval($food["full_price"]), 2, ',', ''),
                number_format(floatval($sum), 2, ',', ''));
            ++$i;
            $total_sum += $sum;
        }
        $html .= create_item_html("", "", "", "", "", number_format(floatval($total_sum), 2, ',', ''));
        $html .= "</table>";

        if (count($foods) != count($list))
            $all_items_available = false;
        if (!$all_items_available)
            $_SESSION["error_$location"] = "Some items were no longer available and were removed! Please review your order.";

        return $html;
    }

    /**
    * Returns one or two HTML tables, where one represents cancelled reservations
    * and the other other active reservations.
    * First table has id 'cancelled' and the second table has id 'active'.
    *
    * @param int $reservation_id is the id of the reservation;
    * 
    * @param string $restaurant_email indicates which restaurant is viewing the tables, so that only their foods from the order would be displayed
    *
    * @param mixed $conn is a connection to the database from which additional information will be fetched;
    *
    * @return string string that represents the HTML tables
    */
    function create_reservation_tables($reservation_id, $restaurant_email, $conn) {
        $sql = "";
        $is_restaurant = !empty($restaurant_email);

        if ($is_restaurant) { // Select only restaurant's foods
            $sql = "SELECT food_reservation.* 
                        FROM food_reservation INNER JOIN food 
                            ON food_reservation.food_id = food.food_id
                        INNER JOIN restaurant 
                            ON food.restaurant_id = restaurant.restaurant_id
                        WHERE restaurant.restaurant_email = '$restaurant_email'
                            AND reservation_id = $reservation_id 
                            AND reservation_state <> 4;";
        }
        else { // Select all foods
            $sql = "SELECT * FROM food_reservation
                        WHERE reservation_id = $reservation_id 
                            AND reservation_state <> 4;";
        }

        $result = $conn->query($sql) or fatal_error("Failed to query(food_reservation): " . $conn->error);
        $food_reservations = $result->fetch_all(MYSQLI_ASSOC);

        // Go over each food in reservation...
        $cancelled_reservation_indexes = [];
        $active_reservation_indexes = [];
        foreach ($food_reservations as $i => &$food_reservation) {
            $food_id = $food_reservation["food_id"];
            
            // Fetch additional data:
            $food_result = $conn->query(
                "SELECT food_name, restaurant.restaurant_name 
                    FROM food INNER JOIN restaurant 
                        ON restaurant.restaurant_id = food.restaurant_id
                    WHERE food_id = $food_id;") or fatal_error("Failed to query(food): " . $conn->error);

            if ($food_result->num_rows == 0)
                fatal_error("No food corresponds for food_reservation. ID: $food_id");
            $food = $food_result->fetch_all(MYSQLI_ASSOC)[0];

            $food_reservation["food_name"] = $food["food_name"];
            $food_reservation["restaurant_name"] = $food["restaurant_name"];

            $sum = $food_reservation["food_price"] * $food_reservation["food_quantity"];
            $food_reservation["sum"] = $sum;

            // Cancelled foods go to cancelled-table, active foods to active-table;
            // Fulfilled foods are not displayed.
            if ($food_reservation["reservation_state"] == 1 ||
                $food_reservation["reservation_state"] == 3)
                array_push($cancelled_reservation_indexes, $i);
            else
                array_push($active_reservation_indexes, $i);
        }

        usort($food_reservations, "compare_food_rows");

        $html = "";

        // Cancelled foods table:
        if (!empty($cancelled_reservation_indexes)) {
            $html .= "<h1>The following items have been cancelled:</h1>";
            $html .= "<table id='cancelled'>";
            $html .= create_item_html("#", "Name", "Restaurant", "Qty.", "Price (€)", "Sum (€)");

            $i = 1;
            foreach ($cancelled_reservation_indexes as $index) {
                $fr = $food_reservations[$index];
                $html .= create_item_html(
                    $i,
                    $fr["food_name"],
                    $fr["restaurant_name"],
                    $fr["food_quantity"],
                    number_format(floatval($fr["food_price"]), 2, ',', ''),
                    number_format(floatval($fr["sum"]), 2, ',', ''));
                ++$i;
            }
            $html .= "</table>";
        }
        
        // Active foods table:
        if (!empty($active_reservation_indexes)) {
            $html .= "<h1>Active reservation:</h1>";
            $html .= "<table id='active'>";
            $html .= create_item_html("#", "Name", "Restaurant", "Qty.", "Price (€)", "Sum (€)", 'redundant_checkbox');

            $i = 1;
            $total_sum = 0;
            foreach ($active_reservation_indexes as $index) {
                $fr = $food_reservations[$index];
                $html .= create_item_html(
                    $i,
                    $fr["food_name"],
                    $fr["restaurant_name"],
                    $fr["food_quantity"],
                    number_format(floatval($fr["food_price"]), 2, ',', ''),
                    number_format(floatval($fr["sum"]), 2, ',', ''),
                    $fr["food_id"]);
                ++$i;
                $total_sum += $fr["sum"];
            }

            $html .= create_item_html("", "", "", "", "", number_format(floatval($total_sum), 2, ',', ''), 'check_all');
            $html .= "</table>";
        }

        // Print special case notices:
        if (empty($active_reservation_indexes)) {
            if (empty($cancelled_reservation_indexes))
                $html .= "<p class='error fulfilled'>This reservation has been fulfilled.</p>";
            else
                $html .= "<p class='error'>This reservation has been cancelled.</p>";
        }

        return $html;
    }
?>