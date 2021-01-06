<?php
    require_once 'common.php'; // General function file
    
    $parent = "login_and_registration.php"; // Path to the parent file 

    // Checks the form validation 
    if (!$_POST || !isset($_POST["restaurant_email"]) || !isset($_POST["restaurant_password"])) {
        return_with_error('Please fill out the required fields.', $parent);
    }

    $conn = connect();

    // Prepare statement to look for specified email:
    $stmt = $conn->prepare(
        "SELECT restaurant_id, restaurant_email, restaurant_password 
            FROM restaurant
            WHERE restaurant_email=?;") 
        or fatal_error("Failed to prepare(email lookup): " . $conn->error);

    // Insert specified email:
    $stmt->bind_param('s', $_POST["restaurant_email"]);
    if (!$stmt->execute())
        fatal_error("Failed to execute: " . $stmt->error);

    $result = $stmt->get_result() or fatal_error("Failed to get results(email lookup): " . $conn->error);

    // Checks whether any item was found
    if ($result->num_rows == 0) {
        return_with_error('No user found with that email!', $parent);
    }

    $row = $result -> fetch_assoc();
    $conn->close();

    // Checks the password
    if ($row["restaurant_password"] != hash("sha256", $_POST["restaurant_password"])) {
        return_with_error('Wrong password!', $parent);
    }

    $_SESSION["restaurant_email"] = $row["restaurant_email"];
    header("Location: restaurant.php");
?>