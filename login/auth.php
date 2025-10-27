<?php
// ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL); // Uncomment for debugging

ob_start(); // Buffer output to allow clean headers
session_start();
include("../connect.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"] ?? '');
    $password = trim($_POST["password"] ?? '');
    $role = trim($_POST["role"] ?? '');

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['login_error'] = "Invalid email format.";
        ob_end_clean();
        header("Location: login.php");
        exit;
    }

    // Check if email exists (prepared statement for security)
    $stmt = $conn->prepare("SELECT user_id, email, password, role FROM users WHERE email = ? AND role = ?");
    $stmt->bind_param("ss", $email, $role);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Verify hashed password
        if (password_verify($password, $user['password'])) {
            // Regenerate session ID for security (prevent fixation)
            session_regenerate_id(true);
            
            // Save session
            $_SESSION["user_id"] = $user["user_id"];
            $_SESSION["email"] = $user["email"];
            $_SESSION["role"] = $user["role"];

            $stmt->close();
            $conn->close();
            ob_end_clean(); // Flush buffer before redirect

            // Redirect based on role
            if ($user["role"] === "admin") {
                header("Location: ../admin/admin_dashboard.php");
            } else {
                header("Location: ../alumni/alumni_dashboard.php");
            }
            exit();
        } else {
            error_log("Failed login attempt for email: $email"); // Basic logging
            $_SESSION['login_error'] = "Invalid password.";
            $stmt->close();
            $conn->close();
            ob_end_clean();
            header("Location: login.php");
            exit();
        }
    } else {
        error_log("User not found or role mismatch for email: $email");
        $_SESSION['login_error'] = "User not found or role mismatch.";
        $stmt->close();
        $conn->close();
        ob_end_clean();
        header("Location: login.php");
        exit();
    }
}
$conn->close();
// No final ob_end_clean() needed here, as cases are handled above
?>