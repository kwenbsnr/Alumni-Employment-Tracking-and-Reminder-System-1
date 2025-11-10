<?php
// alumni_layout.php
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "alumni") {
    header("Location: ../login/login.php");
    exit();
}
include("../connect.php");
$user_id = $_SESSION["user_id"];

// Fetch profile data
$stmt = $conn->prepare("
    SELECT ap.first_name, ap.last_name, u.email, u.name as user_name, ap.photo_path
    FROM users u
    LEFT JOIN alumni_profile ap ON u.user_id = ap.user_id
    WHERE u.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$profile = $result->fetch_assoc() ?: [];
$stmt->close();

// Build full name
$full_name = 'Alumni';
if (!empty($profile)) {
    if (!empty($profile['first_name']) || !empty($profile['last_name'])) {
        $full_name = trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? ''));
    } elseif (!empty($profile['user_name'])) {
        $full_name = $profile['user_name'];
    }
}
$user_email = $profile['email'] ?? '';
$photo_path = $profile['photo_path'] ?? null;

// Page title fallback
$page_title = $page_title ?? "Alumni Page";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>

    <!-- Tailwind CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="alumni_format.css">

    <style>
        :root {
            --primary-green: #034f03;
            --forest-green: #026a02;
            --lime-green: #016801;
            --sea-green: #1d681d;
            --light-bg: #06690e;
            --dark-text: #1C1C1C;
        }
        .gradient-bg {
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--forest-green) 100%);
        }
        .card-hover { transition: all 0.3s ease; }
        .sidebar-item {
            transition: all 0.3s ease;
            color: #fff;
            padding-left: 14px;
        }
        .sidebar-item:hover {
            background: rgba(50, 205, 50, 0.1);
            border-left: 4px solid var(--lime-green);
        }
        .sidebar-item.active {
            background: rgba(34, 139, 34, 0.3);
            border-left: 4px solid var(--lime-green);
            padding-left: 10px;
        }
        .profile-avatar {
            background: linear-gradient(135deg, var(--lime-green) 0%, var(--sea-green) 100%);
        }
        .stats-card {
            background-color: white;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        .sidebar-wrapper {
            height: 100vh;
            overflow-y: auto;
            position: sticky;
            top: 0;
        }
        .sidebar-wrapper::-webkit-scrollbar {
            width: 6px;
        }
        .sidebar-wrapper::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.3);
            border-radius: 3px;
        }
        .hidden { display: none; }
        #profileUpdateModal {
            opacity: 0;
            transition: opacity 0.5s;
        }
        #profileUpdateModal.show { opacity: 1; }

        /* Sidebar Profile */
        .sidebar-profile {
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            margin-bottom: 1rem;
        }

        /* 27mm ≈ 102px (coin size) */
        .sidebar-profile-avatar {
            width: 102px;
            height: 102px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 36px;
            color: white;
            background: linear-gradient(135deg, var(--lime-green) 0%, var(--sea-green) 100%);
            text-transform: uppercase;
        }

        /* Enhanced Dashboard Styles */
        .dashboard-card {
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .dashboard-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .progress-bar {
            transition: width 0.5s ease-in-out;
        }

        .quick-action-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .quick-action-card:hover {
            transform: translateY(-2px);
            border-color: currentColor;
        }

        .status-badge {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        /* Card entrance animations */
        @keyframes cardEntrance {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .dashboard-card {
            animation: cardEntrance 0.5s ease-out;
        }

        .dashboard-card:nth-child(1) { animation-delay: 0.1s; }
        .dashboard-card:nth-child(2) { animation-delay: 0.2s; }
        .dashboard-card:nth-child(3) { animation-delay: 0.3s; }
        .dashboard-card:nth-child(4) { animation-delay: 0.4s; }

        /* Responsive */
        @media (max-width: 768px) {
            .grid-cols-1.md\:grid-cols-2 > div { padding: 1.5rem !important; }
            .dashboard-card { padding: 1.25rem !important; }
            .text-4xl { font-size: 2rem; }
            .quick-action-card { padding: 1rem !important; }
        }

        @media (max-width: 640px) {
            .grid-cols-1.md\:grid-cols-2 {
                grid-template-columns: 1fr;
            }
            .dashboard-card {
                margin-bottom: 1rem;
            }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex">
    <!-- ==================== SIDEBAR ==================== -->
    <aside class="w-72 gradient-bg text-white flex-shrink-0">
        <div class="sidebar-wrapper flex flex-col justify-between">
            <!-- Top Section -->
            <div class="p-6">
                <div class="flex items-center space-x-3 mb-8">
                    <div class="w-10 h-10 rounded-full bg-white bg-opacity-20 flex items-center justify-center">
                        <i class="fas fa-graduation-cap text-lg" aria-hidden="true"></i>
                    </div>
                    <h2 class="font-bold text-lg">Alumni Portal</h2>
                </div>

                <!-- Profile Section -->
                <div class="sidebar-profile pb-6 mb-6">
                    <div class="flex flex-col items-center text-center space-y-4">
                        <!-- Profile Picture (27mm coin size) -->
                        <div class="sidebar-profile-avatar">
                            <?php
                            if ($photo_path && file_exists("../" . $photo_path)) {
                                echo '<img src="../' . htmlspecialchars($photo_path) . '" alt="Profile" class="w-full h-full object-cover">';
                            } else {
                                $initials = 'AL';
                                if ($full_name !== 'Alumni') {
                                    $parts = array_filter(explode(' ', $full_name));
                                    $initials = '';
                                    foreach ($parts as $part) {
                                        $initials .= strtoupper(substr(trim($part), 0, 1));
                                    }
                                    $initials = substr($initials, 0, 2);
                                }
                                echo htmlspecialchars($initials);
                            }
                            ?>
                        </div>

                        <div class="w-full">
                            <h3 class="font-bold text-lg truncate"><?php echo htmlspecialchars($full_name); ?></h3>
                            <p class="text-sm text-gray-200 truncate"><?php echo htmlspecialchars($user_email); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Navigation -->
                <nav class="space-y-2">
                    <a href="alumni_dashboard.php"
                       class="sidebar-item <?php echo ($active_page ?? '') === 'dashboard' ? 'active' : ''; ?> flex items-center space-x-3 p-3 rounded-lg">
                        <i class="fas fa-tachometer-alt w-5" aria-hidden="true"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="alumni_profile.php"
                       class="sidebar-item <?php echo ($active_page ?? '') === 'profile' ? 'active' : ''; ?> flex items-center space-x-3 p-3 rounded-lg">
                        <i class="fas fa-user w-5" aria-hidden="true"></i>
                        <span>Profile Management</span>
                    </a>
                </nav>
            </div>

            <!-- Logout -->
            <div class="p-6">
                <hr class="border-gray-400 my-6">
                <a href="../login/logout.php"
                   class="flex items-center space-x-3 text-white hover:text-red-500 p-3 rounded-lg">
                    <i class="fas fa-sign-out-alt text-xl" aria-hidden="true"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </aside>

    <!-- ==================== MAIN CONTENT ==================== -->
    <div class="flex-1 flex flex-col">
        <!-- Top Bar -->
        <header class="bg-white shadow-sm border-b p-4">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>
                <div class="flex items-center space-x-6">
                    <!-- Notification Bell -->
                    <div class="relative">
                        <button id="notificationBtn" class="relative text-gray-600 hover:text-blue-600">
                            <i class="fas fa-bell text-xl"></i>
                            <span class="absolute top-0 right-0 w-2 h-2 bg-red-500 rounded-full"></span>
                        </button>
                        <div id="notifPopup" class="absolute right-0 mt-2 w-80 bg-white rounded-xl shadow-2xl hidden z-50 overflow-hidden">
                            <div class="p-4 border-b font-bold text-gray-700 flex justify-between items-center sticky top-0 bg-white">
                                Notifications
                                <button id="markReadBtn" class="text-xs text-blue-600 hover:underline">Mark all as read</button>
                            </div>
                            <div class="p-3 space-y-4">
                                <div class="border-b pb-3 border-gray-100">
                                    <h2 class="font-semibold text-gray-800"><i class="fas fa-bell text-blue-500 mr-2"></i> New Job Fair Event</h2>
                                    <p class="text-sm text-gray-600 pl-6">Join us at the upcoming job fair this Sept 15, 2025 at the University Main Hall.</p>
                                    <span class="text-xs text-gray-400 pl-6">Posted: Sept 5, 2025</span>
                                </div>
                                <div class="border-b pb-3 border-gray-100">
                                    <h2 class="font-semibold text-gray-800"><i class="fas fa-bullhorn text-yellow-500 mr-2"></i> Alumni Gathering</h2>
                                    <p class="text-sm text-gray-600 pl-6">Reconnect with your batchmates at the Alumni Homecoming on Oct 10, 2025.</p>
                                    <span class="text-xs text-gray-400 pl-6">Posted: Sept 1, 2025</span>
                                </div>
                                <div>
                                    <h2 class="font-semibold text-gray-800"><i class="fas fa-briefcase text-green-500 mr-2"></i> Job Opportunity</h2>
                                    <p class="text-sm text-gray-600 pl-6">TechCorp Inc. is hiring Software Engineers. Apply now!</p>
                                    <span class="text-xs text-gray-400 pl-6">Posted: Aug 25, 2025</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Page Content -->
        <main class="flex-1 p-6 overflow-auto">
            <?php echo $page_content ?? ''; ?>
        </main>
    </div>

    <!-- ==================== SCRIPTS ==================== -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const notifButton = document.getElementById('notificationBtn');
            const notifPopup = document.getElementById('notifPopup');

            notifButton.addEventListener('click', (e) => {
                e.stopPropagation();
                notifPopup.classList.toggle('hidden');
            });

            document.addEventListener('click', (e) => {
                if (!notifPopup.classList.contains('hidden') &&
                    !notifPopup.contains(e.target) &&
                    e.target !== notifButton) {
                    notifPopup.classList.add('hidden');
                }
            });

            document.getElementById('markReadBtn').addEventListener('click', () => {
                notifButton.querySelector('span').classList.add('hidden');
            });
        });
    </script>
</body>
</html>