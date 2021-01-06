<!DOCTYPE html>
<?php
    require_once "common.php";

    $FOOD_ITEM_TEMPLATE = file_get_contents("other/food_item_template.html");
    $BASKET_ITEM_TEMPLATE = file_get_contents("other/basket_item_template.html");

    function create_item_html($id, $name, $restaurant_name, $restaurant_website, $image, $tags, $price, $quantity) {
        $item_html = $GLOBALS["FOOD_ITEM_TEMPLATE"];
        $insert = function($what, $with) use (&$item_html)
        {
            $item_html = str_replace($what, $with, $item_html);
        };

        $insert("'%FOOD_ID%'",                 $id);
        $insert("'%FOOD_NAME%'",               htmlspecialchars($name));
        $insert("'%ESCAPED_FOOD_NAME%'",       escape_quotes($name));
        $insert("'%RESTAURANT_NAME%'",         htmlspecialchars($restaurant_name));
        $insert("'%RESTAURANT_WEBSITE%'",      $restaurant_website);
        $insert("'%IMAGE%'",                   $image);
        $insert("'%TAGS%'",                    $tags);
        $insert("'%PRICE%'",                   $price);
        $insert("'%QUANTITY%'",                $quantity);
        
        return $item_html;
    }

    // First get saved filters from cookies:
    $filters = json_decode(isset($_COOKIE["filters"]) ? $_COOKIE["filters"] : "[]", true);

    // Then overwrite with GET values, or apply defaults if neither exist:
    $get_filter = function($key, $default) use (&$filters) {
        if (isset($_GET[$key]))
            $filters[$key] = $_GET[$key];

        if (!isset($filters[$key]) || empty($filters[$key]))
            $filters[$key] = $default;

        return $filters[$key];
    };

    $query          = $get_filter('query',            "");
    $query_type     = $get_filter('query_type',       "food_name");
    $sort           = $get_filter('sort',             "full_price");
    $no_red_meats   = $get_filter('no_red_meats',     "off");
    $no_white_meats = $get_filter('no_white_meats',   "off");
    $no_fish        = $get_filter('no_fish',          "off");
    $no_gluten      = $get_filter('no_gluten',        "off");
    $no_dairy       = $get_filter('no_dairy',         "off");

    if ($query_type != "food_name" && $query_type != "restaurant_name")
        fatal_error("Invalid query type: ". $query_type);
    if ($sort != "full_price" && $sort != "food_name")
        fatal_error("Invalid sorting: " . $sort);

    $required_tags = [];
    if ($no_red_meats == "on")      array_push($required_tags, 1);
    if ($no_white_meats == "on")    array_push($required_tags, 2);
    if ($no_fish == "on")           array_push($required_tags, 3);
    if ($no_gluten == "on")         array_push($required_tags, 4);
    if ($no_dairy == "on")          array_push($required_tags, 5);

    $conn = connect();
    
    // Fetch ALL quantities separately:
    // Items in the basket are checked against this quantity and may be removed as required.
    $quantities_sql = "SELECT food_id, food_quantity FROM food";
    $quantities = $conn->query($quantities_sql)
        or fatal_error("Failed to query quantities: " . $conn->error);
    $quantities_object = [];
    foreach ($quantities->fetch_all(MYSQLI_ASSOC) as $entry)
        $quantities_object[$entry["food_id"]] = intval($entry["food_quantity"]);

    // Prepare statement with query:
    $stmt = $conn->prepare(
        "SELECT food_id, food_name, food_image, food_quantity,
            food_price * (100 - food_sale) / 100 AS full_price,
            restaurant.restaurant_name, restaurant.restaurant_website
            FROM food INNER JOIN restaurant
                ON restaurant.restaurant_id=food.restaurant_id
            WHERE food_quantity > 0 AND $query_type LIKE ? AND food_expiration > NOW()
            ORDER BY $sort ") or fatal_error("Failed to prepare(food): " . $conn->error);
    
    // If query is empty, match everything:
    if (empty(trim($query)))
        $query = "%";
    else
        $query = "%$query%";

    // Insert query:
    $stmt->bind_param('s', $query);
    if (!$stmt->execute())
        fatal_error("Failed to execute: " . $stmt->error);

    $result = $stmt->get_result() or fatal_error("Failed to get results(food): " . $conn->error);
    $foods = $result->fetch_all(MYSQLI_ASSOC);
    
    $item_count = 0;
    $results_html = "";
    foreach ($foods as $food) {
        // Get food tags and format into list
        $tags_sql = "SELECT tag.tag_id, tag_name
                        FROM tag INNER JOIN food_tag
                            ON tag.tag_id=food_tag.tag_id
                        WHERE food_tag.food_id=" . $food["food_id"];
        
        $tag_result = $conn->query($tags_sql) or fatal_error("Failed to query(tags): " . $conn->error);
        $provided_tags = $tag_result->fetch_all(MYSQLI_ASSOC);

        // Verify all required tags are checked:
        $valid = true;
        foreach ($required_tags as $tag) {
            $valid = false;
            foreach ($provided_tags as $tag2) {
                if ($tag == $tag2["tag_id"])
                    $valid = true;
            }
            if (!$valid)
                break;
        }
        if (!$valid)
            continue; // Skip food; all required tags not provided by it.

        $tags_list = "";
        foreach ($provided_tags as $tag) {
            if (in_array($tag["tag_id"], $required_tags))
                $tags_list .= "<li class='required'>" . $tag["tag_name"] . "</li>";
            else
                $tags_list .= "<li>" . $tag["tag_name"] . "</li>";
        }
        // Get full image path
        $image_full_path = "img_database/" . $food["food_image"];
        
        // Price calculations
        $full_price = number_format(floatval($food["full_price"]), 2, ',', '');

        // Quantity fix for large quantities
        $quantity = $food["food_quantity"];
        $quantity_str = $quantity < 100 ? strval($quantity) : "99+";

        $results_html .= create_item_html(
            $food["food_id"],
            $food["food_name"],
            $food["restaurant_name"],
            $food["restaurant_website"],
            $image_full_path,
            $tags_list,
            $full_price,
            $quantity_str
        );
        ++$item_count;
    }
    $conn->close();

    // Pass quantities to JS:
    $quantities_json = json_encode($quantities_object);
    echo "<script> var quantities = JSON.parse('$quantities_json'); </script>";

    // Pass filters to JS:
    $filters_json = json_encode($filters);
    echo "<script> var filters = JSON.parse('$filters_json'); </script>";

    // Pass Basket-Item template to JS:
    $basket_item_template = addslashes(json_encode($GLOBALS["BASKET_ITEM_TEMPLATE"]));
    echo "<script> var BASKET_ITEM_TEMPLATE = JSON.parse('$basket_item_template'); </script>";
?>

<html lang="en">

    <head>
        <meta charset="utf-8">
        <title>Foods. Cheap and economic.</title>
        <link rel="stylesheet" href="styles/customer.css">
        <script src="scripts/customer.js" type="module" defer></script>
    </head>

    <body>
        <header id="fixed_header">
            <a href="index.php"><h1>Žraadlotäika</h1></a>
        </header>
        
        <section id="main">
        <section id="centered_main">

            <section id="filters_and_results">
                <section id="filters">
                    <form id="filters_form" method="get" onsubmit="onFiltersSubmit()">
                        <h2>Search & filter:</h2>
                        <input type="button" onclick="clearFilters()" id="clear_filters" value="clear all">
                        <hr class="break">

                        <input type="search" name="query" id="query" placeholder="Search here.." placeholder="Search here..">

                        <label class="radio">Search by food
                            <input type="radio" name="query_type" id="by_food" value="food_name" checked>
                            <span></span>
                        </label>
                        <label class="radio">Search by restaurant
                            <input type="radio" name="query_type" id="by_restaurant" value="restaurant_name">
                            <span></span>
                        </label>

                        <hr class="break">

                        <label class="checkbox">No red meats
                            <input type="checkbox" name="no_red_meats" id="no_red_meats" value="on">
                            <span></span>
                        </label>
                          
                        <label class="checkbox">No white meats
                            <input type="checkbox" name="no_white_meats" id="no_white_meats" value="on">
                            <span></span>
                        </label>
                          
                        <label class="checkbox">No fish
                            <input type="checkbox" name="no_fish" id="no_fish" value="on">
                            <span></span>
                        </label>
                          
                        <label class="checkbox">No gluten
                            <input type="checkbox" name="no_gluten" id="no_gluten" value="on">
                            <span></span>
                        </label>

                        <label class="checkbox">No dairy
                            <input type="checkbox" name="no_dairy" id="no_dairy" value="on">
                            <span></span>
                        </label>

                        <hr class="break">

                        <label class="radio">Cheaper first
                            <input type="radio" name="sort" id="cheaper_first" value="full_price" checked>
                            <span></span>
                        </label>
                        <label class="radio">Alphabetical
                            <input type="radio" name="sort" id="alphabetical" value="food_name">
                            <span></span>
                        </label>

                        <hr class="break">

                        <input type="submit" id="submit_button" value="Search">
                    </form>
                </section>
                
                <section id="results">
                    <?php 
                        if ($item_count == 0)
                            echo "<div id='no_results_notification'>There's nothing here. Please be less picky!</div>";
                        else
                            echo $results_html;
                     ?>
                </section>
            </section> 
        </section>

        <div id="basket_toggle_button">
            <div class="open">
                <img src="img/open_basket.png">
                <div id="counter_div"><p id="counter">0</p></div>
            </div>
            <img class="close" src="img/close_basket.png">
        </div>
        <section id="basket">
            <h1>Your items</h1>
            <section id="basket_list">
                <!-- Generated by customer.js -->
            </section>

            <section id="checkout">
                <form method="post" action="checkout.php" onsubmit="onCheckout();">
                    <input type="hidden" id="checkout_list" name="checkout_list">
                    <input type="submit" id="checkout_button" value="To Reservation&#10;(0€)">
                </form>
            </section>
        </section>

        </section>

        <?php include("footer.html"); ?>
    </body>

</html>