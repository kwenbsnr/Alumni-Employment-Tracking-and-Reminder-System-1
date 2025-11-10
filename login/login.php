<?php
// login.php
session_start();
include("../connect.php");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JHCSC IT Alumni Portal Login</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" rel="stylesheet">
    <link href="../assets/css/output.css" rel="stylesheet">
    <link href="login.css" rel="stylesheet">
    <style>
        
        /* ---- LOCK PAGE & HIDE SCROLLBAR ---- */
        html, body { height: 100%; margin: 0; overflow: hidden; }
        ::-webkit-scrollbar { display: none; }
        body { -ms-overflow-style: none; scrollbar-width: none; }
        
        /* Enhanced Error Message Styles */
        .error-container {
            margin-top: 0.75rem;
            padding: 0.75rem 1rem;
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 0.5rem;
            color: #991b1b;
            font-size: 0.875rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            animation: slideDown 0.3s ease-out;
            box-shadow: 0 2px 6px rgba(254, 178, 178, 0.2);
        }
        
        .error-container i {
            font-size: 1rem;
            color: #dc2626;
        }
        
        .error-container span {
            flex: 1;
        }
        
        @keyframes slideDown {
            from { 
                opacity: 0; 
                transform: translateY(-8px); 
            }
            to { 
                opacity: 1; 
                transform: translateY(0); 
            }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <header class="fixed top-0 left-0 w-full z-50">
        <div class="top-green-bar"></div>
        <div class="top-links">
            <div class="jhcsc-card-link">
                <a href="https://jhcsc.edu.ph/" target="_blank">
                     <i class="fas fa-home mr-1"></i> Go to jhcsc.edu.ph
                </a>
            </div>
            <div class="library-card-link">
                <a href="https://opac.jhcsc.edu.ph/" target="_blank">
                    <i class="fas fa-book-open mr-1"></i> College eLibrary
                </a>
            </div>
            <div class="facebook-card-link">
                <a href="https://www.facebook.com/OfficialJHCSCPresident" target="_blank">
                    <i class="fab fa-facebook-f mr-1"></i> Facebook Page
                </a>
            </div>
        </div>
    </header>

    <div id="loginPage" class="login-container">
        <div class="school-branding flex items-center justify-center p-8">
           
            <div class="text-center text-white z-10 p-4 max-w-lg">
                <div class="flex items-center justify-center gap-6 mb-18">
                    <div class="w-32 h-32 rounded-full flex items-center justify-center shadow-lg">
                        <img src="images/favicon.png" alt="JHCSC Logo" class="h-full object-cover rounded-xl">
                    </div>
                    <div class="w-32 h-32 rounded-full flex items-center justify-center shadow-lg">
                        <img src="images/socs-logo.png" alt="SCS Logo" class="h-full object-cover rounded-xl">
                    </div>
                </div>
                <h1 class="text-4xl font-extrabold mb-7">JHCSC BSIT Alumni Monitoring System</h1>
                <p class="text-xl mb-30 opacity-95">Connecting Graduates, Building Futures</p>
                <div class="mt-6 grid grid-cols-2 gap-4">
                    <div class="h-24 bg-white bg-opacity-20 rounded-xl flex items-center justify-center shadow-inner">
                        <i class="fas fa-graduation-cap text-3xl"></i>
                    </div>
                    <div class="h-24 bg-white bg-opacity-20 rounded-xl flex items-center justify-center shadow-inner">
                        <i class="fas fa-building text-3xl"></i>
                    </div>
                </div>
            </div>
        </div>
                
        <div class="login-right">
            <div class="login-box">
                <div class="text-center mb-8">
                    <h2 class="text-3xl font-bold text-gray-800 mb-2">Welcome Back</h2>
                    <p class="text-gray-600">Please select your role and sign in</p>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-8">
                    <div class="role-selector p-4 rounded-lg cursor-pointer text-center bg-gray-50" data-role="alumni">
                        <i class="fas fa-user-graduate text-3xl text-green-600 mb-2"></i>
                        <h3 class="font-bold text-gray-800">Alumni</h3>
                    </div>
                    <div class="role-selector admin-role-selector p-4 rounded-lg cursor-pointer text-center bg-gray-50" data-role="admin">
                        <i class="fas fa-user-shield text-3xl text-blue-600 mb-2"></i>
                        <h3 class="font-bold text-gray-800">Administrator</h3>
                    </div>
                </div>

                <form id="loginForm" method="POST" action="auth.php" class="space-y-6">
                    <input type="hidden" name="role" id="selectedRole" value="">

                    <div>
                        <label for="loginEmail" class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                        <input type="email" id="loginEmail" name="email" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all" placeholder="Enter your email" autocomplete="email">
                    </div>

                    <div>
                        <label for="loginPassword" class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                        <input type="password" id="loginPassword" name="password" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all" placeholder="Enter your password" autocomplete="current-password">
                        
                        <!-- ENHANCED ERROR MESSAGE CONTAINER -->
                        <?php if (isset($_SESSION['login_error'])): ?>
                            <div class="error-container">
                                <i class="fas fa-exclamation-triangle"></i>
                                <span><strong>Login failed:</strong> <?php echo htmlspecialchars($_SESSION['login_error']); ?></span>
                            </div>
                            <?php unset($_SESSION['login_error']); ?>
                        <?php endif; ?>
                    </div>

                    <button type="submit" id="loginButton" class="w-full py-3 font-medium bg-gray-300 text-gray-500 cursor-not-allowed" disabled>
                        Sign In
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script src="login.js"></script>
</body>
</html>