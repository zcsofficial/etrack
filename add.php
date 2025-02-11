<?php
session_start();
include 'config.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch logged-in user's email
$stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$user_email = $user['email'];
$stmt->close();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $expense_name = trim(htmlspecialchars($_POST['expense_name']));
    $amount = trim($_POST['amount']);
    $category = trim(htmlspecialchars($_POST['category']));
    $type = trim($_POST['type']); // "add" or "deduct"

    // Error handling
    if (empty($expense_name) || empty($amount) || !is_numeric($amount) || $amount <= 0) {
        $_SESSION['error'] = "Please enter a valid expense name and amount.";
        header("Location: add.php");
        exit();
    }

    // Convert amount to negative if it's a deduction
    $amount = ($type == "deduct") ? -abs(intval($amount)) : abs(intval($amount));

    // Insert into database
    $stmt = $conn->prepare("INSERT INTO expenses (user_id, expense_name, amount, category) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isds", $user_id, $expense_name, $amount, $category);

    if ($stmt->execute()) {
        // Send email notification
        sendExpenseEmail($user_email, $expense_name, $amount, $category, $type);

        $_SESSION['success'] = "Expense added successfully!";
        header("Location: dashboard.php");
        exit();
    } else {
        $_SESSION['error'] = "Failed to add expense. Try again.";
        header("Location: add.php");
        exit();
    }

    $stmt->close();
}

// Function to send email
function sendExpenseEmail($to, $expense_name, $amount, $category, $type) {
    $subject = "Expense Tracker - New " . ($type == "deduct" ? "Deduction" : "Addition");

    // Read email template and replace placeholders
    $template = file_get_contents('email.html');
    $message = str_replace(
        ['{{expense_name}}', '{{amount}}', '{{category}}', '{{type}}'],
        [$expense_name, "₹" . abs($amount), $category, ($type == "deduct" ? "Deduction" : "Addition")],
        $template
    );

    // Headers for HTML email
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: Expense Tracker <no-reply@expensetracker.com>" . "\r\n";

    // Send email
    mail($to, $subject, $message, $headers);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Expense</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body { background-color: #1a1a1a; color: #fff; }
        .container { max-width: 500px; margin: 40px auto; }
        .card { background: #121212; border: 1px solid #8A2BE2; box-shadow: 0 0 10px #8A2BE2; color: #fff; }
        .btn-submit { background-color: #8A2BE2; color: #fff; border-radius: 20px; }
        .btn-submit:hover { background-color: #6a1bb1; }
        .notification { position: fixed; top: 10px; right: 10px; padding: 10px 20px; color: #fff; border-radius: 5px; display: none; z-index: 1000; }
        .error { background: red; }
        .success { background: green; }
    </style>
</head>
<body>

<!-- Error Notification -->
<?php if (isset($_SESSION['error'])): ?>
    <div class="notification error" id="errorMsg">
        <i class="fa-solid fa-exclamation-circle"></i> <?php echo $_SESSION['error']; ?>
    </div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<!-- Success Notification -->
<?php if (isset($_SESSION['success'])): ?>
    <div class="notification success" id="successMsg">
        <i class="fa-solid fa-check-circle"></i> <?php echo $_SESSION['success']; ?>
    </div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<div class="container">
    <div class="card p-4">
        <h3 class="text-center"><i class="fa-solid fa-plus-circle"></i> Add Expense</h3>
        <form method="POST" action="">
            <div class="mb-3">
                <label class="form-label">Expense Name</label>
                <input type="text" class="form-control" name="expense_name" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Amount (₹)</label>
                <input type="number" class="form-control" name="amount" required min="1">
            </div>
            <div class="mb-3">
                <label class="form-label">Category</label>
                <select class="form-select" name="category">
                    <option value="Food">Food</option>
                    <option value="Transport">Transport</option>
                    <option value="Shopping">Shopping</option>
                    <option value="Bills">Bills</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Type</label>
                <select class="form-select" name="type">
                    <option value="add">Add Money</option>
                    <option value="deduct">Deduct Money</option>
                </select>
            </div>
            <button type="submit" class="btn btn-submit w-100"><i class="fa-solid fa-paper-plane"></i> Submit</button>
        </form>
    </div>
</div>

<script>
    $(document).ready(function() {
        $(".notification").fadeIn().delay(3000).fadeOut();
    });
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>
