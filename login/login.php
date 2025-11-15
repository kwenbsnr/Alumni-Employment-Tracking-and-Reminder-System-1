<?php
session_start();
include("../connect.php");

// Static stats 
//$total_alumni = 2847;
//$emp_rate = 94;
//$years = 50; 

// Get last attempted email and role for pre-filling
$last_email = $_SESSION['last_attempt_email'] ?? '';
$last_role = $_SESSION['last_attempt_role'] ?? '';
$login_attempts = $_SESSION['login_attempts'] ?? 0;

// Clear the stored values after use to prevent permanent pre-filling
unset($_SESSION['last_attempt_email']);
unset($_SESSION['last_attempt_role']);
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
</head>

<body class="bg-gray-50 min-h-screen">
    <header class="fixed top-0 left-0 w-full z-50">
        <!-- Top Green Bar -->
        <div class="top-green-bar"></div>

        <!-- Top Links -->
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

    <!-- Login Page Content -->
    <div id="loginPage" class="login-container">
        <!-- Left Side - School Branding (Now uses the building image with black overlay) -->
         <div class="school-branding flex items-center justify-center p-8">
            <img src="images/jh-building.png" alt="JHCSC Building" class="absolute inset-0 w-full h-full object-cover opacity-30">
            <div class="text-center text-white z-10 p-4 max-w-lg">
                <!-- School Logo/Emblem - Using Icons as Placeholder -->
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

                <!-- School Stats (Static/Hardcoded) -->
               <!-- <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-12">
                    <div class="text-center">
                        <div class="text-3xl font-bold"><?php echo $total_alumni; ?></div>
                        <div class="text-sm opacity-80">Alumni</div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-bold"><?php echo $years; ?>+</div>
                        <div class="text-sm opacity-80">Years</div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-bold"><?php echo $emp_rate; ?>%</div>
                        <div class="text-sm opacity-80">Employment</div>
                    </div>
                </div> -->
                
                
                <!-- School Images Placeholder (Visual accents) -->
                 <div class="mt-12 grid grid-cols-2 gap-4">
                    <div class="h-24 bg-white bg-opacity-20 rounded-xl flex items-center justify-center shadow-inner">
                        <i class="fas fa-graduation-cap text-3xl"></i>
                    </div>
                    <div class="h-24 bg-white bg-opacity-20 rounded-xl flex items-center justify-center shadow-inner">
                        <i class="fas fa-building text-3xl"></i>
                    </div>
                </div>
            </div>
        </div>
                

        <!-- Right Side - Login Form -->
        <div class="login-right">
            <div class="login-box">
                <div class="text-center mb-8">
                    <h2 class="text-3xl font-bold text-gray-800 mb-2">Welcome Back</h2>
                    <p class="text-gray-600">Please select your role and sign in</p>
                </div>

                <!-- Role Selection -->
                 <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-8">
                    <div class="role-selector p-4 rounded-lg cursor-pointer text-center bg-gray-50 <?php echo ($last_role === 'alumni') ? 'selected' : ''; ?>" data-role="alumni">
                        <i class="fas fa-user-graduate text-3xl text-green-600 mb-2"></i>
                        <h3 class="font-bold text-gray-800">Alumni</h3>
                    </div>
                    
                    <div class="role-selector admin-role-selector p-4 rounded-lg cursor-pointer text-center bg-gray-50 <?php echo ($last_role === 'admin') ? 'selected' : ''; ?>" data-role="admin">
                        <i class="fas fa-user-shield text-3xl text-blue-600 mb-2"></i>
                        <h3 class="font-bold text-gray-800">Administrator</h3>
                    </div>
                </div>

                <?php if (isset($_SESSION['login_error'])): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 text-center login-error-message">
                        <div class="flex items-center justify-center">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            <?php echo htmlspecialchars($_SESSION['login_error']); ?>
                        </div>
                        <?php if (strpos($_SESSION['login_error'], 'Incorrect password') !== false): ?>
                            <div class="mt-2 text-sm text-red-600 flex items-center justify-center">
                                <i class="fas fa-lightbulb mr-1"></i>
                                <span>Please re-enter your password carefully</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php unset($_SESSION['login_error']); ?>
                <?php endif; ?>

                <form id="loginForm" method="POST" action="auth.php" class="space-y-6">
                    <input type="hidden" name="role" id="selectedRole" value="<?php echo htmlspecialchars($last_role); ?>">

                    <div>
                        <label for="loginEmail" class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                        <input type="email" id="loginEmail" name="email" required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all" 
                               placeholder="Enter your email" 
                               autocomplete="email"
                               value="<?php echo htmlspecialchars($last_email); ?>">
                        <div id="emailError" class="text-red-500 text-sm mt-1 hidden"></div>
                    </div>

                    <div class="relative">
                        <label for="loginPassword" class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                        <div class="password-field-container">
                            <input type="password" id="loginPassword" name="password" required 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all <?php echo ($login_attempts > 0) ? 'password-retry-field' : ''; ?>" 
                                   placeholder="Enter your password" 
                                   autocomplete="current-password">
                            <button type="button" id="togglePassword" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div id="passwordError" class="text-red-500 text-sm mt-1 hidden"></div>
                        <?php if ($login_attempts > 0): ?>
                            <div class="text-orange-600 text-sm mt-2 flex items-center">
                                <i class="fas fa-info-circle mr-1"></i>
                                <span>Previous login attempt failed. Please re-enter your password carefully.</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <button type="submit" id="loginButton" class="w-full py-3 font-medium" <?php echo ($last_role) ? '' : 'disabled'; ?>>
                        <?php if ($login_attempts > 0): ?>
                            <i class="fas fa-redo-alt mr-2"></i> Try Again
                        <?php else: ?>
                            Sign In
                        <?php endif; ?>
                    </button>

                    <!-- <p class="text-center text-sm pt-4">
                        Don't have an account?
                        <a href="#" class="font-medium text-green-600 hover:text-green-800 transition">Register Here</a>
                    </p> -->
                </form>
            </div>
        </div>
    </div>

    <script src="login.js"></script>
</body>
</html>