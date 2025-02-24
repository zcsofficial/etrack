<?php
session_start();
include 'config.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Session timeout: Logout after 30 minutes of inactivity
$timeout_duration = 1800; // 30 minutes in seconds
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}
$_SESSION['last_activity'] = time();

// Delete Expense Functionality
if (isset($_POST['delete_expense'])) {
    $expense_id = $_POST['expense_id'];
    $stmt = $conn->prepare("DELETE FROM expenses WHERE user_id = ? AND id = ?");
    $stmt->bind_param("ii", $user_id, $expense_id);
    if ($stmt->execute()) {
        header("Location: dashboard.php?status=deleted&msg=Expense+deleted+successfully");
    } else {
        header("Location: dashboard.php?status=error&msg=Failed+to+delete+expense");
    }
    $stmt->close();
    exit();
}

// Fetch Total Money
$total_collected = 0;
$total_deducted = 0;
$result = $conn->query("SELECT SUM(amount) AS total FROM expenses WHERE user_id = $user_id AND amount > 0");
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $total_collected = $row['total'] ?? 0;
}
$result = $conn->query("SELECT SUM(amount) AS total FROM expenses WHERE user_id = $user_id AND amount < 0");
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $total_deducted = abs($row['total'] ?? 0);
}
$balance = $total_collected - $total_deducted;

// Fetch Categories from ENUM
$categories = [];
$result = $conn->query("SHOW COLUMNS FROM expenses LIKE 'category'");
if ($result && $row = $result->fetch_assoc()) {
    preg_match("/^enum\((.*)\)$/", $row['Type'], $matches);
    $categories = explode(',', str_replace("'", "", $matches[1]));
}

// Filter Logic
$where_clause = "WHERE user_id = $user_id";
if (isset($_GET['category']) && in_array($_GET['category'], $categories)) {
    $category_filter = $conn->real_escape_string($_GET['category']);
    $where_clause .= " AND category = '$category_filter'";
}
if (isset($_GET['date_from']) && isset($_GET['date_to'])) {
    $date_from = $conn->real_escape_string($_GET['date_from']);
    $date_to = $conn->real_escape_string($_GET['date_to']);
    $where_clause .= " AND created_at BETWEEN '$date_from 00:00:00' AND '$date_to 23:59:59'";
}

