<?php
session_start();
$error = ""; // Initialize error message

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
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);

    // Validate input fields
    if (empty($username) || empty($password) || empty($confirm_password)) {
        $error = "All fields are required!";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match!";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters!";
    }

    // Insert User If No Errors
    if (empty($error)) {
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        $sql = "INSERT INTO users (username, password) VALUES (?, ?)";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ss", $username, $hashed_password);
            if ($stmt->execute()) {
                header("Location: login.php?signup=success");
                exit();
            } else {
                $error = "Signup failed! Try again.";
                logError("SIGNUP ERROR: Could not create account for $username");
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
    <title>Signup - Expense Tracker</title>
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
        .error-bubble {
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

    <div class="signup-container">
        <h2>Signup</h2>
        <form action="signup.php" method="post">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <input type="password" name="confirm_password" placeholder="Confirm Password" required>
            <button type="submit">Sign Up <i class="fas fa-user-plus"></i></button>
        </form>
        <p>Already have an account? <a href="index.php">Login</a></p>
    </div>

</body>
</html>
