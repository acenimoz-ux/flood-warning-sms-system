
<?php
session_start();

require_once "config.php";
require_once "db.php";

$message = "";
$messageType = "";

$username = "bitano_drrm_admin";

// CSRF protection.
if (empty($_SESSION["admin_csrf"])) {
    $_SESSION["admin_csrf"] = bin2hex(random_bytes(32));
}

// Check if an administrator already exists.
$adminCheck = $conn->query(
    "SELECT id FROM users WHERE role = 'admin' LIMIT 1"
);

if (!$adminCheck) {
    die("Database query error.");
}

$adminExists = $adminCheck->num_rows > 0;

if ($_SERVER["REQUEST_METHOD"] === "POST" && !$adminExists) {

    $csrf = $_POST["csrf_token"] ?? "";
    $setupKey = $_POST["setup_key"] ?? "";

    $firstName = trim($_POST["first_name"] ?? "");
    $middleName = trim($_POST["middle_name"] ?? "");
    $lastName = trim($_POST["last_name"] ?? "");
    $suffix = trim($_POST["suffix"] ?? "");
    $mobileNumber = trim($_POST["mobile_number"] ?? "");

    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";

    if (!hash_equals($_SESSION["admin_csrf"], $csrf)) {

        $message = "Invalid request. Please refresh the page.";
        $messageType = "error";

    } elseif (
        !defined("ADMIN_SETUP_KEY") ||
        !hash_equals(ADMIN_SETUP_KEY, $setupKey)
    ) {

        $message = "Invalid administrator setup key.";
        $messageType = "error";

    } elseif (
        $firstName === "" ||
        $lastName === "" ||
        $mobileNumber === "" ||
        $password === "" ||
        $confirmPassword === ""
    ) {

        $message = "Please complete all required fields.";
        $messageType = "error";

    } elseif (!preg_match("/^[0-9+\-\s()]{10,20}$/", $mobileNumber)) {

        $message = "Please enter a valid mobile number.";
        $messageType = "error";

    } elseif (strlen($password) < 12) {

        $message = "Password must contain at least 12 characters.";
        $messageType = "error";

    } elseif ($password !== $confirmPassword) {

        $message = "Passwords do not match.";
        $messageType = "error";

    } else {

        // Normalize the mobile number for duplicate checking.
        $digits = preg_replace("/\D+/", "", $mobileNumber);

        if (
            strlen($digits) === 11 &&
            substr($digits, 0, 2) === "09"
        ) {
            $mobileNumber = "+63" . substr($digits, 1);

        } elseif (
            strlen($digits) === 12 &&
            substr($digits, 0, 2) === "63"
        ) {
            $mobileNumber = "+" . $digits;

        } elseif (
            strlen($digits) === 10 &&
            substr($digits, 0, 1) === "9"
        ) {
            $mobileNumber = "+63" . $digits;

        } else {
            $mobileNumber = false;
        }

        if ($mobileNumber === false) {

            $message = "Please enter a valid Philippine mobile number.";
            $messageType = "error";

        } else {

            // Check for an existing username or mobile number.
            $check = $conn->prepare(
                "SELECT id FROM users
                 WHERE username = ? OR mobile_number = ?
                 LIMIT 1"
            );

            if (!$check) {
                die("Database query error.");
            }

            $check->bind_param("ss", $username, $mobileNumber);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {

                $message = "Admin username or mobile number is already in use.";
                $messageType = "error";

            } else {

                // Check again that no administrator exists.
                $latestCheck = $conn->query(
                    "SELECT id FROM users WHERE role = 'admin' LIMIT 1"
                );

                if (!$latestCheck) {
                    die("Database query error.");
                }

                if ($latestCheck->num_rows > 0) {

                    $message = "An administrator account already exists.";
                    $messageType = "error";
                    $adminExists = true;

                } else {

                    $fullName = trim(
                        $firstName . " " .
                        $middleName . " " .
                        $lastName . " " .
                        $suffix
                    );

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
                            password,
                            role
                        )
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'admin')"
                    );

                    if (!$insert) {
                        die("Database query error.");
                    }

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

                        $message = "Admin account created successfully. You can now log in.";
                        $messageType = "success";
                        $adminExists = true;

                        // Invalidate the setup form token.
                        unset($_SESSION["admin_csrf"]);

                    } else {

                        $message = "Unable to create the admin account. Please try again.";
                        $messageType = "error";
                    }

                    $insert->close();
                }
            }

            $check->close();
        }
    }

    // Generate a new CSRF token after submission.
    if (!$adminExists) {
        $_SESSION["admin_csrf"] = bin2hex(random_bytes(32));
    }
}

