<?php
session_start();
$error = "";

// Error Logging Function
function logError($message) {
    file_put_contents("error_log.txt", date("[Y-m-d H:i:s] ") . $message . "\n", FILE_APPEND);
}

// Include Database Config
$configFile = 'config.php';
if (file_exists($configFile)) {
    include $configFile;
} else {
    $error = "Server Error: Missing config.php file!";
    logError("ERROR: config.php not found.");
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && empty($error)) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);

    // Validate input fields
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "All fields are required!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format!";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match!";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters!";
    } else {
        // Check if username or email already exists
        $check_sql = "SELECT id FROM users WHERE username = ? OR email = ?";
        if ($check_stmt = $conn->prepare($check_sql)) {
            $check_stmt->bind_param("ss", $username, $email);
            $check_stmt->execute();
            $check_stmt->store_result();
            if ($check_stmt->num_rows > 0) {
                $error = "Username or email already taken!";
                logError("SIGNUP ERROR: Username $username or email $email already exists.");
                $check_stmt->close();
            } else {
                $check_stmt->close();
                // Insert User
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                $sql = "INSERT INTO users (username, email, password) VALUES (?, ?, ?)";
                if ($stmt = $conn->prepare($sql)) {
                    $stmt->bind_param("sss", $username, $email, $hashed_password);
                    if ($stmt->execute()) {
                        header("Location: index.php?signup=success");
                        exit();
                    } else {
                        $error = "Signup failed! Try again.";
                        logError("SIGNUP ERROR: Could not create account for $username - " . $stmt->error);
                    }
                    $stmt->close();
                } else {
                    $error = "Database error. Please try again later.";
                    logError("ERROR: Database statement preparation failed - " . $conn->error);
                }
            }
        } else {
            $error = "Database error. Please try again later.";
            logError("ERROR: Check statement preparation failed - " . $conn->error);
        }
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Signup - Expense Tracker</title>
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
        input[type="password"]::-ms-reveal,
        input[type="password"]::-ms-clear {
            display: none;
        }
    </style>
</head>
<body class="bg-secondary min-h-screen flex items-center justify-center">
    <div class="bg-gray-800 rounded-lg p-8 w-full max-w-md shadow-lg">
        <h2 class="text-3xl font-bold text-primary font-['Pacifico'] text-center mb-6">Signup</h2>
        <form id="signupForm" method="POST" action="signup.php" class="space-y-4">
            <div>
                <label class="block text-white mb-2">Username</label>
                <input type="text" name="username" class="w-full bg-gray-700 text-white px-4 py-2 rounded focus:outline-none focus:ring-2 focus:ring-primary" placeholder="Enter username" required value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
            </div>
            <div>
                <label class="block text-white mb-2">Email</label>
                <input type="email" name="email" class="w-full bg-gray-700 text-white px-4 py-2 rounded focus:outline-none focus:ring-2 focus:ring-primary" placeholder="Enter email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>
            <div class="relative">
                <label class="block text-white mb-2">Password</label>
                <input type="password" name="password" id="password" class="w-full bg-gray-700 text-white px-4 py-2 rounded focus:outline-none focus:ring-2 focus:ring-primary" placeholder="Enter password" required>
                <i class="ri-eye-line absolute top-10 right-4 text-gray-400 cursor-pointer" id="togglePassword"></i>
            </div>
            <div class="relative">
                <label class="block text-white mb-2">Confirm Password</label>
                <input type="password" name="confirm_password" id="confirmPassword" class="w-full bg-gray-700 text-white px-4 py-2 rounded focus:outline-none focus:ring-2 focus:ring-primary" placeholder="Confirm password" required>
                <i class="ri-eye-line absolute top-10 right-4 text-gray-400 cursor-pointer" id="toggleConfirmPassword"></i>
            </div>
            <button type="submit" class="bg-primary text-white w-full py-2 rounded-button flex items-center justify-center gap-2 hover:bg-purple-700 transition">
                <i class="ri-user-add-line"></i> Sign Up
            </button>
        </form>
        <div class="mt-4 text-center text-gray-400">
            <p>Already have an account? <a href="index.php" class="text-primary hover:underline">Login</a></p>
        </div>
    </div>

    <script>
        // Password Toggle for Password Field
        const togglePassword = document.getElementById('togglePassword');
        const passwordField = document.getElementById('password');
        togglePassword.addEventListener('click', () => {
            const type = passwordField.type === 'password' ? 'text' : 'password';
            passwordField.type = type;
            togglePassword.classList.toggle('ri-eye-line');
            togglePassword.classList.toggle('ri-eye-off-line');
        });

        // Password Toggle for Confirm Password Field
        const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
        const confirmPasswordField = document.getElementById('confirmPassword');
        toggleConfirmPassword.addEventListener('click', () => {
            const type = confirmPasswordField.type === 'password' ? 'text' : 'password';
            confirmPasswordField.type = type;
            toggleConfirmPassword.classList.toggle('ri-eye-line');
            toggleConfirmPassword.classList.toggle('ri-eye-off-line');
        });

        // Error Handling with SweetAlert
        <?php if (!empty($error)): ?>
            Swal.fire({
                icon: 'error',
                title: 'Signup Failed',
                text: '<?php echo $error; ?>',
                confirmButtonColor: '#8A2BE2',
                background: '#1f2937',
                color: '#fff'
            });
        <?php endif; ?>

        // Success Message from index.php
        <?php if (isset($_GET['signup']) && $_GET['signup'] === 'success'): ?>
            Swal.fire({
                icon: 'success',
                title: 'Signup Successful',
                text: 'You can now log in!',
                confirmButtonColor: '#8A2BE2',
                background: '#1f2937',
                color: '#fff'
            }).then(() => {
                window.location.href = 'index.php';
            });
        <?php endif; ?>
    </script>
</body>
</html>