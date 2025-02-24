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
    $login = trim($_POST['login']); // Can be username or email
    $password = trim($_POST['password']);

    if (empty($login) || empty($password)) {
        $error = "Please enter both login and password.";
    } else {
        // Check if login is username or email
        $sql = "SELECT id, username, email, password FROM users WHERE username = ? OR email = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ss", $login, $login);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows == 1) {
                $stmt->bind_result($id, $username, $email, $hashed_password);
                $stmt->fetch();
                if (password_verify($password, $hashed_password)) {
                    $_SESSION['user_id'] = $id;
                    $_SESSION['username'] = $username;
                    $_SESSION['last_activity'] = time(); // Set for session timeout in dashboard
                    header("Location: dashboard.php");
                    exit();
                } else {
                    $error = "Invalid password!";
                    logError("LOGIN FAILED: Incorrect password for user $username (email: $email)");
                }
            } else {
                $error = "No account found with that username or email.";
                logError("LOGIN FAILED: Login $login not found.");
            }
            $stmt->close();
        } else {
            $error = "Database error. Please try again later.";
            logError("ERROR: Database statement preparation failed - " . $conn->error);
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
    <title>Login - Expense Tracker</title>
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
        <h2 class="text-3xl font-bold text-primary font-['Pacifico'] text-center mb-6">Login</h2>
        <form id="loginForm" method="POST" action="index.php" class="space-y-4">
            <div>
                <label class="block text-white mb-2">Username or Email</label>
                <input type="text" name="login" class="w-full bg-gray-700 text-white px-4 py-2 rounded focus:outline-none focus:ring-2 focus:ring-primary" placeholder="Enter username or email" required>
            </div>
            <div class="relative">
                <label class="block text-white mb-2">Password</label>
                <input type="password" name="password" id="password" class="w-full bg-gray-700 text-white px-4 py-2 rounded focus:outline-none focus:ring-2 focus:ring-primary" placeholder="Enter password" required>
                <i class="ri-eye-line absolute top-10 right-4 text-gray-400 cursor-pointer" id="togglePassword"></i>
            </div>
            <button type="submit" class="bg-primary text-white w-full py-2 rounded-button flex items-center justify-center gap-2 hover:bg-purple-700 transition">
                <i class="ri-login-box-line"></i> Login
            </button>
        </form>
        <div class="mt-4 text-center text-gray-400">
            <p>Don't have an account? <a href="signup.php" class="text-primary hover:underline">Sign up</a></p>
            <p><a href="forgot-password.php" class="text-primary hover:underline">Forgot Password?</a></p>
        </div>
    </div>

    <script>
        // Password Toggle
        const togglePassword = document.getElementById('togglePassword');
        const passwordField = document.getElementById('password');
        togglePassword.addEventListener('click', () => {
            const type = passwordField.type === 'password' ? 'text' : 'password';
            passwordField.type = type;
            togglePassword.classList.toggle('ri-eye-line');
            togglePassword.classList.toggle('ri-eye-off-line');
        });

        // Error Handling with SweetAlert
        <?php if (!empty($error)): ?>
            Swal.fire({
                icon: 'error',
                title: 'Login Failed',
                text: '<?php echo $error; ?>',
                confirmButtonColor: '#8A2BE2',
                background: '#1f2937',
                color: '#fff'
            });
        <?php endif; ?>
    </script>
</body>
</html>