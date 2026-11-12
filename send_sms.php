
<?php
session_start();

// Allow logged-in residents only.
if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit;
}

if (($_SESSION["role"] ?? "") !== "user") {
    header("Location: admin_dashboard.php");
    exit;
}

require_once "config.php";
require_once "db.php";

$message = "";
$error = "";
$httpStatus = null;

$userId = (int) $_SESSION["user_id"];

// Create CSRF token.
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["csrf_token"];

// Get the resident's registered details.
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

if (!$user || empty($user["mobile_number"])) {
    die("The registered mobile number could not be found.");
}

$fullName = $user["full_name"] ?? "User";
$recipient = trim($user["mobile_number"]);

$digits = preg_replace("/\D+/", "", $recipient);

// Normalize Philippine mobile numbers.
if (substr($digits, 0, 2) === "09" && strlen($digits) === 11) {
    $recipient = "+63" . substr($digits, 1);
} elseif (substr($digits, 0, 2) === "63" && strlen($digits) === 12) {
    $recipient = "+" . $digits;
} else {
    $error = "The registered mobile number is invalid. Please check your account details.";
}

// Test message only.
$content = "TEST ONLY: SMS test from the Flood Warning System of Barangay 37-Bitano, Legazpi City. Please disregard.";

// Process SMS request.
if ($_SERVER["REQUEST_METHOD"] === "POST" && $error === "") {

    $submittedToken = $_POST["csrf_token"] ?? "";

    if (!hash_equals($csrfToken, $submittedToken)) {
        $error = "Invalid request. Please refresh the page and try again.";

    } elseif (!isset($_POST["confirm_send"])) {
        $error = "Please confirm before sending the test SMS.";

    } else {

        $payload = [
            "recipient" => $recipient,
            "content" => $content,
            "sender_id" => UNISMS_SENDER_ID
        ];

        $ch = curl_init("https://unismsapi.com/api/sms");

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json"
            ],
            CURLOPT_USERPWD => UNISMS_API_KEY . ":",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 25
        ]);

        $apiResponse = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($apiResponse === false) {
            $error = "Could not connect to UniSMS. Please try again later.";

        } elseif ($httpStatus >= 200 && $httpStatus < 300) {
            $message = "SMS request submitted to UniSMS. Please check your mobile phone.";

        } else {
            $error = "UniSMS did not accept the request. HTTP status: " . $httpStatus;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Send Test SMS | Flood Warning System</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Arial, sans-serif;
            color: #183451;
            background: linear-gradient(135deg, #eaf3fc, #f7f9fc);
        }

        .topbar {
            padding: 22px 6%;
            background: #173c70;
            color: white;
            border-bottom: 5px solid #f5c844;
        }

        .topbar-inner {
            max-width: 1100px;
            margin: auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .brand-title {
            margin: 0;
            font-size: clamp(21px, 3vw, 30px);
        }

        .brand-subtitle {
            margin: 7px 0 0;
            color: #d9e8f8;
            font-size: 14px;
        }

        .back-link {
            color: white;
            text-decoration: none;
            font-weight: bold;
            white-space: nowrap;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        main {
            width: min(720px, 92%);
            margin: 42px auto;
        }

        .card {
            overflow: hidden;
            background: white;
            border-radius: 16px;
            box-shadow: 0 12px 32px rgba(23, 60, 112, 0.13);
        }

        .card-header {
            padding: 24px 28px;
            background: #f2f7fd;
            border-bottom: 1px solid #dce7f3;
        }

        .card-header h2 {
            margin: 0;
            font-size: 24px;
        }

        .card-header p {
            margin: 9px 0 0;
            color: #61748b;
            line-height: 1.5;
        }

        .card-body {
            padding: 28px;
        }

        .field-label {
            margin-bottom: 8px;
            color: #61748b;
            font-size: 13px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .mobile-number {
            padding: 14px 16px;
            border: 1px solid #dce5ef;
            border-radius: 9px;
            background: #f8fafc;
            font-size: 18px;
            font-weight: bold;
            overflow-wrap: anywhere;
        }

        .message-box {
            margin-top: 22px;
            padding: 18px;
            border-left: 4px solid #f0bd2f;
            border-radius: 8px;
            background: #fff9e8;
            line-height: 1.6;
        }

        .message-box p {
            margin: 0;
        }

        .confirm {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-top: 22px;
            line-height: 1.5;
            color: #40566e;
        }

        .confirm input {
            margin-top: 4px;
            width: 17px;
            height: 17px;
            flex-shrink: 0;
        }

        .send-button {
            display: block;
            width: 100%;
            margin-top: 22px;
            padding: 15px 18px;
            border: 0;
            border-radius: 9px;
            background: #173c70;
            color: white;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        .send-button:hover {
            background: #24558f;
        }

        .notice {
            margin-top: 20px;
            padding: 13px 15px;
            border-radius: 8px;
            line-height: 1.5;
        }

        .success {
            color: #176333;
            background: #e7f6ec;
            border: 1px solid #b9e4c7;
        }

        .error {
            color: #9b2424;
            background: #fff0f0;
            border: 1px solid #f1c4c4;
        }

        .footnote {
            margin: 18px 0 0;
            color: #718197;
            font-size: 13px;
            line-height: 1.5;
            text-align: center;
        }

        @media (max-width: 600px) {
            .topbar {
                padding: 20px 5%;
            }

            .topbar-inner {
                align-items: flex-start;
                flex-direction: column;
            }

            main {
                margin: 24px auto;
            }

            .card-header,
            .card-body {
                padding: 21px;
            }
        }
    </style>
</head>

<body>

<header class="topbar">
    <div class="topbar-inner">
        <div>
            <h1 class="brand-title">Flood Warning System</h1>
            <p class="brand-subtitle">
                Barangay 37-Bitano, Legazpi City
            </p>
        </div>

        <a class="back-link" href="dashboard.php">
            ← Back to Dashboard
        </a>
    </div>
</header>

<main>
    <section class="card">

        <div class="card-header">
            <h2>Send Test SMS</h2>

            <p>
                Hello,
                <?= htmlspecialchars($fullName, ENT_QUOTES, "UTF-8") ?>.
                Review the details before sending.
            </p>
        </div>

        <div class="card-body">

            <div class="field-label">
                Registered Mobile Number
            </div>

            <div class="mobile-number">
                <?= htmlspecialchars($recipient, ENT_QUOTES, "UTF-8") ?>
            </div>

            <div class="message-box">
                <div class="field-label">
                    Test Message Preview
                </div>

                <p>
                    <?= htmlspecialchars($content, ENT_QUOTES, "UTF-8") ?>
                </p>
            </div>

            <?php if ($message !== ""): ?>
                <div class="notice success" role="status">
                    <?= htmlspecialchars($message, ENT_QUOTES, "UTF-8") ?>
                </div>
            <?php endif; ?>

            <?php if ($error !== ""): ?>
                <div class="notice error" role="alert">
                    <?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?>
                </div>
            <?php endif; ?>

            <form method="POST">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, "UTF-8") ?>"
                >

                <label class="confirm">
                    <input
                        type="checkbox"
                        name="confirm_send"
                        value="1"
                        required
                    >

                    <span>
                        Confirm: This is a test SMS and may use 1 SMS credit.
                    </span>
                </label>

                <button class="send-button" type="submit">
                    Send Test SMS
                </button>

            </form>

            <p class="footnote">
                This page sends a test message only to your registered
                mobile number. It does not represent an automatic flood alert.
            </p>

        </div>
    </section>
</main>

</body>
</html>