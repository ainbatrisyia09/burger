<?php
session_start();

// Simple authentication (change for production)
$admin_username = "admin";
$admin_password = "quickbite123";

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

$is_logged_in = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'];

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "burger";

$conn = null;
$bookings = [];
$stats = [
    'total_bookings' => 0,
    'pending_bookings' => 0,
    'confirmed_bookings' => 0,
    'today_bookings' => 0,
];

// Only connect & process if logged in
if ($is_logged_in) {
    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Handle status update POST with redirect
        if (isset($_POST['update_status'])) {
            $booking_id = $_POST['booking_id'] ?? null;
            $new_status = $_POST['status'] ?? null;
            $valid_statuses = ['pending', 'confirmed', 'cancelled'];

            if ($booking_id && $new_status && in_array($new_status, $valid_statuses)) {
                $stmt = $conn->prepare("UPDATE bookings SET status = ? WHERE id = ?");
                $stmt->execute([$new_status, $booking_id]);

                $_SESSION['success_message'] = "Booking status updated successfully!";
                header("Location: admin.php");
                exit();
            }
        }

        // Handle delete GET
        if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
            $booking_id = $_GET['delete'];
            $stmt = $conn->prepare("DELETE FROM bookings WHERE id = ?");
            $stmt->execute([$booking_id]);
            $_SESSION['success_message'] = "Booking deleted successfully!";
            header("Location: admin.php");
            exit();
        }

        // Fetch bookings
        $stmt = $conn->prepare("SELECT * FROM bookings ORDER BY booking_date DESC, booking_time DESC");
        $stmt->execute();
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch stats
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
            $stats[$key] = $result['count'] ?? 0;
        }

    } catch (PDOException $e) {
        $db_error = "Database connection failed: " . $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>QuickBite - Admin Dashboard</title>
<style>
    /* General resets */
    * {
        box-sizing: border-box;
    }
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: #f7f7f7;
        margin: 0;
        padding: 0;
        color: #333;
    }
    a {
        text-decoration: none;
        color: #007bff;
        transition: color 0.3s ease;
    }
    a:hover {
        color: #0056b3;
    }
    /* Login Container */
    .login-container {
        max-width: 380px;
        margin: 80px auto;
        background: white;
        padding: 30px;
        border-radius: 12px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        text-align: center;
    }
    .login-container h2 {
        margin-bottom: 20px;
        font-weight: 700;
        color: #ff5722;
        font-size: 28px;
    }
    .login-form .form-group {
        margin-bottom: 18px;
        text-align: left;
    }
    .login-form label {
        display: block;
        font-weight: 600;
        margin-bottom: 6px;
    }
    .login-form input[type="text"],
    .login-form input[type="password"] {
        width: 100%;
        padding: 10px 14px;
        border-radius: 6px;
        border: 1px solid #ccc;
        font-size: 16px;
        transition: border-color 0.3s ease;
    }
    .login-form input[type="text"]:focus,
    .login-form input[type="password"]:focus {
        border-color: #ff5722;
        outline: none;
    }
    .login-btn {
        background-color: #ff5722;
        border: none;
        color: white;
        padding: 12px 20px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 18px;
        font-weight: 700;
        width: 100%;
        margin-top: 8px;
        transition: background-color 0.3s ease;
    }
    .login-btn:hover {
        background-color: #e64a19;
    }
    .error {
        margin-top: 12px;
        color: #b00020;
        font-weight: 600;
        font-size: 14px;
    }

    /* Dashboard container */
    .dashboard {
        max-width: 1100px;
        margin: 30px auto 50px;
        padding: 0 15px 40px;
    }
    .header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        border-bottom: 2px solid #ddd;
        padding-bottom: 8px;
    }
    .header h1 {
        font-size: 30px;
        font-weight: 800;
        color: #ff5722;
        margin: 0;
    }
    .logout-btn {
        background-color: #f44336;
        color: white;
        padding: 10px 20px;
        font-weight: 700;
        border-radius: 8px;
        transition: background-color 0.3s ease;
    }
    .logout-btn:hover {
        background-color: #c62828;
    }

    /* Success message */
    .success-message {
        background-color: #d4edda;
        border: 1px solid #c3e6cb;
        color: #155724;
        padding: 12px 18px;
        border-radius: 10px;
        margin-bottom: 20px;
        font-weight: 600;
        box-shadow: 0 2px 6px rgba(0,0,0,0.07);
    }

    /* Error message in dashboard */
    .error {
        background-color: #f8d7da;
        border: 1px solid #f5c6cb;
        color: #721c24;
        padding: 12px 18px;
        border-radius: 10px;
        margin-bottom: 20px;
        font-weight: 600;
    }

    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit,minmax(180px,1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .stat-card {
        background: white;
        padding: 25px 20px;
        border-radius: 14px;
        box-shadow: 0 4px 15px rgba(255,87,34,0.15);
        text-align: center;
        transition: box-shadow 0.3s ease;
    }
    .stat-card:hover {
        box-shadow: 0 8px 30px rgba(255,87,34,0.25);
    }
    .stat-number {
        font-size: 36px;
        font-weight: 900;
        color: #ff5722;
        margin-bottom: 6px;
    }
    .stat-label {
        font-size: 18px;
        font-weight: 700;
        color: #444;
    }

    /* Bookings Section */
    .bookings-section {
        background: white;
        padding: 25px 20px 30px;
        border-radius: 14px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.07);
    }
    .section-title {
        font-size: 26px;
        font-weight: 900;
        margin-bottom: 18px;
        color: #333;
        border-bottom: 3px solid #ff5722;
        padding-bottom: 6px;
        display: inline-block;
    }

    /* Table styling */
    .table-container {
        overflow-x: auto;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 15px;
        min-width: 900px;
    }
    th, td {
        padding: 14px 15px;
        text-align: left;
        border-bottom: 1px solid #eee;
        vertical-align: middle;
    }
    thead th {
        background-color: #ff5722;
        color: white;
        font-weight: 700;
        position: sticky;
        top: 0;
        z-index: 10;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    tbody tr:hover {
        background-color: #fff0e6;
        cursor: default;
    }

    /* Status badges */
    .status-badge {
        display: inline-block;
        padding: 5px 12px;
        border-radius: 12px;
        font-weight: 700;
        color: white;
        text-transform: capitalize;
        font-size: 14px;
        user-select: none;
    }
    .status-pending {
        background-color: #ff9800; /* orange */
    }
    .status-confirmed {
        background-color: #4caf50; /* green */
    }
    .status-cancelled {
        background-color: #f44336; /* red */
    }

    /* Action buttons */
    .action-buttons {
        display: flex;
        gap: 8px;
        align-items: center;
        flex-wrap: wrap;
    }
    .action-buttons form {
        margin: 0;
    }
    select.btn {
        padding: 6px 10px;
        border-radius: 8px;
        border: 1px solid #ddd;
        background: white;
        font-weight: 600;
        color: #555;
        cursor: pointer;
        transition: border-color 0.3s ease;
        min-width: 110px;
        font-size: 14px;
        user-select: none;
    }
    select.btn:hover {
        border-color: #ff5722;
    }
    .btn-danger {
        background-color: #f44336;
        color: white !important;
        border-radius: 8px;
        padding: 8px 14px;
        font-weight: 700;
        font-size: 14px;
        transition: background-color 0.3s ease;
        display: inline-block;
        cursor: pointer;
        user-select: none;
    }
    .btn-danger:hover {
        background-color: #c62828;
    }

    /* Responsive */
    @media (max-width: 700px) {
        .header {
            flex-direction: column;
            align-items: flex-start;
            gap: 12px;
        }
        .stats-grid {
            grid-template-columns: repeat(auto-fit,minmax(140px,1fr));
        }
        .table-container table {
            min-width: 600px;
        }
        select.btn {
            min-width: 90px;
            font-size: 13px;
        }
    }
</style>
</head>
<body>
<?php if (!$is_logged_in): ?>
    <div class="login-container">
        <form class="login-form" method="POST" autocomplete="off">
            <h2>🍔 QuickBite Admin</h2>
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required />
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required />
            </div>
            <button type="submit" name="login" class="login-btn">Login</button>
            <?php if (isset($login_error)): ?>
                <div class="error"><?php echo htmlspecialchars($login_error); ?></div>
            <?php endif; ?>
        </form>
    </div>
<?php else: ?>
    <div class="dashboard">
        <div class="header">
            <h1>🍔 QuickBite Admin Dashboard</h1>
            <a href="?logout=1" class="logout-btn">Logout</a>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="success-message"><?php echo htmlspecialchars($_SESSION['success_message']); ?></div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($db_error)): ?>
            <div class="error" style="margin-bottom: 2rem; padding: 1rem; border-radius: 10px;">
                <?php echo htmlspecialchars($db_error); ?>
                <br /><small>Please check your database configuration.</small>
            </div>
        <?php endif; ?>

        <?php if ($conn): ?>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo htmlspecialchars($stats['total_bookings']); ?></div>
                    <div class="stat-label">Total Bookings</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo htmlspecialchars($stats['pending_bookings']); ?></div>
                    <div class="stat-label">Pending Bookings</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo htmlspecialchars($stats['confirmed_bookings']); ?></div>
                    <div class="stat-label">Confirmed Bookings</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo htmlspecialchars($stats['today_bookings']); ?></div>
                    <div class="stat-label">Today's Bookings</div>
                </div>
            </div>

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
                                        <td><?php echo htmlspecialchars($booking['id'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($booking['customer_name'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($booking['customer_phone'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($booking['customer_email'] ?: 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($booking['party_size'] ?? ''); ?> people</td>
                                        <td><?php echo isset($booking['booking_date']) ? date('M d, Y', strtotime($booking['booking_date'])) : ''; ?></td>
                                        <td><?php echo isset($booking['booking_time']) ? date('g:i A', strtotime($booking['booking_time'])) : ''; ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo htmlspecialchars($booking['status'] ?? 'pending'); ?>">
                                                <?php echo ucfirst(htmlspecialchars($booking['status'] ?? 'pending')); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($booking['special_requests'] ?: 'None'); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="booking_id" value="<?php echo htmlspecialchars($booking['id'] ?? ''); ?>">
                                                    <select name="status" onchange="this.form.submit()" class="btn">
                                                        <option value="pending" <?php echo (($booking['status'] ?? 'pending') == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                                        <option value="confirmed" <?php echo (($booking['status'] ?? '') == 'confirmed') ? 'selected' : ''; ?>>Confirmed</option>
                                                        <option value="cancelled" <?php echo (($booking['status'] ?? '') == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                                    </select>
                                                    <input type="hidden" name="update_status" value="1" />
                                                </form>
                                                <a href="?delete=<?php echo htmlspecialchars($booking['id'] ?? ''); ?>" class="btn-danger" onclick="return confirm('Are you sure you want to delete this booking?');">Delete</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p>No bookings found.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
</body>
</html>
