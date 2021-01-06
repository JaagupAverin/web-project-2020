<?php

    require_once "common.php";
    $parent = 'restaurant.php'; // location of file for error returning

    $img_dir = __DIR__."/img_database/";
    if (!file_exists($img_dir)) {
        mkdir($img_dir, 0755, true);
    }
    
    if (!$_POST) {
        return_with_error("Chyba v přijetí požadavku.", $parent);
    }

    // Check the restaurant
    // checks the input data
    if (!isset($_SESSION["restaurant_email"])) {
        return_with_error("Unsuccessful login!", "login_and_registration.php");
    }

    $conn = connect();
    
    // Finds the selected restaurant item via email address
    $sql = "SELECT restaurant_id FROM restaurant
                WHERE restaurant_email='" .  $_SESSION["restaurant_email"] ."';";
    $result = $conn->query($sql); 
    
    // Checks whether any item was found
    if ($result->num_rows == 0) {
        return_with_error('No user found with that email!', $parent);
    }
    
    $row = $result->fetch_assoc();

    if (isset($_POST["existing_item"]) && !empty($_POST["existing_item"]))
    {
        $sql="SELECT restaurant_id FROM food WHERE food_id=".$_POST["existing_item"];
        $result = $conn->query($sql);
        if ($result->num_rows == 0) {
            return_with_error('Item was not found for modifying', $parent);
        }
        
        $check = $result->fetch_assoc();
        if ($check['restaurant_id']!=$row['restaurant_id'])
            return_with_error("Unauthorized manipulation!", $parent);
        if ($_POST["action"]=="Delete item")
        {
            $sql="UPDATE food SET food_expiration= '1970-03-03 00:00' WHERE food_id=".$_POST["existing_item"];
            $success = $conn->query($sql);
            if (!$success)
                fatal_error("Error(1): " . $conn->error);
            header("Location: restaurant.php");
            die();
        }
    }

    $keys = array (// defining item's attributes
        'food_name',
        'food_price',
        'food_sale',
        'food_quantity',
        'food_expiration',
        'food_exp_time',
        'food_tags'
    ); 
    $data = array_fill_keys($keys, NULL); // item
    $tags=array();

    
    // checking and filling the input information
    foreach ($data as $key => &$value) {
        if (!isset($_POST[$key]) && $key!="food_tags") {
            return_with_error("Value not set for: $key", $parent);
        }

        $value = $_POST[$key];
        switch ($key) {
            case 'food_name':
                if ( !preg_match("/[0-9a-zA-Z '\-]/", $value))
                    return_with_error("Invalid characters in the name!", $parent);
                break;
            case 'food_price':
                $value = floatval($value);
                if ($value <= 0)
                    return_with_error("Price must be positive", $parent);
                break;
            case 'food_sale':
                $value = intval($value);
                if ($value < 0 || $value > 100)
                    return_with_error("Sale must be within [0, 100]", $parent);
                break;
            case 'food_quantity':
                $value = intval($value);
                if ($value <= 0)
                    return_with_error("Quantity must be positive", $parent);
                break;
            case 'food_expiration':
                if (!preg_match("/20[0-9][0-9]-[0-1][0-9]-[0-3][0-9]/",$value))
                    return_with_error("Wrong date format!", $parent);
                break;
            case 'food_exp_time':
                if (!preg_match("/[0-2][0-9]:[0-5][0-9]/",$value))
                    return_with_error("Wrong time format!", $parent);
                $data["food_expiration"] .= " ".$value;
                break;
                case 'food_tags':
                    foreach ($value as $tag_value) {
                        if ( $tag_value < 1 || $tag_value > 5 )
                            return_with_error("Invalid tag name", $parent);
                        array_push($tags, $tag_value);
                    }
            default:
                break;
        }
    }
        
    // deleting connected attribute
    unset($data["food_exp_time"]);
    unset($data['food_tags']);

    // file handling
    $data["food_image"] = check_image();
    $data["restaurant_id"] = $row["restaurant_id"];

    if (!isset($_POST["existing_item"]) || empty($_POST["existing_item"])) {// if adding new element
        if (!$data["food_image"]) 
            return_with_error("Unsuccessful image upload", $parent);
            
        // Add new the row to the restaurant table
        $sql = "INSERT INTO food (" . implode(", ", array_keys($data)) . ")
                VALUES ('" . implode("', '", $data) . "');";
        echo "BAM";
    }
    else {
        if (!$data["food_image"])
            unset($data["food_image"]);
        
        $sql = "UPDATE food SET ";
        $con = "";
        foreach($data as $key => $value){
            if ($key=="food_name"||$key=="food_expiration"||$key=="food_image")
                $value="'".$value."'";
            $sql.=$con.$key."=".$value;
            $con=",";
        }
        $sql .= " WHERE food_id=".$_POST["existing_item"];
        echo "BOOM";
    }
    echo $sql;
    echo $conn->error;
    $success = $conn->query($sql);
    if (!$success)
        fatal_error("Error(2): " . $conn->error);
    
    if (isset($_POST["existing_item"]) && !empty($_POST["existing_item"])) {
        $sql = "DELETE FROM food_tag WHERE food_id=".$_POST["existing_item"];
        if (!$conn->query($sql))
            fatal_error("Error(3): " . $conn->error);
        $food_id=$_POST["existing_item"];
    }
    else {
        $food_id = $conn->insert_id;
    }
    if (!empty($tags)) {
        $sql = "INSERT INTO food_tag (food_id, tag_id) VALUES ";
        $pre = "(";
        foreach ($tags as $value){
            $sql .= $pre."$food_id,$value)";
            $pre = ",(";
        }
        $success = $conn->query($sql);
        if (!$success)
            return_with_error("Unsuccesful tag input." . $conn->error, $parent);

    }
    header("Location: restaurant.php");

    function check_image() {
        global $img_dir;
        if (!file_exists($_FILES["food_image"]["tmp_name"]) ||
            !is_uploaded_file($_FILES["food_image"]["tmp_name"])) {
            return false;
        }
        
        if (getimagesize($_FILES["food_image"]["tmp_name"]) === false) {
            return false;
        }
        
        $image_name = "img" . date("Y-m-d-h-i-s") ."." . strtolower(pathinfo($_FILES["food_image"]["name"], PATHINFO_EXTENSION)); 

        if (!move_uploaded_file($_FILES["food_image"]["tmp_name"], "$img_dir" . $image_name)) { 
            return false;
        }
        chmod("$img_dir".$image_name,0755);
        
        return $image_name;
    }
    
?>
