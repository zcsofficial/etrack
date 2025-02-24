<?php
session_start();
$error = "";
$success = "";

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

// Check for token in URL
$token = isset($_GET['token']) ? trim($_GET['token']) : '';
if (empty($token) && $_SERVER["REQUEST_METHOD"] != "POST") {
    $error = "Invalid or missing reset token!";
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && empty($error)) {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $token = trim($_POST['token']);

    // Validate input fields
    if (empty($email) || empty($password) || empty($confirm_password)) {
        $error = "All fields are required!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format!";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match!";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters!";
    }

    // Verify token and reset password
    if (empty($error)) {
        $sql = "SELECT user_id FROM reset_tokens WHERE token = ? AND expires > NOW()";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $token);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $user_id = $row['user_id'];

                // Check if email matches the user
                $user_sql = "SELECT email FROM users WHERE id = ? AND email = ?";
                if ($user_stmt = $conn->prepare($user_sql)) {
                    $user_stmt->bind_param("is", $user_id, $email);
                    $user_stmt->execute();
                    $user_result = $user_stmt->get_result();

                    if ($user_result->num_rows > 0) {
                        // Update password
                        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                        $update_sql = "UPDATE users SET password = ? WHERE id = ? AND email = ?";
                        if ($update_stmt = $conn->prepare($update_sql)) {
                            $update_stmt->bind_param("sis", $hashed_password, $user_id, $email);
                            if ($update_stmt->execute()) {
                                // Delete the used token
                                $delete_sql = "DELETE FROM reset_tokens WHERE token = ?";
                                if ($delete_stmt = $conn->prepare($delete_sql)) {
                                    $delete_stmt->bind_param("s", $token);
                                    $delete_stmt->execute();
                                    $delete_stmt->close();
                                }
                                $success = "Password reset successfully. You can now log in with your new password.";
                            } else {
                                $error = "Password reset failed. Try again.";
                                logError("RESET ERROR: Could not update password for $email - " . $update_stmt->error);
                            }
                            $update_stmt->close();
                        } else {
                            $error = "Database error. Please try again later.";
                            logError("ERROR: Update statement preparation failed - " . $conn->error);
                        }
                    } else {
                        $error = "No account found with that email!";
                        logError("RESET ERROR: Email $email does not match user_id $user_id");
                    }
                    $user_stmt->close();
                } else {
                    $error = "Database error. Please try again later.";
                    logError("ERROR: User check statement preparation failed - " . $conn->error);
                }
            } else {
                $error = "Invalid or expired reset token!";
                logError("RESET ERROR: Invalid or expired token $token");
            }
            $stmt->close();
        } else {
            $error = "Database error. Please try again later.";
            logError("ERROR: Token verification statement preparation failed - " . $conn->error);
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
    <title>Reset Password - Expense Tracker</title>
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
        <h2 class="text-3xl font-bold text-primary font-['Pacifico'] text-center mb-6">Reset Password</h2>
        <form id="resetForm" method="POST" action="reset-password.php" class="space-y-4">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            <div>
                <label class="block text-white mb-2">Email</label>
                <input type="email" name="email" class="w-full bg-gray-700 text-white px-4 py-2 rounded focus:outline-none focus:ring-2 focus:ring-primary" placeholder="Enter email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>
            <div class="relative">
                <label class="block text-white mb-2">New Password</label>
                <input type="password" name="password" id="password" class="w-full bg-gray-700 text-white px-4 py-2 rounded focus:outline-none focus:ring-2 focus:ring-primary" placeholder="Enter new password" required>
                <i class="ri-eye-line absolute top-10 right-4 text-gray-400 cursor-pointer" id="togglePassword"></i>
            </div>
            <div class="relative">
                <label class="block text-white mb-2">Confirm New Password</label>
                <input type="password" name="confirm_password" id="confirmPassword" class="w-full bg-gray-700 text-white px-4 py-2 rounded focus:outline-none focus:ring-2 focus:ring-primary" placeholder="Confirm new password" required>
                <i class="ri-eye-line absolute top-10 right-4 text-gray-400 cursor-pointer" id="toggleConfirmPassword"></i>
            </div>
            <button type="submit" class="bg-primary text-white w-full py-2 rounded-button flex items-center justify-center gap-2 hover:bg-purple-700 transition">
                <i class="ri-key-line"></i> Reset Password
            </button>
        </form>
        <div class="mt-4 text-center text-gray-400">
            <p>Remembered your password? <a href="index.php" class="text-primary hover:underline">Login</a></p>
        </div>
    </div>

    <script>
        // Password Toggle for New Password
        const togglePassword = document.getElementById('togglePassword');
        const passwordField = document.getElementById('password');
        togglePassword.addEventListener('click', () => {
            const type = passwordField.type === 'password' ? 'text' : 'password';
            passwordField.type = type;
            togglePassword.classList.toggle('ri-eye-line');
            togglePassword.classList.toggle('ri-eye-off-line');
        });

        // Password Toggle for Confirm Password
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
                title: 'Reset Failed',
                text: '<?php echo $error; ?>',
                confirmButtonColor: '#8A2BE2',
                background: '#1f2937',
                color: '#fff'
            });
        <?php endif; ?>

        // Success Handling with SweetAlert
        <?php if (!empty($success)): ?>
            Swal.fire({
                icon: 'success',
                title: 'Password Reset',
                text: '<?php echo $success; ?>',
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