<?php
// auth.php
ob_start();
session_start();
include("../connect.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email    = trim($_POST["email"]    ?? '');
    $password = trim($_POST["password"] ?? '');
    $role     = trim($_POST["role"]     ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['login_error'] = "Invalid email format.";
        ob_end_clean();
        header("Location: login.php");
        exit;
    }

    $stmt = $conn->prepare("SELECT user_id, email, password, role FROM users WHERE email = ? AND role = ?");
    $stmt->bind_param("ss", $email, $role);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION["user_id"] = $user["user_id"];
            $_SESSION["email"]   = $user["email"];
            $_SESSION["role"]    = $user["role"];
            $stmt->close();
            $conn->close();
            ob_end_clean();
            header($user["role"] === "admin"
                ? "Location: ../admin/admin_dashboard.php"
                : "Location: ../alumni/alumni_dashboard.php");
            exit;
        } else {
            error_log("Failed login – wrong password for $email");
            $_SESSION['login_error'] = "Invalid password.";
        }
    } else {
        error_log("Failed login – user/role not found for $email");
        $_SESSION['login_error'] = "User not found or role mismatch.";
    }

    $stmt->close();
    $conn->close();
    ob_end_clean();
    header("Location: login.php");
    exit;
}
$conn->close();
?>