<?php

    /**
     * PHP file for general functions used in multiple files
     */

    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    /**
    * Connects to the database of Zraadlotaika.
    * Forwards to error page if connection could not be established.
    * 
    * Uses object-oriented MySQLi for connection to MySQL database
    * 
    * @param
    * 
    * @return mysqli connection | connection error
    */
    function connect() {
        // local host db
        // $servername = "localhost";
        // $username = "root";
        // $password = "Žraadlotäika2020";
        // $dbname = "zraadlotaika";
        
        // it college db
        $servername = "anysql.itcollege.ee";
        $username = "WT11";
        $password = "ylGlaeE0I5";
        $dbname = "WT11";

        $conn = new mysqli($servername, $username, $password, $dbname);

        if ($conn->connect_error) {
            fatal_error("Connect error: " . $conn->connect_error);
        }
        $result = $conn->query("SHOW TABLES;"); 
        // Checks whether any item was found
        if ($result->num_rows == 0) {
            fatal_error("Tables not found.");
        }
        return $conn;
    }

    /**
     * For showing the error message to client
     * 
     * Uses $_SESSION
     * 
     * @param string $error Message to print
     * @param string $location Path to file to parent document
     * 
     * @return void
     */
    function return_with_error($error, $location) {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION["error_$location"] = $error;
        header("Location: $location");
        die();
    }

    /**
     * Used to echo the (optional) error after returning to a page with return_with_error().
     * 
     * @param string $location is the current filename
     * 
     * @return void
     */
    function echo_error($location) {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        if (isset($_SESSION["error_$location"])) {
            echo "<p class='error'>" . $_SESSION["error_$location"] . "</p>";
            unset($_SESSION["error_$location"]);
        }
    }

    /**
     * Used to clear the error after returning to a page with return_with_error().
     * 
     * @param string $location is the current filename
     * 
     * @return void
     */
    function clear_error($location) {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        if (isset($_SESSION["error_$location"])) {
            unset($_SESSION["error_$location"]);
        }
    }

    /**
     * For fatal errors such as failed connection
     * 
     * Uses $_SESSION
     * 
     * @param string $error Message to print
     * 
     * @return void
     */
    function fatal_error($error) {
        $_SESSION["fatal_error"] = $error;
        header("Location: error.php");
        die();
    }

    /**
     * Combines addslashes() and htmlspecialchars() with ENT_QUOTES argument
     * in order to properly quotes in strings.
     * 
     * @param string $str string that must be inserted into HTML
     * 
     * @return string string that may be safely inserted into HTML
     */
    function escape_quotes($str) {
        return htmlspecialchars(addslashes($str), ENT_QUOTES);
    }

    /**
     * Attempt to connect the file with the database row
     * 
     * @param mixed $conn mysqli object
     * @param string $email string from session 
     * 
     * @return int restaurant_id
     */
    function connect_restaurant($conn, $email) {
        // checks the input data
        if (!isset($email) || empty($email)) {
            return_with_error('Please log in first.', "login_and_registration.php");
        }

        // Finds the selected restaurant item via email address
        $sql = "SELECT restaurant_id FROM restaurant
                    WHERE restaurant_email='$email'";
        $result = $conn->query($sql); 

        // Checks whether any item was found
        if ($result->num_rows == 0) {
            return_with_error('No user found with that email!', "login_and_registration.php");
        }

        $row = $result->fetch_assoc();
        return $row["restaurant_id"]; 
    }

    /**
     * For creating an item via HTML template.
     * 
     * @param array $values is an associative array of key:value pairs to insert into the HTML.
     * 
     * @return string $item_html contains the template of the HTML with placeholder keys.
     */
    function create_item($values, $item_html) {
        foreach($values as $key => $value) {
            $item_html = str_replace("'%$key%'", $value, $item_html);
        }
        return $item_html;
    }
?>
