
<?php
require_once "db.php";

$message = "";
$messageType = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $firstName = trim($_POST["first_name"] ?? "");
    $middleName = trim($_POST["middle_name"] ?? "");
    $lastName = trim($_POST["last_name"] ?? "");
    $suffix = trim($_POST["suffix"] ?? "");
    $mobileNumber = trim($_POST["mobile_number"] ?? "");
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";

    $fullName = trim(
        $firstName . " " .
        $middleName . " " .
        $lastName . " " .
        $suffix
    );

    if (
        $firstName === "" ||
        $lastName === "" ||
        $mobileNumber === "" ||
        $username === "" ||
        $password === "" ||
        $confirmPassword === ""
    ) {
        $message = "Please complete all required fields.";
        $messageType = "error";

    } elseif ($password !== $confirmPassword) {
        $message = "Passwords do not match.";
        $messageType = "error";

    } else {
        $check = $conn->prepare(
            "SELECT id FROM users
             WHERE username = ? OR mobile_number = ?"
        );

        $check->bind_param("ss", $username, $mobileNumber);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {

            $message = "Username or mobile number is already registered.";
            $messageType = "error";

        } else {
            $hashedPassword = password_hash(
                $password,
                PASSWORD_DEFAULT
            );
            $insert = $conn->prepare(
                "INSERT INTO users
                (
                    full_name,
                    first_name,
                    middle_name,
                    last_name,
                    suffix,
                    mobile_number,
                    username,
                    password
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );

            $insert->bind_param(
                "ssssssss",
                $fullName,
                $firstName,
                $middleName,
                $lastName,
                $suffix,
                $mobileNumber,
                $username,
                $hashedPassword
            );

            if ($insert->execute()) {
                $message = "Account created successfully. You can now log in.";
                $messageType = "success";
            } else {
                $message = "Could not create the account. Please try again.";
                $messageType = "error";
            }

            $insert->close();
        }

        $check->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Create Account | Flood Warning System</title>

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
            padding: 28px 18px;
            font-family: Arial, sans-serif;
            background:
                linear-gradient(
                    rgba(8, 24, 48, 0.82),
                    rgba(8, 24, 48, 0.90)
                ),
                url("assets/mayon.jpg") center/cover no-repeat,
                #10233f;
        }

        .register-card {
            width: 100%;
            max-width: 550px;
            padding: 32px;
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.25);
        }

        .logos {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 18px;
            margin-bottom: 18px;
        }

        .logos img {
            width: 68px;
            height: 68px;
            object-fit: contain;
        }

        .system-name {
            margin-bottom: 8px;
            color: #1872b9;
            font-size: 12px;
            font-weight: bold;
            letter-spacing: 1.4px;
            text-align: center;
            text-transform: uppercase;
        }

        h1 {
            color: #10233f;
            font-size: 27px;
            text-align: center;
        }

        .intro {
            margin: 9px 0 23px;
            color: #64748b;
            font-size: 14px;
            line-height: 1.5;
            text-align: center;
        }

        .message {
            padding: 12px;
            margin-bottom: 18px;
            border-radius: 8px;
            font-size: 14px;
            line-height: 1.4;
        }

        .success {
            background: #e8f7ee;
            color: #176b3a;
        }

        .error {
            background: #fff0f0;
            color: #a12626;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0 14px;
        }

        .field {
            margin-bottom: 15px;
        }

        .field.full {
            grid-column: 1 / -1;
        }

        label {
            display: block;
            margin-bottom: 7px;
            color: #26364a;
            font-size: 13px;
            font-weight: bold;
        }

        input {
            width: 100%;
            padding: 12px 13px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #f8fafc;
            font-size: 14px;
            outline: none;
        }

        input:focus {
            border-color: #2384cf;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(35, 132, 207, 0.12);
        }

        button {
            width: 100%;
            margin-top: 8px;
            padding: 14px;
            border: 0;
            border-radius: 9px;
            background: #f4c542;
            color: #10233f;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.2s;
        }

        button:hover {
            background: #e8b92b;
        }

        .login-link {
            margin-top: 21px;
            color: #64748b;
            font-size: 14px;
            text-align: center;
        }

        .login-link a {
            color: #1872b9;
            font-weight: bold;
            text-decoration: none;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        .footer {
            margin-top: 22px;
            color: #94a3b8;
            font-size: 11px;
            text-align: center;
        }

        @media (max-width: 480px) {
            .register-card {
                padding: 25px 20px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .field.full {
                grid-column: auto;
            }
        }
    </style>
</head>

<body>

    <main class="register-card">

        <div class="logos">
            <img src="assets/school-logo.png" alt="School logo">
            <img src="assets/barangay-logo.png" alt="Barangay logo">
        </div>

        <p class="system-name">Flood Warning System</p>

        <h1>Create Account</h1>

        <p class="intro">
            Register your personal information to access the SMS notification system.
        </p>

        <?php if ($message !== ""): ?>
            <div class="message <?= htmlspecialchars($messageType) ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST">

            <div class="form-grid">

                <div class="field">
                    <label for="first_name">First Name *</label>
                    <input
                        type="text"
                        id="first_name"
                        name="first_name"
                        placeholder="Enter first name"
                        value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>"
                        required
                    >
                </div>

                <div class="field">
                    <label for="middle_name">Middle Name</label>
                    <input
                        type="text"
                        id="middle_name"
                        name="middle_name"
                        placeholder="Enter middle name"
                        value="<?= htmlspecialchars($_POST['middle_name'] ?? '') ?>"
                    >
                </div>

                <div class="field">
                    <label for="last_name">Last Name *</label>
                    <input
                        type="text"
                        id="last_name"
                        name="last_name"
                        placeholder="Enter last name"
                        value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>"
                        required
                    >
                </div>

                <div class="field">
                    <label for="suffix">Suffix</label>
                    <input
                        type="text"
                        id="suffix"
                        name="suffix"
                        placeholder="e.g. Jr., Sr., III"
                        value="<?= htmlspecialchars($_POST['suffix'] ?? '') ?>"
                    >
                </div>

                <div class="field full">
                    <label for="mobile_number">SIM / Mobile Number *</label>
                    <input
                        type="tel"
                        id="mobile_number"
                        name="mobile_number"
                        placeholder="e.g. 09XXXXXXXXX"
                        value="<?= htmlspecialchars($_POST['mobile_number'] ?? '') ?>"
                        required
                    >
                </div>

                <div class="field full">
                    <label for="username">Username *</label>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        placeholder="Create a username"
                        value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                        required
                    >
                </div>

                <div class="field">
                    <label for="password">Password *</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Create password"
                        required
                    >
                </div>

                <div class="field">
                    <label for="confirm_password">Confirm Password *</label>
                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        placeholder="Re-enter password"
                        required
                    >
                </div>

            </div>

            <button type="submit">Create Account</button>

        </form>

        <p class="login-link">
            Already have an account?
            <a href="index.php">Back to Login</a>
        </p>

        <p class="footer">
            Barangay 37-Bitano, Legazpi City
        </p>

    </main>

</body>
</html>