$csrfToken = $_SESSION["admin_csrf"] ?? "";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Registration | Flood Warning System</title>

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
            padding: 25px 15px;
            font-family: Arial, sans-serif;

            background:
                linear-gradient(
                    rgba(8, 24, 48, 0.82),
                    rgba(8, 24, 48, 0.90)
                ),
                url("assets/mayon.jpg") center/cover no-repeat,
                #10233f;
        }

        .admin-card {
            width: 100%;
            max-width: 650px;
            padding: 35px;
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 12px 35px rgba(0,0,0,.25);
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

        .system-name {
            color: #1872b9;
            font-size: 12px;
            font-weight: bold;
            letter-spacing: 1.4px;
            text-align: center;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        h1 {
            color: #10233f;
            font-size: 27px;
            text-align: center;
        }

        .subtitle {
            margin: 10px 0 25px;
            color: #64748b;
            font-size: 14px;
            text-align: center;
            line-height: 1.5;
        }

        .section-title {
            margin: 23px 0 15px;
            padding-bottom: 9px;
            border-bottom: 2px solid #f4c542;
            color: #193d70;
            font-size: 16px;
            font-weight: bold;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0 15px;
        }

        .field {
            margin-bottom: 16px;
        }

        .field.full {
            grid-column: 1 / -1;
        }

        label {
            display: block;
            margin-bottom: 7px;
            color: #26364a;
            font-size: 14px;
            font-weight: bold;
        }

        input {
            width: 100%;
            padding: 13px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 9px;
            background: #f8fafc;
            font-size: 14px;
            outline: none;
        }

        input:focus {
            border-color: #f4c542;
            background: white;
            box-shadow: 0 0 0 2px rgba(244,197,66,.2);
        }

        input[readonly] {
            background: #eaf1f8;
            color: #193d70;
            font-weight: bold;
            cursor: not-allowed;
        }

        .admin-notice {
            margin-top: 5px;
            padding: 13px;
            border-left: 4px solid #f4c542;
            border-radius: 7px;
            background: #fff8e7;
            color: #526174;
            font-size: 13px;
            line-height: 1.5;
        }

        .message {
            padding: 13px;
            margin-bottom: 18px;
            border-radius: 8px;
            font-size: 14px;
            line-height: 1.5;
        }

        .success {
            background: #e8f7ee;
            color: #176b3a;
        }

        .error {
            background: #fff0f0;
            color: #a12626;
        }

        button {
            width: 100%;
            margin-top: 20px;
            padding: 15px;
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

        .footer {
            margin-top: 23px;
            color: #64748b;
            font-size: 12px;
            text-align: center;
        }

        @media (max-width: 520px) {
            .admin-card {
                padding: 27px 21px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .field.full {
                grid-column: auto;
            }

            h1 {
                font-size: 23px;
            }
        }
    </style>
</head>

<body>

<main class="admin-card">

    <div class="logos">
        <img src="assets/school-logo.png" alt="School logo">
        <img src="assets/barangay-logo.png" alt="Barangay logo">
    </div>

    <p class="system-name">Barangay 37-Bitano</p>

    <h1>DRRM Admin Registration</h1>

    <p class="subtitle">
        Flood Warning System
        <br>
        Create the authorized Barangay DRRM administrator account.
    </p>

    <?php if ($message !== ""): ?>
        <div class="message <?= $messageType === "success" ? "success" : "error" ?>">
            <?= htmlspecialchars($message, ENT_QUOTES, "UTF-8") ?>
        </div>
    <?php endif; ?>

    <?php if ($adminExists): ?>

        <div class="admin-notice">
            An administrator account already exists.
            Admin registration is now closed.
        </div>

        <p style="text-align:center; margin-top:20px;">
            <a href="index.php" style="color:#193d70;font-weight:bold;">
                Go to Login Page
            </a>
        </p>

    <?php else: ?>

        <form method="POST" action="">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, "UTF-8") ?>"
            >

            <div class="section-title">Personal Information</div>

            <div class="form-grid">

                <div class="field">
                    <label for="first_name">First Name *</label>
                    <input type="text" id="first_name"
                           name="first_name" maxlength="100" required>
                </div>

                <div class="field">
                    <label for="middle_name">Middle Name</label>
                    <input type="text" id="middle_name"
                           name="middle_name" maxlength="100">
                </div>

                <div class="field">
                    <label for="last_name">Last Name *</label>
                    <input type="text" id="last_name"
                           name="last_name" maxlength="100" required>
                </div>

                <div class="field">
                    <label for="suffix">Suffix</label>
                    <input type="text" id="suffix"
                           name="suffix" maxlength="20"
                           placeholder="e.g. Jr., III">
                </div>

            </div>

            <div class="section-title">Account Information</div>

            <div class="form-grid">

                <div class="field full">
                    <label for="mobile_number">Mobile Number *</label>
                    <input type="tel" id="mobile_number"
                           name="mobile_number"
                           placeholder="e.g. 09XXXXXXXXX" required>
                </div>

                <div class="field full">
                    <label for="username">Admin Username</label>
                    <input type="text" id="username"
                           value="bitano_drrm_admin" readonly>
                </div>

                <div class="field full">
                    <label for="setup_key">Admin Setup Key *</label>
                    <input type="password" id="setup_key"
                           name="setup_key" required>
                </div>

                <div class="field">
                    <label for="password">Password *</label>
                    <input type="password" id="password"
                           name="password" minlength="12"
                           autocomplete="new-password" required>
                </div>

                <div class="field">
                    <label for="confirm_password">Confirm Password *</label>
                    <input type="password" id="confirm_password"
                           name="confirm_password" minlength="12"
                           autocomplete="new-password" required>
                </div>

            </div>

            <div class="admin-notice">
                This account is intended only for the authorized
                Barangay 37-Bitano Disaster Risk Reduction and
                Management personnel. The setup key is required
                to complete registration.
            </div>

            <button type="submit">Create Admin Account</button>

        </form>

    <?php endif; ?>

    <p class="footer">
        COMPUTER COMMUNICATION DEVELOPMENT INSTITUTE.
    </p>

</main>

</body>
</html>