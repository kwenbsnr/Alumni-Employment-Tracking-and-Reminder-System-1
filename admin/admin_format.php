<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login/login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? "Admin Dashboard"; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../assets/js/phil-address/phil.min.js"></script>  
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="admin_format.css" rel="stylesheet">
</head>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('success')) {
        showToast(urlParams.get('success'));
    } else if (urlParams.has('error')) {
        showToast(urlParams.get('error'), 'error');
    }
});
</script>

<body class="bg-gray-50">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <nav class="w-72 admin-gradient-bg text-white flex-shrink-0 flex flex-col h-screen justify-between">
            <div class="p-6 flex-grow flex flex-col">
                <div class="flex items-center space-x-3 mb-8">
                    <div class="w-10 h-10 rounded-full bg-white bg-opacity-20 flex items-center justify-center">
                        <i class="fas fa-user-shield text-lg" aria-hidden="true"></i>
                    </div>
                    <h2 class="font-bold text-lg">Admin</h2>
                </div>
                <ul class="space-y-2 flex-grow">
                    <li><a href="admin_dashboard.php" class="sidebar-item admin-sidebar-item <?php echo ($active_page ?? '') === 'dashboard' ? 'active' : ''; ?> flex items-center space-x-3 p-3 rounded-lg">
                        <i class="fas fa-tachometer-alt w-5" aria-hidden="true"></i>
                        <span>Dashboard</span>
                    </a></li>
                    <li><a href="alumni_management.php" class="sidebar-item admin-sidebar-item <?php echo ($active_page ?? '') === 'alumni_management' ? 'active' : ''; ?> flex items-center space-x-3 p-3 rounded-lg">
                        <i class="fas fa-users w-5" aria-hidden="true"></i>
                        <span>Alumni Records</span>
                    </a></li>
                </ul>

                <!-- Logout -->
                <div class="p-6">
                    <hr class="border-gray-400 my-6">
                    <a href="../login/logout.php" class="flex items-center space-x-3 text-white-300 hover:text-red-500 p-3 rounded-lg">
                        <i class="fas fa-sign-out-alt text-xl" aria-hidden="true"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col">
            <!-- Top Bar -->
            <header class="bg-white shadow-sm border-b p-4">
                <div class="flex items-center justify-between">
                    <h1 class="text-2xl font-bold text-gray-800">
                        <?php echo $page_title ?? "Admin Dashboard"; ?>
                    </h1>
                    <div class="flex items-center space-x-4">
                        <button class="relative text-gray-600 hover:text-blue-600 transition p-2 rounded-full hover:bg-gray-100">
                            <i class="fas fa-bell text-xl"></i>
                            <span class="absolute top-1 right-1 h-2 w-2 bg-red-500 rounded-full border-2 border-white"></span>
                        </button>
                            <div class="flex items-center space-x-3">
                                <div class="admin-avatar w-10 h-10 rounded-full flex items-center justify-center text-white font-bold">
                                    <?php 
                                    // Fetch admin name from users table
                                    $stmt = $conn->prepare("SELECT name FROM users WHERE user_id = ? AND role = 'admin'");
                                    $stmt->bind_param("i", $_SESSION["user_id"]);
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    $admin_name = 'AD';
                                    if ($row = $result->fetch_assoc()) {
                                        $admin_name = strtoupper(substr($row['name'], 0, 2)); // First 2 letters
                                    }
                                    $stmt->close();
                                    echo htmlspecialchars($admin_name);
                                    ?>
                                </div>
                                <div class="hidden md:block">
                                    <p class="font-medium text-gray-800">
                                        <?php 
                                        // Display full name + email
                                        echo htmlspecialchars($row['name'] ?? $_SESSION["email"]); 
                                        ?>
                                    </p>
                                    <p class="text-sm text-gray-500">
                                        <?php echo htmlspecialchars($_SESSION["email"]); ?>
                                    </p>
                                </div>
                            </div>
                    </div>
                </div>
            </header>










            
            <!-- Dynamic Content -->
            <main class="flex-1 p-6 overflow-auto">
                <?php echo $page_content ?? ''; ?>
            </main>
        </div>
    </div>

    <!-- Custom Toast -->
    <div id="customToast" class="fixed bottom-4 right-4 bg-green-500 text-white p-4 rounded-lg shadow-lg flex items-center space-x-2 z-50">
        <i class="fas fa-check-circle"></i>
        <span id="toastMessage"></span>
    </div>

    <script>
    function showToast(message, type = 'success') {
        const toast = document.getElementById('customToast');
        const toastMessage = document.getElementById('toastMessage');
        const icon = toast.querySelector('i');
        toastMessage.textContent = message;
        if (type === 'error') {
            toast.classList.add('bg-red-500');
            toast.classList.remove('bg-green-500');
            icon.classList.add('fa-times-circle');
            icon.classList.remove('fa-check-circle');
        } else {
            toast.classList.add('bg-green-500');
            toast.classList.remove('bg-red-500');
            icon.classList.add('fa-check-circle');
            icon.classList.remove('fa-times-circle');
        }
        toast.classList.add('show');
        setTimeout(() => {
            toast.classList.remove('show');
        }, 3000);
    }
    
    </script>
</body>
</html>