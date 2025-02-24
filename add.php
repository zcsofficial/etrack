<?php
session_start();
include 'config.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php"); // Changed to index.php assuming it's the login page
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
        $error = "Please enter a valid expense name and amount.";
    } else {
        // Convert amount to negative if it's a deduction
        $amount = ($type == "deduct") ? -abs(floatval($amount)) : abs(floatval($amount));

        // Insert into database
        $stmt = $conn->prepare("INSERT INTO expenses (user_id, expense_name, amount, category) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isds", $user_id, $expense_name, $amount, $category);

        if ($stmt->execute()) {
            // Send email notification
            sendExpenseEmail($user_email, $expense_name, $amount, $category, $type);

            $success = "Expense added successfully!";
            // Success will be handled via SweetAlert, redirect happens in JS
        } else {
            $error = "Failed to add expense. Try again.";
            file_put_contents("error_log.txt", date("[Y-m-d H:i:s] ") . "ADD ERROR: Failed to add expense for $user_id - " . $stmt->error . "\n", FILE_APPEND);
        }
        $stmt->close();
    }
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
    <title>Add Expense - Expense Tracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#8A2BE2',
                        secondary: '#121212'
                    },
                    borderRadius: {
                        'button': '8px'
                    }
                }
            }
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Pacifico&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-secondary min-h-screen flex items-center justify-center">
    <div class="bg-gray-800 rounded-lg p-8 w-full max-w-md shadow-lg">
        <h2 class="text-3xl font-bold text-primary font-['Pacifico'] text-center mb-6">Add Expense</h2>
        <form id="addExpenseForm" method="POST" action="add.php" class="space-y-4">
            <div>
                <label class="block text-white mb-2">Expense Name</label>
                <input type="text" name="expense_name" class="w-full bg-gray-700 text-white px-4 py-2 rounded focus:outline-none focus:ring-2 focus:ring-primary" placeholder="Enter expense name" required value="<?php echo isset($_POST['expense_name']) ? htmlspecialchars($_POST['expense_name']) : ''; ?>">
            </div>
            <div>
                <label class="block text-white mb-2">Amount (₹)</label>
                <input type="number" name="amount" class="w-full bg-gray-700 text-white px-4 py-2 rounded focus:outline-none focus:ring-2 focus:ring-primary" placeholder="Enter amount" required min="1" step="0.01" value="<?php echo isset($_POST['amount']) ? htmlspecialchars($_POST['amount']) : ''; ?>">
            </div>
            <div>
                <label class="block text-white mb-2">Category</label>
                <select name="category" class="w-full bg-gray-700 text-white px-4 py-2 rounded focus:outline-none focus:ring-2 focus:ring-primary">
                    <option value="Food" <?php echo isset($_POST['category']) && $_POST['category'] == 'Food' ? 'selected' : ''; ?>>Food</option>
                    <option value="Transport" <?php echo isset($_POST['category']) && $_POST['category'] == 'Transport' ? 'selected' : ''; ?>>Transport</option>
                    <option value="Shopping" <?php echo isset($_POST['category']) && $_POST['category'] == 'Shopping' ? 'selected' : ''; ?>>Shopping</option>
                    <option value="Bills" <?php echo isset($_POST['category']) && $_POST['category'] == 'Bills' ? 'selected' : ''; ?>>Bills</option>
                    <option value="Other" <?php echo isset($_POST['category']) && $_POST['category'] == 'Other' ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>
            <div>
                <label class="block text-white mb-2">Type</label>
                <select name="type" class="w-full bg-gray-700 text-white px-4 py-2 rounded focus:outline-none focus:ring-2 focus:ring-primary">
                    <option value="add" <?php echo isset($_POST['type']) && $_POST['type'] == 'add' ? 'selected' : ''; ?>>Add Money</option>
                    <option value="deduct" <?php echo isset($_POST['type']) && $_POST['type'] == 'deduct' ? 'selected' : ''; ?>>Deduct Money</option>
                </select>
            </div>
            <button type="submit" class="bg-primary text-white w-full py-2 rounded-button flex items-center justify-center gap-2 hover:bg-purple-700 transition">
                <i class="ri-send-plane-line"></i> Submit
            </button>
        </form>
        <div class="mt-4 text-center text-gray-400">
            <p><a href="dashboard.php" class="text-primary hover:underline">Back to Dashboard</a></p>
        </div>
    </div>

    <script>
        // Error Handling with SweetAlert
        <?php if (isset($error)): ?>
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: '<?php echo $error; ?>',
                confirmButtonColor: '#8A2BE2',
                background: '#1f2937',
                color: '#fff'
            });
        <?php endif; ?>

        // Success Handling with SweetAlert
        <?php if (isset($success)): ?>
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: '<?php echo $success; ?>',
                confirmButtonColor: '#8A2BE2',
                background: '#1f2937',
                color: '#fff'
            }).then(() => {
                window.location.href = 'dashboard.php';
            });
        <?php endif; ?>
    </script>
</body>
</html>