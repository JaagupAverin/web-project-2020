<?php
/**
 * RESTAURANT PAGE OF ZRAADLOTAIKA
 *      project for Web Technologies ICD0007 course
 *          Tallinn University of Technology 2020
 *  
 * to do
 *  * add_item popup
 *      * choose picture stays dark when picture is chosen - text change?
 *  * notification popup
 *      * change the css for orders
 *  * orders page
 *      * add the stats for foods
 *      * view items function
 *          * cancelation function
 *          * fulfill function
 *          * mark as seen
 * 
 * 
 */
    require_once 'common.php';

    $conn = connect();
    $restaurant_id = connect_restaurant($conn, $_SESSION["restaurant_email"]);

    echo_error(basename(__FILE__));
    
    /**
     * shows html list of items in the database
     * 
     */
    function get_results() {
        global $conn, $restaurant_id;

        // Finds the selected restaurant item via it's ID
        $sql = "SELECT food_id AS ID, food_name AS NAME, food_image AS IMAGE, 
                    food_price AS ORIGINAL_PRICE, food_sale AS SALE, 
                    food_quantity AS QUANTITY, food_expiration AS EXPIRATION 
                FROM food WHERE restaurant_id=$restaurant_id AND YEAR(food_expiration) > 1970
                 ORDER BY expiration DESC";
        $result = $conn->query($sql); 
        if(!$result) {
            fatal_error("Failed to fetch foods!");
        }
        
        // Checks whether any item was found
        if ($result->num_rows == 0) {
            echo '<div class="empty">Click the + for adding new food item</div>';
        }
        
        foreach ($result->fetch_all(MYSQLI_ASSOC) as $row) {
            $row["IMAGE"] = "img_database/" . $row["IMAGE"];
            $row["TAGS"] = "";
            // Get food tags and format into list
            $sql2 = "SELECT tag.tag_id, tag_name 
                        FROM tag INNER JOIN food_tag
                            ON tag.tag_id=food_tag.tag_id 
                        WHERE food_tag.food_id=" . $row["ID"];
            
            $tag_result = $conn->query($sql2);
            foreach ($tag_result->fetch_all() as $tag)
                $row["TAGS"] .= "<li data-id=" . $tag[0] . ">" . $tag[1] . "</li>";

            $row["NEW_PRICE"] = floatval($row["ORIGINAL_PRICE"]) * (100 - floatval($row["SALE"])) / 100;
            $row["NEW_PRICE"] = number_format($row["NEW_PRICE"], 2, ',', '');
            
            $expiration =  new DateTime($row["EXPIRATION"]);
            $row["TIME"] = explode(" ",$expiration->format('d M Y H:i'));
            $row["DAY"] = $row["TIME"] [0];
            $row["MONTH"] = $row["TIME"] [1];
            $row["YEAR"] = $row["TIME"] [2];
            $row["TIME"] = $row["TIME"] [3];
            $now = new DateTime();
            if ($now > $expiration->modify('-2 minute') || $row["QUANTITY"] == 0)
                $row["ITEM_TYPE"] = 'prelement';
            else $row["ITEM_TYPE"] = 'element';
             
            echo create_item($row, file_get_contents("other/restaurant_item_template.html"));
        }
        $conn->close();
    }

    function get_num_notification() {
        global $conn,$restaurant_id;
        $sql = "SELECT COUNT(*)
                    FROM reservation
                        INNER JOIN food_reservation
                            ON food_reservation.reservation_id=reservation.reservation_id
                        INNER JOIN food
                            ON food.food_id=food_reservation.food_id
                    WHERE reservation_state < 2 AND restaurant_id=".$restaurant_id;
        $result = $conn->query($sql); 
        if(!$result) {
            fatal_error("Failed to fetch notifications information!");
        }
        
        return $result->fetch_assoc()["COUNT(*)"];
    }

    function show_notifications() {
        global $conn, $restaurant_id;
        $sql =  "SELECT customer_name AS customer, access_code, reservation_state AS `state`, reservation.reservation_id AS reservation_id
                    FROM reservation
                        INNER JOIN food_reservation
                            ON food_reservation.reservation_id=reservation.reservation_id
                        INNER JOIN food 
                            ON food.food_id=food_reservation.food_id
                    WHERE reservation_state < 2 AND restaurant_id=$restaurant_id
                    GROUP BY reservation_id, state";
        $result = $conn->query($sql); 
        if(!$result) {
            fatal_error("Failed to fetch notifications!");
        }
        $notifications="";
        foreach ($result->fetch_all(MYSQLI_ASSOC) as $order) {
            switch($order["state"])
            {
                case 0:
                    $order["state"]="created";
                break;
                case 1:
                    $order["state"]="changed";
                break;
            }
            $notifications .="<a class='message' href='reservation.php?a=".$order["access_code"]."&r=".$_SESSION["restaurant_email"]."'>".
            $order["customer"] ." has ".$order["state"]." the order no ".$order["reservation_id"]."</a><hr class='break'>";
        }
        return $notifications;
    }
