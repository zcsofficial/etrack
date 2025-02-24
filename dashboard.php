<?php
session_start();
include 'config.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php"); // Updated to index.php assuming it's the login page
    exit();
}

$user_id = $_SESSION['user_id'];

// Session timeout: Logout after 30 minutes of inactivity
$timeout_duration = 1800; // 30 minutes in seconds
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header("Location: index.php");
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
        /* Ensure dropdowns don't overflow on small screens */
        .dropdown-content {
            right: 0;
            left: auto;
            min-width: 100%;
        }
        @media (max-width: 640px) {
            .dropdown-content {
                width: 100%;
                max-width: 100%;
            }
        }
    </style>
</head>
<body class="bg-secondary min-h-screen">
    <div class="container mx-auto px-4 py-6 sm:py-8">
        <!-- Header -->
        <header class="flex flex-col sm:flex-row justify-between items-center mb-6 sm:mb-8 gap-4">
            <h1 class="text-2xl sm:text-3xl font-bold text-primary font-['Pacifico'] text-center">Expense Tracker</h1>
            <div class="flex items-center gap-2 sm:gap-4">
                <span class="text-white text-sm sm:text-base"><?php echo date('Y-m-d'); ?></span>
                <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-full bg-primary flex items-center justify-center">
                    <i class="ri-user-line text-white text-lg sm:text-xl"></i>
                </div>
            </div>
        </header>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 sm:gap-6 mb-6 sm:mb-8">
            <div class="bg-gray-800 rounded-lg p-4 sm:p-6">
                <div class="flex items-center justify-between mb-3 sm:mb-4">
                    <h3 class="text-white text-sm sm:text-base">Total Collected</h3>
                    <div class="w-8 h-8 sm:w-10 sm:h-10 flex items-center justify-center">
                        <i class="ri-money-dollar-circle-line text-green-500 text-xl sm:text-2xl"></i>
                    </div>
                </div>
                <p class="text-2xl sm:text-3xl font-bold text-green-500">₹<?php echo number_format($total_collected, 2); ?></p>
                <p class="text-green-400 text-xs sm:text-sm">+0% from last month</p>
            </div>
            <div class="bg-gray-800 rounded-lg p-4 sm:p-6">
                <div class="flex items-center justify-between mb-3 sm:mb-4">
                    <h3 class="text-white text-sm sm:text-base">Total Deducted</h3>
                    <div class="w-8 h-8 sm:w-10 sm:h-10 flex items-center justify-center">
                        <i class="ri-subtract-line text-red-500 text-xl sm:text-2xl"></i>
                    </div>
                </div>
                <p class="text-2xl sm:text-3xl font-bold text-red-500">₹<?php echo number_format($total_deducted, 2); ?></p>
                <p class="text-red-400 text-xs sm:text-sm">+0% from last month</p>
            </div>
            <div class="bg-gray-800 rounded-lg p-4 sm:p-6">
                <div class="flex items-center justify-between mb-3 sm:mb-4">
                    <h3 class="text-white text-sm sm:text-base">Balance</h3>
                    <div class="w-8 h-8 sm:w-10 sm:h-10 flex items-center justify-center">
                        <i class="ri-wallet-3-line text-primary text-xl sm:text-2xl"></i>
                    </div>
                </div>
                <p class="text-2xl sm:text-3xl font-bold text-primary">₹<?php echo number_format($balance, 2); ?></p>
                <p class="text-primary text-xs sm:text-sm">Available balance</p>
            </div>
        </div>

        <!-- Action Buttons and Filters -->
        <div class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-4 sm:gap-0">
            <button onclick="window.location.href='add.php'" class="bg-primary text-white px-4 py-2 rounded-button flex items-center gap-2 w-full sm:w-auto text-sm sm:text-base">
                <i class="ri-add-line"></i>
                Add Expense
            </button>
            <div class="flex flex-col sm:flex-row gap-2 sm:gap-4 w-full sm:w-auto">
                <div class="relative w-full sm:w-auto">
                    <button id="categoryFilterBtn" class="bg-gray-800 text-white px-3 py-2 rounded-button flex items-center gap-2 w-full justify-center text-sm sm:text-base">
                        <i class="ri-filter-line"></i>
                        Category
                    </button>
                    <div id="categoryDropdown" class="hidden absolute top-full mt-2 bg-gray-800 rounded shadow-lg z-10 dropdown-content w-48 sm:w-48">
                        <div class="p-2">
                            <?php foreach ($categories as $cat): ?>
                                <a href="?category=<?php echo urlencode($cat); ?>" class="block text-white p-2 hover:bg-gray-700 text-sm"><?php echo $cat; ?></a>
                            <?php endforeach; ?>
                            <a href="dashboard.php" class="block text-white p-2 hover:bg-gray-700 text-sm">All</a>
                        </div>
                    </div>
                </div>
                <div class="relative w-full sm:w-auto">
                    <button id="dateFilterBtn" class="bg-gray-800 text-white px-3 py-2 rounded-button flex items-center gap-2 w-full justify-center text-sm sm:text-base">
                        <i class="ri-calendar-line"></i>
                        Date
                    </button>
                    <div id="dateDropdown" class="hidden absolute top-full mt-2 bg-gray-800 rounded shadow-lg z-10 dropdown-content w-64 sm:w-64">
                        <form method="GET" class="p-4">
                            <div class="mb-4">
                                <label class="block text-white mb-2 text-sm">From</label>
                                <input type="date" name="date_from" class="w-full bg-gray-700 text-white px-3 py-2 rounded text-sm" value="<?php echo isset($_GET['date_from']) ? $_GET['date_from'] : ''; ?>">
                            </div>
                            <div class="mb-4">
                                <label class="block text-white mb-2 text-sm">To</label>
                                <input type="date" name="date_to" class="w-full bg-gray-700 text-white px-3 py-2 rounded text-sm" value="<?php echo isset($_GET['date_to']) ? $_GET['date_to'] : ''; ?>">
                            </div>
                            <button type="submit" class="bg-primary text-white w-full py-2 rounded-button text-sm">Apply</button>
                        </form>
                    </div>
                </div>
                <form method="POST" action="logout.php" class="w-full sm:w-auto">
                    <button type="submit" class="bg-gray-800 text-white px-3 py-2 rounded-button flex items-center gap-2 w-full justify-center text-sm sm:text-base">
                        <i class="ri-logout-box-line"></i>
                        Logout
                    </button>
                </form>
            </div>
        </div>

        <!-- Recent Expenses -->
        <div class="bg-gray-800 rounded-lg p-4 sm:p-6">
            <h3 class="text-white text-lg sm:text-xl mb-4">Recent Expenses</h3>
            <div class="space-y-4">
                <?php if ($ledger->num_rows > 0): ?>
                    <?php while ($row = $ledger->fetch_assoc()): ?>
                        <div class="bg-gray-700 rounded-lg p-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                            <div class="flex items-center gap-4 w-full sm:w-auto">
                                <div class="w-8 h-8 sm:w-10 sm:h-10 bg-<?php echo $row['amount'] < 0 ? 'red' : 'green'; ?>-500 rounded flex items-center justify-center flex-shrink-0">
                                    <i class="ri-<?php echo $row['amount'] < 0 ? 'subtract' : 'add'; ?>-line text-white text-lg sm:text-xl"></i>
                                </div>
                                <div class="flex-1">
                                    <h4 class="text-white text-sm sm:text-base"><?php echo htmlspecialchars($row['expense_name']); ?> <span class="text-gray-400 text-xs sm:text-sm">[<?php echo $row['category']; ?>]</span></h4>
                                    <p class="text-gray-400 text-xs sm:text-sm"><?php echo date('Y-m-d', strtotime($row['created_at'])); ?></p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 sm:gap-4 w-full sm:w-auto justify-between sm:justify-end">
                                <span class="text-white font-bold text-sm sm:text-base">₹<?php echo number_format(abs($row['amount']), 2); ?></span>
                                <button class="text-gray-400 hover:text-red-500 delete-btn flex-shrink-0" data-id="<?php echo $row['id']; ?>">
                                    <i class="ri-delete-bin-line text-lg sm:text-xl"></i>
                                </button>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="text-gray-400 text-sm sm:text-base">No transactions yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Feedback Messages -->
    <?php if (isset($_GET['status'])): ?>
        <div class="fixed bottom-4 right-4 bg-<?php echo $_GET['status'] === 'deleted' ? 'green' : 'red'; ?>-500 text-white px-4 py-2 rounded text-sm sm:text-base">
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