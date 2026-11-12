
<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit;
}

if (($_SESSION["role"] ?? "") !== "admin") {
    header("Location: dashboard.php");
    exit;
}

require_once "config.php";
require_once "db.php";

set_time_limit(180);

$adminName = $_SESSION["full_name"] ?? "Administrator";

$message = "";
$error = "";

$acceptedCount = 0;
$failedCount = 0;
$invalidCount = 0;
$totalResidents = 0;
$uniqueRecipientCount = 0;

$allowedLevels = [
    "WARNING",
    "HIGH ALERT",
    "CRITICAL",
    "ALL CLEAR"
];

// CSRF protection.
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["csrf_token"];

// Normalize Philippine mobile numbers.
function normalizeMobileNumber($number) {
    $digits = preg_replace("/\D+/", "", trim($number));

    if (strlen($digits) === 11 && substr($digits, 0, 2) === "09") {
        return "+63" . substr($digits, 1);
    }

    if (strlen($digits) === 12 && substr($digits, 0, 2) === "63") {
        return "+" . $digits;
    }

    if (strlen($digits) === 10 && substr($digits, 0, 1) === "9") {
        return "+63" . $digits;
    }

    return false;
}

// Count resident accounts with registered mobile numbers.
$countStmt = $conn->prepare(
    "SELECT COUNT(*) AS total
     FROM users
     WHERE role = 'user'
     AND mobile_number IS NOT NULL
     AND mobile_number <> ''"
);

if ($countStmt) {
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $countRow = $countResult->fetch_assoc();

    $totalResidents = (int) ($countRow["total"] ?? 0);

    $countStmt->close();
}

// Process broadcast request.
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $submittedToken = $_POST["csrf_token"] ?? "";

    if (!hash_equals($csrfToken, $submittedToken)) {

        $error = "Invalid request. Please refresh the page and try again.";

    } else {

        $alertLevel = trim($_POST["alert_level"] ?? "");
        $alertDetails = trim($_POST["alert_details"] ?? "");
        $confirmed = isset($_POST["confirm_broadcast"]);

        if (!in_array($alertLevel, $allowedLevels, true)) {

            $error = "Please select a valid alert level.";

        } elseif ($alertDetails === "") {

            $error = "Please enter the flood alert announcement.";

        } elseif (strlen($alertDetails) > 500) {

            $error = "The announcement must not exceed 500 characters.";

        } elseif (!$confirmed) {

            $error = "Please confirm before sending the flood alert.";

        } else {

            // Retrieve all registered residents.
            $residentStmt = $conn->prepare(
                "SELECT id, mobile_number
                 FROM users
                 WHERE role = 'user'
                 AND mobile_number IS NOT NULL
                 AND mobile_number <> ''"
            );

            if (!$residentStmt) {

                $error = "Could not retrieve the registered residents.";

            } else {

                $residentStmt->execute();
                $residentResult = $residentStmt->get_result();

                $recipients = [];
                $seenNumbers = [];

                while ($resident = $residentResult->fetch_assoc()) {

                    $phone = normalizeMobileNumber(
                        $resident["mobile_number"]
                    );

                    if ($phone === false) {
                        $invalidCount++;
                        continue;
                    }

                    // Avoid duplicate SMS to the same number.
                    if (isset($seenNumbers[$phone])) {
                        continue;
                    }

                    $seenNumbers[$phone] = true;
                    $recipients[] = $phone;
                }

                $residentStmt->close();

                $uniqueRecipientCount = count($recipients);

                if ($uniqueRecipientCount === 0) {

                    $error = "There are no valid registered resident mobile numbers.";

                } else {

                    // Prepare the SMS content.
                    $smsContent =
                        "FLOOD ALERT - " . $alertLevel . "\n" .
                        "Barangay 37-Bitano, Legazpi City\n\n" .
                        $alertDetails . "\n\n" .
                        "Please stay alert and follow local authorities' instructions.";

                    // Create a unique broadcast ID.
                    $broadcastId = bin2hex(random_bytes(16));

                    $adminUserId = (int) $_SESSION["user_id"];

                    // Prepare SMS history saving.
                    $historyStmt = $conn->prepare(
                        "INSERT INTO sms_history (
                            broadcast_id,
                            admin_user_id,
                            recipient_number,
                            alert_level,
                            announcement,
                            sms_content,
                            sending_status,
                            http_status,
                            provider_response
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    );

                    if (!$historyStmt) {

                        // Do not send SMS if history cannot be prepared.
                        $error = "Unable to prepare SMS history. No SMS was sent.";

                    } else {

                        $historySaveFailed = 0;

                        // Send SMS individually to each unique recipient.
                        foreach ($recipients as $recipient) {

                            $payload = [
                                "recipient" => $recipient,
                                "content" => $smsContent,
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

                            $httpStatus = curl_getinfo(
                                $ch,
                                CURLINFO_HTTP_CODE
                            );

                            $curlError = curl_error($ch);

                            curl_close($ch);

                            // Determine request status.
                            if (
                                $apiResponse !== false &&
                                $httpStatus >= 200 &&
                                $httpStatus < 300
                            ) {

                                $sendingStatus = "Accepted";
                                $acceptedCount++;

                            } else {

                                $sendingStatus = "Failed";
                                $failedCount++;
                            }

                            // Store the provider response.
                            if ($apiResponse === false) {

                                $providerResponse = "cURL error: " . $curlError;

                            } else {

                                $providerResponse = (string) $apiResponse;
                            }

                            $providerResponse = substr(
                                $providerResponse,
                                0,
                                5000
                            );

                            $httpStatusValue = $httpStatus > 0
                                ? $httpStatus
                                : null;

                            // Save this recipient's SMS attempt.
                            $historyStmt->bind_param(
                                "sisssssis",
                                $broadcastId,
                                $adminUserId,
                                $recipient,
                                $alertLevel,
                                $alertDetails,
                                $smsContent,
                                $sendingStatus,
                                $httpStatusValue,
                                $providerResponse
                            );

                            if (!$historyStmt->execute()) {
                                $historySaveFailed++;
                            }
                        }

                        $historyStmt->close();

                        if ($acceptedCount > 0) {

                            $message =
                                "Broadcast process completed. " .
                                $acceptedCount .
                                " SMS request(s) accepted by UniSMS.";
                        }

                        if ($failedCount > 0) {

                            $error =
                                $failedCount .
                                " SMS request(s) were not accepted. Please check your UniSMS account and connection.";
                        }

                        if ($historySaveFailed > 0) {

                            $error .= ($error !== "" ? " " : "") .
                                $historySaveFailed .
                                " SMS history record(s) could not be saved.";
                        }

                        if ($acceptedCount === 0 && $failedCount === 0) {

                            $error = "No SMS requests were processed.";
                        }
                    }
                }
            }
        }
    }

    // Generate a new token to prevent simple form resubmission.
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    $csrfToken = $_SESSION["csrf_token"];
}

