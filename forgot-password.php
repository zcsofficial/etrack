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

if ($_SERVER["REQUEST_METHOD"] == "POST" && empty($error)) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);

    // Validate input fields
    if (empty($username) || empty($email)) {
        $error = "Username and email are required!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format!";
    }

    // Check if user exists
    if (empty($error)) {
        $sql = "SELECT id FROM users WHERE username = ? AND email = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ss", $username, $email);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                $user_id = $user['id'];
                // Generate a unique reset token
                $token = bin2hex(random_bytes(16));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

                // Store the token in the database (you'll need a reset_tokens table)
                $token_sql = "INSERT INTO reset_tokens (user_id, token, expires) VALUES (?, ?, ?)";
                if ($token_stmt = $conn->prepare($token_sql)) {
                    $token_stmt->bind_param("iss", $user_id, $token, $expires);
                    if ($token_stmt->execute()) {
                        $reset_link = "http://redexploit.online/reset-password.php?token=$token";
                        $success = "A password reset link has been generated: <a href='$reset_link' class='text-primary underline'>$reset_link</a>";
                        // TODO: Replace this with actual email sending logic
                        // e.g., mail($email, "Password Reset", "Click here to reset: $reset_link");
                        logError("RESET LINK GENERATED: For $username - $reset_link");
                    } else {
                        $error = "Failed to generate reset link. Try again.";
                        logError("RESET ERROR: Could not store token for $username - " . $token_stmt->error);
                    }
                    $token_stmt->close();
                } else {
                    $error = "Database error. Please try again later.";
                    logError("ERROR: Token statement preparation failed - " . $conn->error);
                }
            } else {
                $error = "No account found with that username and email!";
                logError("RESET ERROR: Invalid request for $username with email $email");
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
    <title>Forgot Password - Expense Tracker</title>
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
        <h2 class="text-3xl font-bold text-primary font-['Pacifico'] text-center mb-6">Forgot Password</h2>
        <form id="resetForm" method="POST" action="forgot-password.php" class="space-y-4">
            <div>
                <label class="block text-white mb-2">Username</label>
                <input type="text" name="username" class="w-full bg-gray-700 text-white px-4 py-2 rounded focus:outline-none focus:ring-2 focus:ring-primary" placeholder="Enter username" required value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
            </div>
            <div>
                <label class="block text-white mb-2">Email</label>
                <input type="email" name="email" class="w-full bg-gray-700 text-white px-4 py-2 rounded focus:outline-none focus:ring-2 focus:ring-primary" placeholder="Enter email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>
            <button type="submit" class="bg-primary text-white w-full py-2 rounded-button flex items-center justify-center gap-2 hover:bg-purple-700 transition">
                <i class="ri-lock-password-line"></i> Reset Password
            </button>
        </form>
        <div class="mt-4 text-center text-gray-400">
            <p>Remembered your password? <a href="index.php" class="text-primary hover:underline">Login</a></p>
        </div>
    </div>

    <script>
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
                title: 'Reset Link Generated',
                html: '<?php echo $success; ?>',
                confirmButtonColor: '#8A2BE2',
                background: '#1f2937',
                color: '#fff'
            });
        <?php endif; ?>
    </script>
</body>
</html>