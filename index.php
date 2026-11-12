
<?php
session_start();

// Redirect users according to their account role.
if (isset($_SESSION["user_id"])) {

    if (($_SESSION["role"] ?? "") === "admin") {
        header("Location: admin_dashboard.php");
    } else {
        header("Location: dashboard.php");
    }

    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Flood Warning System</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            font-family: Arial, sans-serif;
            background:
                linear-gradient(
                    rgba(8, 24, 48, 0.82),
                    rgba(8, 24, 48, 0.90)
                ),
                url("assets/mayon.jpg") center/cover no-repeat,
                #10233f;
        }

        .login-card {
            width: 100%;
            max-width: 430px;
            padding: 32px;
            background: white;
            border-radius: 18px;
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.25);
        }

        .logos {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 18px;
            margin-bottom: 20px;
        }

        .logos img {
            width: 75px;
            height: 75px;
            object-fit: contain;
        }

        h1 {
            color: #10233f;
            font-size: 23px;
            text-align: center;
            line-height: 1.3;
        }

        .subtitle {
            margin: 10px 0 25px;
            color: #64748b;
            font-size: 14px;
            text-align: center;
            line-height: 1.5;
        }

        .message {
            margin-bottom: 16px;
            color: #b42318;
            font-size: 14px;
            text-align: center;
        }

        label {
            display: block;
            margin: 15px 0 7px;
            color: #26364a;
            font-size: 14px;
            font-weight: bold;
        }

        input {
            width: 100%;
            padding: 13px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 9px;
            font-size: 15px;
        }

        input:focus {
            outline: 2px solid #f4c542;
            border-color: #f4c542;
        }

        button {
            width: 100%;
            margin-top: 22px;
            padding: 14px;
            border: none;
            border-radius: 9px;
            background: #f4c542;
            color: #10233f;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            background: #e8b92b;
        }

        .register-link {
            margin-top: 20px;
            color: #64748b;
            font-size: 14px;
            text-align: center;
        }

        .register-link a {
            color: #1872b9;
            font-weight: bold;
            text-decoration: none;
        }

        .footer {
            margin-top: 22px;
            color: #64748b;
            font-size: 12px;
            text-align: center;
        }
    </style>
</head>

<body>

    <main class="login-card">

        <div class="logos">
            <img src="assets/school-logo.png" alt="School logo">
            <img src="assets/barangay-logo.png" alt="Barangay 37-Bitano logo">
        </div>

        <h1>Flood Warning System</h1>

        <p class="subtitle">
            Barangay 37-Bitano, Legazpi City<br>
            Please log in to continue.
        </p>

        <?php if (isset($_GET["error"])): ?>
            <p class="message">
                Invalid username or password.
            </p>
        <?php endif; ?>

        <form action="login.php" method="POST">

            <label for="username">Username</label>

            <input
                type="text"
                id="username"
                name="username"
                placeholder="Enter your username"
                autocomplete="username"
                required
            >

            <label for="password">Password</label>

            <input
                type="password"
                id="password"
                name="password"
                placeholder="Enter your password"
                autocomplete="current-password"
                required
            >

            <button type="submit">Log In</button>

        </form>

        <p class="register-link">
            Don't have an account?
            <a href="register.php">Create Account</a>
        </p>

        <p class="footer">
            Flood Monitoring and SMS Notification System
        </p>

    </main>

</body>
</html>