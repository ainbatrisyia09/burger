<?php
// Database connection settings
$servername = "localhost";  // usually localhost
$username = "root";  // <-- Replace with your DB username
$password = "";  // <-- Replace with your DB password
$dbname = "burger";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['form_type']) && $_POST['form_type'] === 'table_booking') {
    
    // Get and sanitize input data
    $customerName = $conn->real_escape_string(trim($_POST['customerName']));
    $customerPhone = $conn->real_escape_string(trim($_POST['customerPhone']));
    $customerEmail = $conn->real_escape_string(trim($_POST['customerEmail'] ?? ''));
    $partySize = (int)$_POST['partySize'];
    $bookingDate = $conn->real_escape_string($_POST['bookingDate']);
    $bookingTime = $conn->real_escape_string($_POST['bookingTime']);
    $specialRequests = $conn->real_escape_string(trim($_POST['specialRequests'] ?? ''));

    // Prepare SQL query
    $sql = "INSERT INTO bookings 
            (customer_name, customer_phone, customer_email, party_size, booking_date, booking_time, special_requests) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";

    // Prepare statement
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        die("Prepare failed: " . $conn->error);
    }

    // Bind parameters and execute
    $stmt->bind_param(
        "sssisss", 
        $customerName, 
        $customerPhone, 
        $customerEmail, 
        $partySize, 
        $bookingDate, 
        $bookingTime, 
        $specialRequests
    );

    if ($stmt->execute()) {
        // Redirect to index.html with success message
        header("Location: index.html?booking=success");
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
}

$conn->close();
?>
