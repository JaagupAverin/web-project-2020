<?php
    require_once 'common.php';

    $conn = connect();
    $restaurant_id = connect_restaurant($conn, $_SESSION["restaurant_email"]);

    function compare_orders($a, $b) {
        return $a["STATE"] > $b["STATE"] ? 1 : -1;
    }

    function get_results() {
        global $conn, $restaurant_id;
        $sql = "SELECT reservation.reservation_id AS ORDER_ID, access_code AS ACCESS_CODE, customer_name AS CUSTOMER_NAME, customer_contact AS CUSTOMER_PHONE, food_reservation.food_quantity AS ORDER_QUANTITY, food_reservation.food_price AS ORDER_PRICE, reservation_state AS `STATE`
            FROM reservation
                INNER JOIN food_reservation
                    ON food_reservation.reservation_id=reservation.reservation_id
                INNER JOIN food
                    ON food_reservation.food_id=food.food_id
            WHERE restaurant_id=$restaurant_id";
        $result = $conn->query($sql); 
        if(!$result) {
            fatal_error("Failed to fetch orders!");
        }

        // Checks whether any item was found
        if ($result->num_rows == 0) {
            echo '<div>You have no orders.</div>';
        }

        $reservations = array();
        foreach($result->fetch_all(MYSQLI_ASSOC) as $row) {
            if (!array_key_exists($row["ORDER_ID"], $reservations)) {
                $row["EMAIL"] = $_SESSION["restaurant_email"];
                $row["ORDER_PRICE"] *= $row["ORDER_QUANTITY"];
                $reservations[$row["ORDER_ID"]] = $row;
                continue;
            }

            switch ($reservations[$row["ORDER_ID"]])
            {
                case 0: // seen
                    switch ($row["STATE"])
                    {
                        case 0: // seen
                        case 2: // cancelled
                        case 3: // cancelled by customer
                        case 4: // fulfilled
                            // invalid state;
                            break;
                    }
                    break;
                    
                case 1: // new
                    switch ($row["STATE"])
                    {
                        case 0: // seen
                        case 1: // new
                            // invalid state
                            break;
                            break;
                    }
                    break;
                case 2: // cancelled
                    switch ($row["STATE"])
                    {
                        case 1: // new
                        case 3: // cancelled by customer
                            // invalid state;
                            break;
                    }
                    break;
                    
                case 3: // cancelled by customer
                    switch ($row["STATE"])
                    {
                        case 0: // seen
                            $reservations[$row["ORDER_ID"]]["STATE"] = 0; 
                            break;  
                        case 1: // new
                            // invalid state
                            break;
                        case 3: // cancelled by customer
                            $reservations[$row["ORDER_ID"]]["STATE"] = 3; 
                            break;   
                        case 4: // fulfilled
                            $reservations[$row["ORDER_ID"]]["STATE"] = 4; 
                            break;
                    }
                    break;
                    
                case 4: // fulfilled 
                    switch ($row["STATE"])
                    {
                        case 0: // seen
                            $reservations[$row["ORDER_ID"]]["STATE"] = 0; 
                            break;  
                        case 1: // new
                            // invalid state
                            break;
                        case 3: // cancelled by customer
                            $reservations[$row["ORDER_ID"]]["STATE"] = 3; 
                            break;
                    }
                break;
            }
            $reservations[$row["ORDER_ID"]]["ORDER_QUANTITY"]+=$row["ORDER_QUANTITY"];
            $reservations[$row["ORDER_ID"]]["ORDER_PRICE"]+=$row["ORDER_QUANTITY"]*$row["ORDER_PRICE"];
        }   

        usort($reservations,'compare_orders');
        foreach($reservations as $row) {
            $row["ORDER_PRICE"] = number_format($row["ORDER_PRICE"],2,',','');
            echo create_item($row, file_get_contents("other/order_template.html"));
        }
    }
?>

<html lang="en">
    <head>
        <meta charset="utf-8">
        <link href="styles/orders.css" rel="stylesheet">
    </head>
    <body>
        <header id="fixed_header">
            <div class="nav2">
                <div><a href="logout.php"><img src="img/log-out.png"></a></div>
            </div>
            <a href="index.php"><h1>Žraadlotäika</h1></a>
            <nav>
                <a href="customer.php">Customer's site</a>
                <a href="orders.php" class="opened">Our orders</a>
                <a href="restaurant.php">Our meals</a>
            </nav>
        </header>
        
        <section id="main">
            <section id="centered_main">
                <?php get_results()?>
            </section>
        </section>

        <?php include("footer.html"); ?>
    </body>
</html>