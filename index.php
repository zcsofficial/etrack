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

    $sql = "SELECT id, username, password FROM users WHERE username = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows == 1) {
            $stmt->bind_result($id, $username, $hashed_password);
            $stmt->fetch();
            if (password_verify($password, $hashed_password)) {
                $_SESSION['user_id'] = $id;
                $_SESSION['username'] = $username;
                header("Location: dashboard.php");
                exit();
            } else {
                $error = "Invalid password!";
                logError("LOGIN FAILED: Incorrect password for user $username");
            }
        } else {
            $error = "No account found with that username.";
            logError("LOGIN FAILED: Username $username not found.");
        }
        $stmt->close();
    } else {
        $error = "Database error. Please try again later.";
        logError("ERROR: Database statement preparation failed.");
    }
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Expense Tracker</title>
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
        .login-container {
            background: rgba(255, 255, 255, 0.1);
            padding: 30px;
            border-radius: 15px;
            backdrop-filter: blur(10px);
            box-shadow: 0px 0px 20px rgba(155, 89, 182, 0.5);
            text-align: center;
            width: 350px;
        }
        .login-container h2 {
            font-size: 24px;
            color: #9b59b6;
            margin-bottom: 20px;
        }
        .login-container input {
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
        .login-container input:focus {
            background: rgba(255, 255, 255, 0.3);
        }
        .login-container button {
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
        .login-container button:hover {
            background: #8e44ad;
        }
        .login-container p {
            margin-top: 10px;
        }
        .login-container a {
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

    <div class="login-container">
        <h2>Login</h2>
        <form action="index.php" method="post">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Login <i class="fas fa-sign-in-alt"></i></button>
        </form>
        <p>Don't have an account? <a href="signup.php">Sign up</a></p>
    </div>

</body>
</html>
