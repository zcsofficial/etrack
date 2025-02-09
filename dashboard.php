<?php
session_start();
include 'config.php';

// Check if the user is logged in (user_id should be stored in session after login)
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id']; // Fetch logged-in user's ID from session

// Session timeout: Logout after 30 minutes of inactivity
$timeout_duration = 1800; // 30 minutes in seconds
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}
$_SESSION['last_activity'] = time(); // Update last activity time

// Fetch Total Money & Expense Log for the logged-in user
$total_money = 0;

// Get Total Money from Expenses
$result = $conn->query("SELECT SUM(amount) AS total FROM expenses WHERE user_id = $user_id");
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $total_money = $row['total'] ?? 0;
}

// Get Expense Ledger
$ledger = $conn->query("SELECT * FROM expenses WHERE user_id = $user_id ORDER BY created_at DESC");

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense Tracker Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body {
            background-color: #1a1a1a;
            color: #fff;
        }
        .container {
            max-width: 800px;
            margin: 40px auto;
        }
        .total-money {
            font-size: 24px;
            font-weight: bold;
            color: #8A2BE2;
        }
        .card {
            background: #121212;
            color: #fff;
            border: 1px solid #8A2BE2;
            box-shadow: 0 0 10px #8A2BE2;
        }
        .btn-add, .btn-logout {
            background-color: #8A2BE2;
            color: #fff;
            border-radius: 20px;
        }
        .btn-add:hover, .btn-logout:hover {
            background-color: #6a1bb1;
        }
        .ledger {
            background: #2a2a2a;
            padding: 10px;
            border-radius: 10px;
        }
        .expense-icon {
            font-size: 18px;
            margin-right: 10px;
        }
    </style>
</head>
<body>

<div class="container text-center">
    <h2><i class="fa-solid fa-wallet"></i> Expense Tracker</h2>

    <!-- Total Money Display -->
    <div class="card p-3 my-3">
        <h4>Total Money</h4>
        <div class="total-money">
            <i class="fa-solid fa-coins"></i> ₹<?php echo number_format($total_money, 2); ?>
        </div>
    </div>

    <!-- Add Expense Button -->
    <button class="btn btn-add px-4 py-2" onclick="window.location.href='add.php'">
        <i class="fa-solid fa-plus"></i> Add Expense
    </button>

    <!-- Ledger (Transaction Log) -->
    <h3 class="mt-4"><i class="fa-solid fa-book"></i> Ledger</h3>
    <div class="ledger mt-2">
        <?php if ($ledger->num_rows > 0): ?>
            <ul class="list-group">
                <?php while ($row = $ledger->fetch_assoc()): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center bg-dark text-white">
                        <span>
                            <i class="expense-icon <?php echo ($row['amount'] < 0) ? 'fa-solid fa-minus-circle text-danger' : 'fa-solid fa-plus-circle text-success'; ?>"></i>
                            <?php echo $row['expense_name']; ?>
                        </span>
                        <span class="fw-bold">
                            <?php echo ($row['amount'] < 0) ? '-' : '+'; ?>₹<?php echo number_format(abs($row['amount']), 2); ?>
                        </span>
                    </li>
                <?php endwhile; ?>
            </ul>
        <?php else: ?>
            <p>No transactions yet.</p>
        <?php endif; ?>
    </div>

    <!-- Logout Button -->
    <form method="POST" action="logout.php" class="mt-4">
        <button type="submit" class="btn btn-logout px-4 py-2">
            <i class="fa-solid fa-sign-out-alt"></i> Logout
        </button>
    </form>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>
