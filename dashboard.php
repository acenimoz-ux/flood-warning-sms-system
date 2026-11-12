
<?php
session_start();

// Check if the user is logged in.
if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit;
}

// Allow residents only on this dashboard.
if (($_SESSION["role"] ?? "user") !== "user") {
    header("Location: index.php?error=unauthorized");
    exit;
}

require_once "db.php";

$userId = (int) $_SESSION["user_id"];

$stmt = $conn->prepare(
    "SELECT full_name, mobile_number FROM users WHERE id = ? LIMIT 1"
);

if (!$stmt) {
    die("Database query error.");
}

$stmt->bind_param("i", $userId);
$stmt->execute();

$result = $stmt->get_result();
$user = $result->fetch_assoc();

$stmt->close();

if (!$user) {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit;
}

$fullName = $_SESSION["full_name"] ?? ($user["full_name"] ?? "User");
$mobileNumber = $user["mobile_number"] ?? "Not available";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Flood Warning System | Barangay 37-Bitano</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Arial, sans-serif;
            color: #17365f;
            background:
                linear-gradient(
                    rgba(226, 240, 255, 0.48),
                    rgba(226, 240, 255, 0.62)
                ),
                url("assets/mayon.jpg") center / cover fixed no-repeat,
                #eaf3fc;
        }

        .topbar {
            background: #173c70;
            color: white;
            border-bottom: 5px solid #f5c844;
            padding: 22px 5%;
        }

        .topbar-inner {
            width: min(1200px, 100%);
            margin: auto;
            display: flex;
            align-items: center;
            gap: 24px;
        }

        .logos {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-shrink: 0;
        }

        .logo {
            width: 88px;
            height: 88px;
            border-radius: 50%;
            object-fit: cover;
            background: white;
            border: 3px solid white;
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.2);
        }

        .brand {
            min-width: 0;
        }

        .brand h1 {
            margin: 0;
            font-size: clamp(24px, 3.2vw, 38px);
            line-height: 1.15;
        }

        .brand h1 span {
            color: #f5c844;
        }

        .brand p {
            margin: 8px 0 0;
            color: #e1edfa;
            font-size: 17px;
        }

        .header-actions {
            margin-left: auto;
            text-align: right;
            flex-shrink: 0;
        }

        .welcome {
            margin-bottom: 12px;
            color: #e1edfa;
            font-size: 14px;
        }

        .logout {
            display: inline-block;
            color: white;
            text-decoration: none;
            font-weight: bold;
        }

        .logout:hover {
            text-decoration: underline;
        }

        main {
            width: min(1000px, 92%);
            margin: 38px auto;
        }

        .card {
            overflow: hidden;
            background: rgba(255, 255, 255, 0.96);
            border: 1px solid rgba(255, 255, 255, 0.8);
            border-radius: 18px;
            box-shadow: 0 14px 38px rgba(22, 55, 95, 0.2);
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 18px;
            padding: 25px 30px;
            background: #f0f6fd;
            border-bottom: 1px solid #d9e6f4;
        }

        .sms-icon {
            width: 68px;
            height: 68px;
            display: grid;
            place-items: center;
            flex-shrink: 0;
            border-radius: 50%;
            background: #173c70;
            color: white;
            font-size: 20px;
            font-weight: bold;
        }

        .card-header h2 {
            margin: 0;
            font-size: 28px;
        }

        .card-header p {
            margin: 8px 0 0;
            color: #60758e;
            line-height: 1.5;
        }

        .card-body {
            padding: 30px;
        }

        .label {
            margin-bottom: 10px;
            color: #61758d;
            font-size: 13px;
            font-weight: bold;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .mobile-number {
            padding: 16px 18px;
            border: 1px solid #d8e4f1;
            border-radius: 10px;
            background: #f7f9fc;
            color: #173c70;
            font-size: 20px;
            font-weight: bold;
            overflow-wrap: anywhere;
        }

        .message-preview {
            margin-top: 24px;
            padding: 20px;
            border-left: 5px solid #f5c844;
            border-radius: 10px;
            background: #fff8e6;
            line-height: 1.6;
        }

        .message-preview p {
            margin: 0;
        }

        .status {
            display: inline-block;
            margin-top: 22px;
            padding: 8px 13px;
            border-radius: 20px;
            background: #e5f5e9;
            color: #176333;
            font-size: 13px;
            font-weight: bold;
        }

        .send-button {
            display: block;
            width: 100%;
            margin-top: 22px;
            padding: 16px;
            border: 0;
            border-radius: 10px;
            background: #173c70;
            color: white;
            text-align: center;
            text-decoration: none;
            font-size: 17px;
            font-weight: bold;
        }

        .send-button:hover {
            background: #24558f;
        }

        .note {
            margin: 18px 0 0;
            color: #61758d;
            font-size: 13px;
            line-height: 1.5;
            text-align: center;
        }

        @media (max-width: 760px) {
            .topbar-inner {
                flex-wrap: wrap;
            }

            .logo {
                width: 68px;
                height: 68px;
            }

            .header-actions {
                width: 100%;
                margin-left: 0;
                text-align: left;
            }

            .card-header {
                padding: 22px;
            }

            .card-body {
                padding: 22px;
            }

            .card-header h2 {
                font-size: 23px;
            }
        }

        @media (max-width: 450px) {
            .topbar-inner {
                align-items: flex-start;
                gap: 16px;
            }

            .logos {
                gap: 8px;
            }

            .logo {
                width: 54px;
                height: 54px;
            }

            .brand p {
                font-size: 14px;
            }

            .sms-icon {
                width: 52px;
                height: 52px;
                font-size: 16px;
            }
        }
    </style>
</head>

<body>

    <header class="topbar">
        <div class="topbar-inner">

            <div class="logos">
                <img
                    class="logo"
                    src="assets/school-logo.png"
                    alt="School logo"
                >

                <img
                    class="logo"
                    src="assets/barangay-logo.png"
                    alt="Barangay 37-Bitano logo"
                >
            </div>

            <div class="brand">
                <h1>Flood Warning <span>System</span></h1>
                <p>Barangay 37-Bitano, Legazpi City</p>
            </div>

            <div class="header-actions">
                <div class="welcome">
                    Welcome,
                    <strong>
                        <?= htmlspecialchars(
                            $fullName,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>
                    </strong>
                </div>

                <a class="logout" href="logout.php">Log Out</a>
            </div>

        </div>
    </header>

    <main>

        <section class="card">

            <div class="card-header">
                <div class="sms-icon">SMS</div>

                <div>
                    <h2>SMS Notifications</h2>
                    <p>
                        Review your registered number and send a test message.
                    </p>
                </div>
            </div>

            <div class="card-body">

                <div class="label">Registered Mobile Number</div>

                <div class="mobile-number">
                    <?= htmlspecialchars(
                        $mobileNumber,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>
                </div>

                <div class="message-preview">

                    <div class="label">Test Message Preview</div>

                    <p>
                        TEST ONLY: SMS test from the Flood Warning System of
                        Barangay 37-Bitano, Legazpi City. Please disregard.
                    </p>

                </div>

                <div class="status">Ready for SMS test</div>

                <a class="send-button" href="send_sms.php">
                    Send Test SMS
                </a>

                <p class="note">
                    COMPUTER COMMUNICATION DEVELOPMENT INSTITUTE.
                </p>

            </div>

        </section>

    </main>

</body>
</html>