// Get Expense Ledger with Filters
$ledger = $conn->query("SELECT * FROM expenses $where_clause ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense Tracker</title>
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
    <style>
        input[type="number"]::-webkit-inner-spin-button,
        input[type="number"]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
    </style>
</head>
<body class="bg-secondary min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <header class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold text-primary font-['Pacifico']">Expense Tracker</h1>
            <div class="flex items-center gap-4">
                <span class="text-white"><?php echo date('Y-m-d'); ?></span>
                <div class="w-10 h-10 rounded-full bg-primary flex items-center justify-center">
                    <i class="ri-user-line text-white"></i>
                </div>
            </div>
        </header>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-gray-800 rounded p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-white">Total Collected</h3>
                    <div class="w-10 h-10 flex items-center justify-center">
                        <i class="ri-money-dollar-circle-line text-green-500 text-2xl"></i>
                    </div>
                </div>
                <p class="text-3xl font-bold text-green-500">₹<?php echo number_format($total_collected, 2); ?></p>
                <p class="text-green-400 text-sm">+0% from last month</p>
            </div>
            <div class="bg-gray-800 rounded p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-white">Total Deducted</h3>
                    <div class="w-10 h-10 flex items-center justify-center">
                        <i class="ri-subtract-line text-red-500 text-2xl"></i>
                    </div>
                </div>
                <p class="text-3xl font-bold text-red-500">₹<?php echo number_format($total_deducted, 2); ?></p>
                <p class="text-red-400 text-sm">+0% from last month</p>
            </div>
            <div class="bg-gray-800 rounded p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-white">Balance</h3>
                    <div class="w-10 h-10 flex items-center justify-center">
                        <i class="ri-wallet-3-line text-primary text-2xl"></i>
                    </div>
                </div>
                <p class="text-3xl font-bold text-primary">₹<?php echo number_format($balance, 2); ?></p>
                <p class="text-primary text-sm">Available balance</p>
            </div>
        </div>

        <!-- Action Buttons and Filters -->
        <div class="flex justify-between items-center mb-6">
            <button onclick="window.location.href='add.php'" class="bg-primary text-white px-6 py-2 rounded-button flex items-center gap-2">
                <i class="ri-add-line"></i>
                Add Expense
            </button>
            <div class="flex gap-4">
                <div class="relative">
                    <button id="categoryFilterBtn" class="bg-gray-800 text-white px-4 py-2 rounded-button flex items-center gap-2">
                        <i class="ri-filter-line"></i>
                        Category
                    </button>
                    <div id="categoryDropdown" class="hidden absolute top-full mt-2 w-48 bg-gray-800 rounded shadow-lg z-10">
                        <div class="p-2">
                            <?php foreach ($categories as $cat): ?>
                                <a href="?category=<?php echo urlencode($cat); ?>" class="block text-white p-2 hover:bg-gray-700"><?php echo $cat; ?></a>
                            <?php endforeach; ?>
                            <a href="dashboard.php" class="block text-white p-2 hover:bg-gray-700">All</a>
                        </div>
                    </div>
                </div>
                <div class="relative">
                    <button id="dateFilterBtn" class="bg-gray-800 text-white px-4 py-2 rounded-button flex items-center gap-2">
                        <i class="ri-calendar-line"></i>
                        Date
                    </button>
                    <div id="dateDropdown" class="hidden absolute top-full mt-2 w-64 bg-gray-800 rounded shadow-lg z-10">
                        <form method="GET" class="p-4">
                            <div class="mb-4">
                                <label class="block text-white mb-2">From</label>
                                <input type="date" name="date_from" class="w-full bg-gray-700 text-white px-3 py-2 rounded" value="<?php echo isset($_GET['date_from']) ? $_GET['date_from'] : ''; ?>">
                            </div>
                            <div class="mb-4">
                                <label class="block text-white mb-2">To</label>
                                <input type="date" name="date_to" class="w-full bg-gray-700 text-white px-3 py-2 rounded" value="<?php echo isset($_GET['date_to']) ? $_GET['date_to'] : ''; ?>">
                            </div>
                            <button type="submit" class="bg-primary text-white w-full py-2 rounded-button">Apply</button>
                        </form>
                    </div>
                </div>
                <form method="POST" action="logout.php">
                    <button type="submit" class="bg-gray-800 text-white px-4 py-2 rounded-button flex items-center gap-2">
                        <i class="ri-logout-box-line"></i>
                        Logout
                    </button>
                </form>
            </div>
        </div>

        <!-- Recent Expenses -->
        <div class="bg-gray-800 rounded p-6">
            <h3 class="text-white text-xl mb-4">Recent Expenses</h3>
            <div class="space-y-4">
                <?php if ($ledger->num_rows > 0): ?>
                    <?php while ($row = $ledger->fetch_assoc()): ?>
                        <div class="bg-gray-700 rounded p-4 flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 bg-<?php echo $row['amount'] < 0 ? 'red' : 'green'; ?>-500 rounded flex items-center justify-center">
                                    <i class="ri-<?php echo $row['amount'] < 0 ? 'subtract' : 'add'; ?>-line text-white"></i>
                                </div>
                                <div>
                                    <h4 class="text-white"><?php echo htmlspecialchars($row['expense_name']); ?> <span class="text-gray-400 text-sm">[<?php echo $row['category']; ?>]</span></h4>
                                    <p class="text-gray-400 text-sm"><?php echo date('Y-m-d', strtotime($row['created_at'])); ?></p>
                                </div>
                            </div>
                            <div class="flex items-center gap-4">
                                <span class="text-white font-bold">₹<?php echo number_format(abs($row['amount']), 2); ?></span>
                                <div class="flex gap-2">
                                    <button class="text-gray-400 hover:text-red-500 delete-btn" data-id="<?php echo $row['id']; ?>">
                                        <i class="ri-delete-bin-line"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="text-gray-400">No transactions yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Feedback Messages -->
    <?php if (isset($_GET['status'])): ?>
        <div class="fixed bottom-4 right-4 bg-<?php echo $_GET['status'] === 'deleted' ? 'green' : 'red'; ?>-500 text-white px-4 py-2 rounded">
            <?php echo urldecode($_GET['msg']); ?>
        </div>
    <?php endif; ?>

    <script>
        // Dropdown Toggle
        document.getElementById('categoryFilterBtn').addEventListener('click', () => {
            document.getElementById('categoryDropdown').classList.toggle('hidden');
        });
        document.getElementById('dateFilterBtn').addEventListener('click', () => {
            document.getElementById('dateDropdown').classList.toggle('hidden');
        });
        window.addEventListener('click', (e) => {
            if (!e.target.closest('#categoryFilterBtn') && !e.target.closest('#categoryDropdown')) {
                document.getElementById('categoryDropdown').classList.add('hidden');
            }
            if (!e.target.closest('#dateFilterBtn') && !e.target.closest('#dateDropdown')) {
                document.getElementById('dateDropdown').classList.add('hidden');
            }
        });

        // SweetAlert Delete Confirmation
        document.querySelectorAll('.delete-btn').forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                const expenseId = button.getAttribute('data-id');
                Swal.fire({
                    title: 'Are you sure?',
                    text: "You won't be able to revert this!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = 'dashboard.php';
                        form.innerHTML = `
                            <input type="hidden" name="delete_expense" value="1">
                            <input type="hidden" name="expense_id" value="${expenseId}">
                        `;
                        document.body.appendChild(form);
                        form.submit();
                    }
                });
            });
        });
    </script>
</body>
</html>