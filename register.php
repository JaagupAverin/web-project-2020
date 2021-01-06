<?php
    require_once 'common.php'; // General function file

    $parent = "login_and_registration.php"; // Path to the parent file 

    if ($_POST)
    {
        $keys = array // Columns of restaurant table
            (
                'restaurant_email',
                'restaurant_password',
                'restaurant_name',
                'restaurant_website'
            );
        $data = array_fill_keys($keys, NULL); // Values of new restaurant item

        // Checks the input values
        foreach ($data as $key => &$value)
        {
            if (!isset($_POST[$key]))
                return_with_error("Value not set for: $key", $parent);

            $value = $_POST[$key];
            switch ($key) {
                case 'restaurant_email':
                    if (!checkTheEmail($value))
                        return_with_error("Invalid email!", $parent);
                    break;
                case 'restaurant_password':
                    if (!checkThePassword($value))
                        return_with_error("Invalid password!", $parent);
                    $value = hash("sha256", $value);
                    break;
                case 'restaurant_website':
                    if (!preg_match("~^(?:f|ht)tps?://~i", $value)) // Insert HTTP if needed:
                        $value = "http://" . $value;
                    if (!checkTheURL($value))
                        return_with_error("Invalid website URL!", $parent);
                    break;
                default:
                    break;
            }
        }

        // Connects to the database
        $conn = connect();

        // Prepare insertion statement:
        $in = str_repeat("?,", count($data) - 1) . '?';
        $stmt = $conn->prepare("INSERT INTO restaurant (" . implode(", ", $keys) . ") VALUES ($in);")
            or fatal_error("Failed to prepare(restaurant insertion): " . $conn->error);

        // Insert the data into the statement:
        $stmt->bind_param(str_repeat('s', count($data)), ...array_values($data));
        if (!$stmt->execute())
            fatal_error("Failed to execute(restaurant insertion): " . $stmt->error);
        
        $conn->close();

        $_SESSION['restaurant_email'] = $data['restaurant_email'];
        header("Location: restaurant.php");
    }

    /**
     * Checks the accuracy and uniqueness of the input email address
     * 
     * If the address is not unique ends with displaying the error
     * 
     * @param string $value The input value of email address
     * @return bool 
     */
    function checkTheEmail($value)
    {
        // Checks the format of the email adress
        if (!preg_match("/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/", $value))
            return false;
        
        // Connects to the database
        $conn = connect();

        // Asks for the number of items with the same email adress
        $stmt = $conn->prepare(
            "SELECT COUNT(*) as count FROM restaurant " .
            "WHERE restaurant_email=?;") or fatal_error("Failed to prepare(email check): " . $conn->error);

        $stmt->bind_param('s', $value);
        if (!$stmt->execute())
            fatal_error("Failed to execute: " . $stmt->error);

        $result = $stmt->get_result() or fatal_error("Failed to get results(email check): " . $conn->error);
        
        $data = $result -> fetch_assoc();
        $count = $data['count'];
        $conn->close();
        // If the adress is not unique displays error 
        if ($count != '0')
        {
            global $parent;
            return_with_error('Email already taken!', $parent);
        }
        
        return true;
    }

    /**
     * Checks the minimum length of the password
     * 
     * @param string $value The input value of password
     * 
     * @return bool
     */
    function checkThePassword($value)
    {
        return strlen($value) >= 8;
    }

    /**
     * Checks the right format of the URL
     * 
     * @param string $value The input value of the URL
     * 
     * @return bool
     */
    function checkTheURL($value)
    {
        return preg_match('/(?:https?:\/\/)?(?:[a-zA-Z0-9.-]+?\.(?:[a-zA-Z])|\d+\.\d+\.\d+\.\d+)/', $value);
    }
?>