?>

<html lang="en">
    <head>
        <meta charset="utf-8">
        <link href="styles/restaurant.css" rel="stylesheet">
        <script src="scripts/restaurant.js" type="module" defer></script>
        <title>Meals you offer</title>
    </head>

    <body>

        <header id="fixed_header">
            <div class="nav2">
                <div id="notif-button"><img src="img/notifications.png"></div>
                <span class="orders"><?php echo get_num_notification()?></span>
                <span id="notifications" class="popup-window">
                <?php echo show_notifications()?>
                <a href="orders.php">Show all</a>
                </span>
                <div><a href="logout.php"><img src="img/log-out.png"></a></div>
            </div>

            <a href="index.php"><h1>Žraadlotäika</h1></a>
            
            <nav>
               <a href="customer.php">Customer's site</a>
                <a href="orders.php">Our orders</a>
                <a href="restaurant.php" class="opened">Our meals</a>
            </nav>
        </header>

        
        <section id="main">
        <section id="centered_main">
            <section id="results">
            <!--List of elements selected from meal table-->
            <?php get_results() ?>
        </section>
            
            <!--Button for viewing popup window for adding new element to the meal table-->
            <div id="popup-button" class="fixed">+</div>
            <section id="popup-window">
                <h3 id="popup-heading">Your new food item:</h3>
            <form id="popup-form" method="POST" action="update_item.php" enctype="multipart/form-data">
                <button type="button" id="b_discard" onclick="abortItemModification()">Discard changes</button>

                <input id="item-name" name="food_name" type="text" placeholder="Name of the food" required><br>
                
                <button type="button" class="step-button-left" onclick="this.parentNode.querySelector('#item-price').stepDown()"></button>
                <input id="item-price" name="food_price" type="number" step="0.1" placeholder="Original price" required>
                <button type="button" class="step-button-right" onclick="this.parentNode.querySelector('#item-price').stepUp()"></button>
                
                <span id="new-price" class="new-price">0.00</span>
                <label for="item-sale">Sale:</label>
                <input id="item-sale" name="food_sale" type="range" min="0" max="100" step="5" required><br>
                
                <button type="button" class="step-button-left" onclick="this.parentNode.querySelector('#item-quantity').stepDown()"></button>
                <input id="item-quantity" name="food_quantity" type="number" placeholder="Quantity">
                <button type="button" class="step-button-right" onclick="this.parentNode.querySelector('#item-quantity').stepUp()"></button>

                <div>Choose a picture<input id="item-image" name="food_image" type="file" accept="image/*" required></div>

                <fieldset>
                <legend>Tags:</legend>
                    
                    <label for="tag-no-red-meat" class="checkbox">No red meat
                        <input type="checkbox" id="tag-no-red-meat" name="food_tags[]" value="1">
                        <span></span>
                    </label>

                    
                    <label for="tag-no-white-meat" class="checkbox">No white meat
                        <input type="checkbox" id="tag-no-white-meat" name="food_tags[]" value="2">
                        <span></span>
                    </label>

                    
                    <label for="tag-no-fish" class="checkbox">No fish
                        <input type="checkbox" id="tag-no-fish" name="food_tags[]" value="3">
                        <span></span>
                    </label>

                    
                    <label for="tag-no-gluten" class="checkbox">No gluten
                        <input type="checkbox" id="tag-no-gluten" name="food_tags[]" value="4">
                        <span></span>
                    </label>

                    
                    <label for="tag-no-dairy" class="checkbox">No dairy
                        <input type="checkbox" id="tag-no-dairy" name="food_tags[]" value="5">
                        <span></span>
                    </label>
                </fieldset>
                <br>
                <label for="item-date">Valid until:</label><br>
                <input id="item-date" name="food_expiration" type="date" min="2000-01-01" max="3000-01-01" required>
                <input id="item-time" name="food_exp_time" type="time" required>
                <br>

                <input type="hidden" class="modified_item_id" name="existing_item">

                <input id="b_add" name="action" type="submit" value="Add item">
            </form>

            <form action="update_item.php" method="POST">
                <input type="hidden" class="modified_item_id" name="existing_item">
                <input id="b_delete" name="action" type="submit" value="Delete item">
            </form>

        </section>
        </section>

        <?php include("footer.html"); ?>
    </body>
</img>