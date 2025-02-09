<?php
session_start();
$error = "";
$success = "";

// Error Logging Function
function logError($message) {
    file_put_contents("error_log.txt", date("[Y-m-d H:i:s] ") . $message . "\n", FILE_APPEND);
}

// Try to Include Database Config
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
        $error = "Username and Email are required!";
    }

    // Check if user exists
    if (empty($error)) {
        $sql = "SELECT * FROM users WHERE username = ? AND email = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ss", $username, $email);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                // Generate reset link (you could use a unique token)
                $reset_link = "http://redexploit.online/reset-password.php?username=$username";
                $success = "Password reset link sent to your email: $reset_link";
            } else {
                $error = "Invalid username or email!";
                logError("ERROR: Invalid password reset request for $username");
            }
            $stmt->close();
        } else {
            $error = "Database error. Please try again later.";
            logError("ERROR: Database statement preparation failed.");
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
    <link rel="stylesheet" href="styles.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/js/all.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap');

        body {
            background-color: #121212;
            font-family: 'Poppins', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            color: #fff;
        }

        .signup-container {
            background: rgba(255, 255, 255, 0.1);
            padding: 30px;
            border-radius: 15px;
            backdrop-filter: blur(10px);
            box-shadow: 0px 0px 20px rgba(155, 89, 182, 0.5);
            text-align: center;
            width: 350px;
        }

        .signup-container h2 {
            font-size: 24px;
            color: #9b59b6;
            margin-bottom: 20px;
        }

        .signup-container input {
            width: 100%;
            padding: 12px;
            margin: 10px 0;
            border: none;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.2);
            color: #fff;
            outline: none;
            transition: 0.3s;
            font-size: 16px;
        }

        .signup-container input:focus {
            background: rgba(255, 255, 255, 0.3);
        }

        .signup-container button {
            width: 100%;
            padding: 12px;
            background: #9b59b6;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            color: #fff;
            cursor: pointer;
            transition: 0.3s;
        }

        .signup-container button:hover {
            background: #8e44ad;
        }

        .signup-container p {
            margin-top: 10px;
        }

        .signup-container a {
            color: #9b59b6;
            text-decoration: none;
            font-weight: bold;
        }

        /* Bubble Notification for Errors */
        .error-bubble, .success-bubble {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(255, 50, 50, 0.9);
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0px 4px 10px rgba(255, 50, 50, 0.5);
            font-size: 16px;
            display: none;
            animation: fadeIn 0.5s ease-in-out;
        }

        .success-bubble {
            background: rgba(50, 255, 50, 0.9);
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateX(-50%) translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateX(-50%) translateY(0);
            }
        }

    </style>
</head>
<body>

    <!-- Error Notification Bubble -->
    <?php if (!empty($error)) : ?>
    <div class="error-bubble" id="errorBubble">
        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
    </div>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            let errorBubble = document.getElementById("errorBubble");
            errorBubble.style.display = "block";
            setTimeout(() => {
                errorBubble.style.display = "none";
            }, 5000);
        });
    </script>
    <?php endif; ?>

    <!-- Success Notification Bubble -->
    <?php if (!empty($success)) : ?>
    <div class="success-bubble" id="successBubble">
        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
    </div>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            let successBubble = document.getElementById("successBubble");
            successBubble.style.display = "block";
            setTimeout(() => {
                successBubble.style.display = "none";
            }, 5000);
        });
    </script>
    <?php endif; ?>

    <div class="signup-container">
        <h2>Forgot Password</h2>
        <form action="forget-password.php" method="post">
            <input type="text" name="username" placeholder="Username" required>
            <input type="email" name="email" placeholder="Email" required>
            <button type="submit">Reset Password <i class="fas fa-lock"></i></button>
        </form>
        <p>Remembered your password? <a href="index.php">Login</a></p>
    </div>

</body>
</html>
