
<?php
session_start();
require_once "db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit;
}

$username = trim($_POST["username"] ?? "");
$password = $_POST["password"] ?? "";

if ($username === "" || $password === "") {
    header("Location: index.php?error=1");
    exit;
}

$stmt = $conn->prepare(
    "SELECT id, full_name, first_name, middle_name, last_name, suffix, password, role
     FROM users
     WHERE username = ?
     LIMIT 1"
);

$stmt->bind_param("s", $username);
$stmt->execute();

$result = $stmt->get_result();
$user = $result->fetch_assoc();

$stmt->close();

if ($user && password_verify($password, $user["password"])) {

    session_regenerate_id(true);

    $fullName = trim(
        ($user["first_name"] ?? "") . " " .
        ($user["middle_name"] ?? "") . " " .
        ($user["last_name"] ?? "") . " " .
        ($user["suffix"] ?? "")
    );

    if ($fullName === "") {
        $fullName = $user["full_name"] ?? $username;
    }

    $_SESSION["user_id"] = (int) $user["id"];
    $_SESSION["full_name"] = $fullName;
    $_SESSION["role"] = $user["role"] ?? "user";

    if ($_SESSION["role"] === "admin") {
        header("Location: admin_dashboard.php");
    } else {
        header("Location: dashboard.php");
    }

    exit;

} else {
    header("Location: index.php?error=1");
    exit;
}