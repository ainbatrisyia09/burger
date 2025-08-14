<?php
session_start();

// Simple authentication (you should improve this for production)
$admin_username = "admin";
$admin_password = "quickbite123"; // Change this password!

// Handle login
if (isset($_POST['login'])) {
    if ($_POST['username'] == $admin_username && $_POST['password'] == $admin_password) {
        $_SESSION['admin_logged_in'] = true;
        header("Location: admin.php");
        exit();
    } else {
        $login_error = "Invalid username or password!";
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin.php");
    exit();
}

// Check if admin is logged in
$is_logged_in = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'];

// Database configuration - UPDATE THESE WITH YOUR DATABASE DETAILS
$servername = "localhost";
$username = "your_db_username";    // Change this
$password = "your_db_password";    // Change this
$dbname = "your_database_name";    // Change this

$conn = null;
$bookings = [];
$stats = [];

if ($is_logged_in) {
    try {
        // Create connection
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Handle booking status updates
        if (isset($_POST['update_status'])) {
            $booking_id = $_POST['booking_id'];
            $new_status = $_POST['status'];
            
            $stmt = $conn->prepare("UPDATE bookings SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $booking_id]);
            
            $success_message = "Booking status updated successfully!";
        }
        
        // Handle booking deletion
        if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
            $booking_id = $_GET['delete'];
            
            $stmt = $conn->prepare("DELETE FROM bookings WHERE id = ?");
            $stmt->execute([$booking_id]);
            
            $success_message = "Booking deleted successfully!";
        }
        
        // Fetch all bookings
        $stmt = $conn->prepare("SELECT * FROM bookings ORDER BY booking_date DESC, booking_time DESC");
        $stmt->execute();
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Fetch statistics
        $stats_queries = [
            'total_bookings' => "SELECT COUNT(*) as count FROM bookings",
            'pending_bookings' => "SELECT COUNT(*) as count FROM bookings WHERE status = 'pending'",
            'confirmed_bookings' => "SELECT COUNT(*) as count FROM bookings WHERE status = 'confirmed'",
            'today_bookings' => "SELECT COUNT(*) as count FROM bookings WHERE booking_date = CURDATE()",
        ];
        
        foreach ($stats_queries as $key => $query) {
            $stmt = $conn->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats[$key] = $result['count'];
        }
        
    } catch(PDOException $e) {
        $db_error = "Database connection failed: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QuickBite - Admin Dashboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }

        /* Login Form Styles */
        .login-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 2rem;
        }

        .login-form {
            background: white;
            padding: 3rem;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }

        .login-form h2 {
            text-align: center;
            color: #333;
            margin-bottom: 2rem;
            font-size: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #555;
            font-weight: 600;
        }

        .form-group input {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: #ff6b35;
        }

        .login-btn {
            width: 100%;
            background: linear-gradient(135deg, #ff6b35, #ff8e53);
            color: white;
            border: none;
            padding: 1rem;
            border-radius: 10px;
            font-size: 1.1rem;
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        .login-btn:hover {
            transform: translateY(-2px);
        }

        .error {
            color: #dc3545;
            text-align: center;
            margin-top: 1rem;
            padding: 0.5rem;
            background: #f8d7da;
            border-radius: 5px;
        }

        /* Dashboard Styles */
        .dashboard {
            padding: 2rem;
        }

        .header {
            background: white;
            padding: 1.5rem 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: #333;
            font-size: 2.5rem;
        }

        .logout-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 0.8rem 1.5rem;
            border-radius: 25px;
            cursor: pointer;
            text-decoration: none;
            font-weight: 500;
            transition: background 0.3s ease;
        }

        .logout-btn:hover {
            background: #c82333;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-number {
            font-size: 3rem;
            font-weight: bold;
            color: #ff6b35;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #666;
            font-size: 1.1rem;
            font-weight: 500;
        }

        /* Bookings Table */
        .bookings-section {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .section-title {
            font-size: 2rem;
            color: #333;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-confirmed {
            background: #d4edda;
            color: #155724;
        }

        .status-completed {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.4rem 0.8rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.8rem;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #007bff;
            color: white;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-warning {
            background: #ffc107;
            color: #212529;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            text-align: center;
        }

        .no-bookings {
            text-align: center;
            color: #666;
            padding: 3rem;
            font-size: 1.2rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .header h1 {
                font-size: 2rem;
            }

            .dashboard {
                padding: 1rem;
            }

            .action-buttons {
                flex-direction: column;
            }

            table {
                font-size: 0.8rem;
            }

            th, td {
                padding: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <?php if (!$is_logged_in): ?>
        <!-- Login Form -->
        <div class="login-container">
            <form class="login-form" method="POST">
                <h2>🍔 QuickBite Admin</h2>
                
                <div class="form-group">
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" name="login" class="login-btn">Login</button>
                
                <?php if (isset($login_error)): ?>
                    <div class="error"><?php echo $login_error; ?></div>
                <?php endif; ?>
            </form>
        </div>
        
    <?php else: ?>
        <!-- Admin Dashboard -->
        <div class="dashboard">
            <div class="header">
                <h1>🍔 QuickBite Admin Dashboard</h1>
                <a href="?logout=1" class="logout-btn">Logout</a>
            </div>

            <?php if (isset($success_message)): ?>
                <div class="success-message"><?php echo $success_message; ?></div>
            <?php endif; ?>

            <?php if (isset($db_error)): ?>
                <div class="error" style="margin-bottom: 2rem; padding: 1rem; border-radius: 10px;">
                    <?php echo $db_error; ?>
                    <br><small>Please check your database configuration in the PHP file.</small>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <?php if ($conn): ?>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['total_bookings']; ?></div>
                        <div class="stat-label">Total Bookings</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['pending_bookings']; ?></div>
                        <div class="stat-label">Pending Bookings</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['confirmed_bookings']; ?></div>
                        <div class="stat-label">Confirmed Bookings</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['today_bookings']; ?></div>
                        <div class="stat-label">Today's Bookings</div>
                    </div>
                </div>

                <!-- Bookings Table -->
                <div class="bookings-section">
                    <h2 class="section-title">Table Bookings Management</h2>
                    
                    <?php if (count($bookings) > 0): ?>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Customer Name</th>
                                        <th>Phone</th>
                                        <th>Email</th>
                                        <th>Party Size</th>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Status</th>
                                        <th>Special Requests</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bookings as $booking): ?>
                                        <tr>
                                            <td><?php echo $booking['id']; ?></td>
                                            <td><?php echo htmlspecialchars($booking['customer_name']); ?></td>
                                            <td><?php echo htmlspecialchars($booking['customer_phone']); ?></td>
                                            <td><?php echo htmlspecialchars($booking['customer_email'] ?? 'N/A'); ?></td>
                                            <td><?php echo $booking['party_size']; ?> people</td>
                                            <td><?php echo date('M d, Y', strtotime($booking['booking_date'])); ?></td>
                                            <td><?php echo date('g:i A', strtotime($booking['booking_time'])); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo $booking['status'] ?? 'pending'; ?>">
                                                    <?php echo ucfirst($booking['status'] ?? 'pending'); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($booking['special_requests'] ?: 'None'); ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <!-- Status Update Form -->
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                        <select name="status" onchange="this.form.submit()" class="btn btn-primary">
                                                            <option value="pending" <?php echo ($booking['status'] ?? 'pending') == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                            <option value="confirmed" <?php echo ($booking['status'] ?? '') == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                                            <option value="completed" <?php echo ($booking['status'] ?? '') == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                            <option value="cancelled" <?php echo ($booking['status'] ?? '') == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                        </select>
                                                        <input type="hidden" name="update_status" value="1">
                                                    </form>
                                                    
                                                    <a href="?delete=<?php echo $booking['id']; ?>" 
                                                       class="btn btn-danger" 
                                                       onclick="return confirm('Are you sure you want to delete this booking?')">
                                                        Delete
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="no-bookings">
                            <p>📝 No bookings found.</p>
                            <p>Bookings will appear here once customers start making reservations.</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <script>
        // Auto-refresh the page every 30 seconds to show new bookings
        setInterval(function() {
            if (document.querySelector('.dashboard')) {
                // Only refresh if we're on the dashboard (not login page)
                location.reload();
            }
        }, 30000);

        // Confirm deletion
        function confirmDelete(id, customerName) {
            return confirm(`Are you sure you want to delete the booking for ${customerName}?`);
        }
    </script>
</body>
</html>