// Retrieve latest 100 SMS history records.
$historyRecords = [];

$historyQuery = $conn->query(
    "SELECT
        id,
        broadcast_id,
        recipient_number,
        alert_level,
        announcement,
        sending_status,
        http_status,
        created_at
     FROM sms_history
     ORDER BY id DESC
     LIMIT 100"
);

if ($historyQuery) {

    while ($historyRow = $historyQuery->fetch_assoc()) {
        $historyRecords[] = $historyRow;
    }

    $historyQuery->free();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Dashboard | Flood Warning System</title>

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
            max-width: 1150px;
            margin: auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 18px;
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

        .logout-link {
            color: white;
            text-decoration: none;
            font-weight: bold;
            white-space: nowrap;
        }

        .logout-link:hover {
            text-decoration: underline;
        }

        main {
            width: min(1100px, 92%);
            margin: 35px auto;
        }

        .welcome {
            margin-bottom: 24px;
        }

        .welcome h2 {
            margin: 0 0 8px;
            font-size: 27px;
        }

        .welcome p {
            margin: 0;
            color: #667b92;
            line-height: 1.5;
        }

        .summary-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding: 23px 26px;
            margin-bottom: 25px;
            background: white;
            border-radius: 14px;
            box-shadow: 0 8px 25px rgba(23, 60, 112, 0.09);
        }

        .summary-label {
            color: #61748b;
            font-size: 14px;
            font-weight: bold;
        }

        .summary-number {
            margin-top: 7px;
            color: #173c70;
            font-size: 34px;
            font-weight: bold;
        }

        .summary-note {
            color: #718197;
            font-size: 13px;
            text-align: right;
            line-height: 1.5;
        }

        .card {
            overflow: hidden;
            margin-bottom: 28px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 12px 32px rgba(23, 60, 112, 0.12);
        }

        .card-header {
            padding: 24px 28px;
            background: #f2f7fd;
            border-bottom: 1px solid #dce7f3;
        }

        .card-header h2 {
            margin: 0;
            font-size: 23px;
        }

        .card-header p {
            margin: 9px 0 0;
            color: #61748b;
            line-height: 1.5;
        }

        .card-body {
            padding: 28px;
        }

        .form-group {
            margin-bottom: 22px;
        }

        .form-group label {
            display: block;
            margin-bottom: 9px;
            font-size: 14px;
            font-weight: bold;
            color: #40566e;
        }

        select,
        textarea {
            width: 100%;
            padding: 13px 14px;
            border: 1px solid #d4dfeb;
            border-radius: 9px;
            background: #fff;
            color: #183451;
            font-family: Arial, sans-serif;
            font-size: 15px;
        }

        select:focus,
        textarea:focus {
            outline: none;
            border-color: #315f94;
            box-shadow: 0 0 0 3px rgba(49, 95, 148, 0.12);
        }

        textarea {
            min-height: 135px;
            resize: vertical;
            line-height: 1.6;
        }

        .helper {
            margin-top: 7px;
            color: #718197;
            font-size: 12px;
        }

        .preview {
            padding: 18px;
            margin-top: 8px;
            border-left: 4px solid #f0bd2f;
            border-radius: 8px;
            background: #fff9e8;
            line-height: 1.6;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        .confirm {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-top: 22px;
            color: #40566e;
            line-height: 1.5;
        }

        .confirm input {
            width: 17px;
            height: 17px;
            margin-top: 3px;
            flex-shrink: 0;
        }

        .send-button {
            width: 100%;
            margin-top: 23px;
            padding: 16px;
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
            margin-bottom: 20px;
            padding: 14px 16px;
            border-radius: 8px;
            line-height: 1.7;
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

        .warning-note {
            margin-top: 20px;
            padding: 15px;
            border: 1px solid #f0d98e;
            border-radius: 9px;
            background: #fff9e8;
            color: #745a16;
            font-size: 13px;
            line-height: 1.6;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        .history-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 850px;
        }

        .history-table th {
            padding: 14px 12px;
            background: #173c70;
            color: white;
            text-align: left;
            font-size: 13px;
            white-space: nowrap;
        }

        .history-table td {
            padding: 14px 12px;
            border-bottom: 1px solid #e5ebf2;
            color: #40566e;
            font-size: 13px;
            vertical-align: top;
        }

        .history-table tr:hover td {
            background: #f7faff;
        }

        .status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            white-space: nowrap;
        }

        .status-accepted {
            color: #176333;
            background: #e7f6ec;
        }

        .status-failed {
            color: #9b2424;
            background: #fff0f0;
        }

        .empty-history {
            padding: 30px;
            text-align: center;
            color: #718197;
            line-height: 1.6;
        }

        .footer-note {
            margin: 20px 0 0;
            color: #718197;
            font-size: 12px;
            text-align: center;
            line-height: 1.5;
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
                margin: 25px auto;
            }

            .summary-card {
                align-items: flex-start;
                flex-direction: column;
            }

            .summary-note {
                text-align: left;
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
                Barangay 37-Bitano, Legazpi City | Administrator Panel
            </p>
        </div>

        <a class="logout-link" href="logout.php">Log Out</a>

    </div>

</header>

<main>

    <section class="welcome">

        <h2>
            Welcome, <?= htmlspecialchars($adminName, ENT_QUOTES, "UTF-8") ?>!
        </h2>

        <p>
            Manage flood announcements and send SMS alerts to registered residents.
        </p>

    </section>

    <section class="summary-card">

        <div>
            <div class="summary-label">REGISTERED RESIDENTS</div>

            <div class="summary-number">
                <?= number_format($totalResidents) ?>
            </div>
        </div>

        <div class="summary-note">
            Eligible resident accounts with a registered mobile number
        </div>

    </section>

    <!-- SMS BROADCAST SECTION -->

    <section class="card">

        <div class="card-header">

            <h2>Send Flood Alert</h2>

            <p>
                Prepare an announcement and submit it to the registered
                residents of Barangay 37-Bitano.
            </p>

        </div>

        <div class="card-body">

            <?php if ($message !== ""): ?>

                <div class="notice success" role="status">

                    <?= htmlspecialchars($message, ENT_QUOTES, "UTF-8") ?>

                    <br>
                    Accepted by UniSMS: <?= $acceptedCount ?>

                    <br>
                    Not accepted: <?= $failedCount ?>

                    <br>
                    Unique recipients: <?= $uniqueRecipientCount ?>

                    <br>
                    Invalid mobile numbers skipped: <?= $invalidCount ?>

                </div>

            <?php endif; ?>

            <?php if ($error !== ""): ?>

                <div class="notice error" role="alert">

                    <?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?>

                    <?php if ($acceptedCount > 0 || $failedCount > 0): ?>

                        <br>
                        Accepted by UniSMS: <?= $acceptedCount ?>

                        <br>
                        Not accepted: <?= $failedCount ?>

                        <br>
                        Unique recipients: <?= $uniqueRecipientCount ?>

                        <br>
                        Invalid mobile numbers skipped: <?= $invalidCount ?>

                    <?php endif; ?>

                </div>

            <?php endif; ?>

            <form method="POST">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, "UTF-8") ?>"
                >

                <div class="form-group">

                    <label for="alert_level">Flood Alert Level</label>

                    <select id="alert_level" name="alert_level" required>

                        <option value="">Select Alert Level</option>
                        <option value="WARNING">WARNING</option>
                        <option value="HIGH ALERT">HIGH ALERT</option>
                        <option value="CRITICAL">CRITICAL</option>
                        <option value="ALL CLEAR">ALL CLEAR</option>

                    </select>

                </div>

                <div class="form-group">

                    <label for="alert_details">
                        Announcement / Instructions
                    </label>

                    <textarea
                        id="alert_details"
                        name="alert_details"
                        maxlength="500"
                        placeholder="Enter the flood announcement and instructions for residents..."
                        required
                    ></textarea>

                    <div class="helper">
                        Maximum of 500 characters. Keep the announcement clear and concise.
                    </div>

                </div>

                <div class="form-group">

                    <label>SMS Message Preview</label>

                    <div class="preview" id="messagePreview">FLOOD ALERT - [ALERT LEVEL]
Barangay 37-Bitano, Legazpi City

[Your announcement will appear here.]

Please stay alert and follow local authorities' instructions.</div>

                </div>

                <label class="confirm">

                    <input
                        type="checkbox"
                        name="confirm_broadcast"
                        value="1"
                        required
                    >

                    <span>
                        I confirm that this is an authorized announcement
                        and that I want to submit it to all registered residents.
                    </span>

                </label>

                <button class="send-button" type="submit">
                    Send Flood Alert to All Residents
                </button>

            </form>

            <div class="warning-note">

                <strong>Important:</strong> Sending a broadcast may use SMS
                credits for every valid registered resident number. A request
                accepted by UniSMS does not guarantee delivery to the recipient's
                phone. Check your provider account and delivery reports.

            </div>

        </div>

    </section>

    <!-- SMS SENDING HISTORY SECTION -->

    <section class="card">

        <div class="card-header">

            <h2>SMS Sending History</h2>

            <p>
                View the latest 100 SMS sending attempts recorded by the system.
            </p>

        </div>

        <div class="card-body">

            <?php if (count($historyRecords) > 0): ?>

                <div class="table-wrapper">

                    <table class="history-table">

                        <thead>

                            <tr>
                                <th>Date and Time</th>
                                <th>Recipient</th>
                                <th>Alert Level</th>
                                <th>Announcement</th>
                                <th>Status</th>
                                <th>HTTP Status</th>
                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach ($historyRecords as $record): ?>

                                <tr>

                                    <td>
                                        <?= htmlspecialchars($record["created_at"], ENT_QUOTES, "UTF-8") ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($record["recipient_number"], ENT_QUOTES, "UTF-8") ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($record["alert_level"], ENT_QUOTES, "UTF-8") ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($record["announcement"], ENT_QUOTES, "UTF-8") ?>
                                    </td>

                                    <td>

                                        <?php if ($record["sending_status"] === "Accepted"): ?>

                                            <span class="status status-accepted">
                                                Accepted
                                            </span>

                                        <?php else: ?>

                                            <span class="status status-failed">
                                                Failed
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                    <td>
                                        <?= $record["http_status"] === null
                                            ? "N/A"
                                            : htmlspecialchars((string) $record["http_status"], ENT_QUOTES, "UTF-8") ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="empty-history">

                    <strong>No SMS history records yet.</strong>

                    <br>

                    SMS sending attempts will appear here after a broadcast.

                </div>

            <?php endif; ?>

        </div>

    </section>

    <p class="footer-note">
        Flood Warning System | Barangay 37-Bitano, Legazpi City
    </p>

</main>

<script>

    const levelInput = document.getElementById("alert_level");
    const detailsInput = document.getElementById("alert_details");
    const preview = document.getElementById("messagePreview");

    function updatePreview() {

        const level = levelInput.value || "[ALERT LEVEL]";

        const details = detailsInput.value.trim() ||
            "[Your announcement will appear here.]";

        preview.textContent =
            "FLOOD ALERT - " + level +
            "\nBarangay 37-Bitano, Legazpi City\n\n" +
            details +
            "\n\nPlease stay alert and follow local authorities' instructions.";
    }

    levelInput.addEventListener("change", updatePreview);
    detailsInput.addEventListener("input", updatePreview);

</script>

</body>
</html>