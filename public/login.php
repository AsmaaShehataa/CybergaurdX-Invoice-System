<?php
// index.php - Login page
require_once dirname(__DIR__) . '/includes/init.php';
startAppSession();

// Check if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

// Include config for database connection
require_once dirname(__DIR__) . '/includes/config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = "Please enter username and password";
    } else {
        $stmt = $conn->prepare("SELECT id, username, password, full_name, role FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password'])) {
                // Login successful
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['login_time'] = time();
                
                // Redirect to dashboard
                header('Location: dashboard.php');
                exit();
            } else {
                $error = "Invalid username or password";
            }
        } else {
            $error = "Invalid username or password";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - CyberGuard Integrated Solutions</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif; 
        }
        
        body { 
            background: linear-gradient(135deg, #0b1120 0%, #0f172a 100%); 
            min-height: 100vh; 
            display: flex; 
            flex-direction: column;
            padding: 0;
            position: relative;
            overflow-x: hidden;
        }
        
        /* Top Header with Logo */
        .header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            padding: 20px 40px;
            z-index: 100;
            background: rgba(15, 23, 42, 0.9);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .logo-container {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .logo-img {
            width: 60px;
            height: 60px;
            object-fit: contain;
            filter: brightness(0) invert(1);
            transition: transform 0.3s ease;
        }
        
        .logo-img:hover {
            transform: scale(1.05);
        }
        
        .logo-text {
            display: flex;
            flex-direction: column;
        }
        
        .company-name {
            font-size: 24px;
            font-weight: 900;
            color: white;
            letter-spacing: -0.5px;
            line-height: 1;
            margin-bottom: 4px;
            text-transform: uppercase;
        }
        
        .company-tagline {
            font-size: 13px;
            color: #a5b4fc;
            font-weight: 500;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        
        .header-right {
            color: #94a3b8;
            font-size: 14px;
            font-weight: 500;
            padding: 8px 16px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        /* Main Content Layout */
        .main-content {
            display: flex;
            min-height: 100vh;
            padding-top: 100px;
            position: relative;
            z-index: 1;
        }
        
        /* Left Hero Section */
        .hero-section {
            flex: 1;
            padding: 80px 60px;
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
        }
        
        .hero-bg {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 30% 40%, rgba(99, 102, 241, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 70% 60%, rgba(139, 92, 246, 0.08) 0%, transparent 50%);
            z-index: -1;
        }
        
        .hero-content {
            max-width: 600px;
        }
        
        .hero-title {
            font-size: 56px;
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 20px;
            background: linear-gradient(135deg, #ffffff 0%, #93c5fd 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .hero-title span {
            color: #60a5fa;
            -webkit-text-fill-color: #60a5fa;
            display: block;
            margin-top: 10px;
        }
        
        .hero-subtitle {
            font-size: 20px;
            color: #cbd5e1;
            margin-bottom: 50px;
            font-weight: 400;
            line-height: 1.6;
            max-width: 500px;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
            margin-top: 40px;
        }
        
        .feature-item {
            padding: 25px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 18px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }
        
        .feature-item:hover {
            transform: translateY(-5px);
            background: rgba(99, 102, 241, 0.15);
            border-color: rgba(99, 102, 241, 0.3);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.3);
        }
        
        .feature-icon {
            font-size: 28px;
            margin-bottom: 20px;
            color: #a5b4fc;
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .feature-title {
            font-size: 18px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 10px;
        }
        
        .feature-desc {
            font-size: 14px;
            color: #94a3b8;
            line-height: 1.6;
        }
        
        /* Login Container */
        .login-section {
            width: 480px;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            padding: 60px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            border-left: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: -20px 0 60px rgba(0, 0, 0, 0.3);
        }
        
        .login-container {
            width: 100%;
            max-width: 380px;
            margin: 0 auto;
        }
        
        .login-title {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .login-title h2 {
            font-size: 32px;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 12px;
        }
        
        .login-title p {
            color: #64748b;
            font-size: 16px;
            font-weight: 500;
        }
        
        .form-group { 
            margin-bottom: 28px; 
            position: relative;
        }
        
        .form-label {
            display: block;
            margin-bottom: 10px;
            color: #475569;
            font-weight: 600;
            font-size: 13px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        
        .input-container {
            position: relative;
        }
        
        .input-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            z-index: 1;
            font-size: 20px;
        }
        
        .form-input { 
            width: 100%; 
            padding: 18px 18px 18px 55px; 
            border: 2px solid #e2e8f0; 
            border-radius: 16px; 
            font-size: 15px; 
            background: #ffffff;
            transition: all 0.3s ease;
            font-weight: 500;
            color: #1e293b;
        }
        
        .form-input:focus { 
            outline: none; 
            border-color: #6366f1; 
            background: white;
            box-shadow: 
                0 0 0 4px rgba(99, 102, 241, 0.1),
                0 10px 30px rgba(99, 102, 241, 0.15);
            transform: translateY(-2px);
        }
        
        .login-btn { 
            width: 100%; 
            padding: 20px; 
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); 
            color: white; 
            border: none; 
            border-radius: 16px; 
            font-size: 16px; 
            font-weight: 700; 
            cursor: pointer; 
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            margin-top: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            letter-spacing: 0.5px;
        }
        
        .login-btn:hover { 
            transform: translateY(-3px); 
            box-shadow: 0 20px 40px rgba(79, 70, 229, 0.4);
        }
        
        .login-btn:active { 
            transform: translateY(-1px); 
        }
        
        .error-message { 
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #dc2626; 
            padding: 18px; 
            border-radius: 16px; 
            margin-bottom: 28px; 
            border-left: 4px solid #dc2626;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
        }
        
        .error-message::before {
            content: "⚠️";
            font-size: 20px;
        }
        
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 25px;
            padding-top: 25px;
            border-top: 1px solid #f1f5f9;
        }
        
        .remember-container {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #64748b;
            font-size: 14px;
        }
        
        .remember-container input[type="checkbox"] {
            width: 20px;
            height: 20px;
            accent-color: #6366f1;
        }
        
        .forgot-link {
            color: #6366f1;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: color 0.3s ease;
        }
        
        .forgot-link:hover {
            color: #4f46e5;
            text-decoration: underline;
        }
        
        .security-footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 40px;
            border-top: 1px solid #f1f5f9;
        }
        
        .security-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 14px 28px;
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border-radius: 50px;
            color: #0369a1;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 20px;
            border: 1px solid #bae6fd;
        }
        
        .copyright {
            text-align: center;
            color: #94a3b8;
            font-size: 12px;
            line-height: 1.5;
        }
        
        /* Background Elements */
        .bg-pattern {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            opacity: 0.05;
            z-index: 0;
            background: 
                radial-gradient(circle at 20% 30%, rgba(99, 102, 241, 0.4) 0%, transparent 50%),
                radial-gradient(circle at 80% 70%, rgba(139, 92, 246, 0.3) 0%, transparent 50%);
        }
        
        /* Responsive Design */
        @media (max-width: 1200px) {
            .hero-title {
                font-size: 48px;
            }
            
            .login-section {
                width: 440px;
            }
        }
        
        @media (max-width: 1024px) {
            .main-content {
                flex-direction: column;
                padding-top: 120px;
            }
            
            .hero-section {
                padding: 40px 30px;
                text-align: center;
            }
            
            .hero-content {
                max-width: 800px;
                margin: 0 auto;
            }
            
            .login-section {
                width: 100%;
                padding: 60px 30px;
                border-left: none;
                border-top: 1px solid rgba(255, 255, 255, 0.2);
                box-shadow: 0 -20px 60px rgba(0, 0, 0, 0.3);
            }
            
            .login-container {
                max-width: 400px;
            }
            
            .features-grid {
                max-width: 600px;
                margin: 40px auto 0;
            }
        }
        
        @media (max-width: 768px) {
            .header {
                padding: 15px 25px;
            }
            
            .logo-img {
                width: 50px;
                height: 50px;
            }
            
            .company-name {
                font-size: 20px;
            }
            
            .company-tagline {
                font-size: 12px;
            }
            
            .hero-title {
                font-size: 36px;
            }
            
            .hero-subtitle {
                font-size: 18px;
            }
            
            .features-grid {
                grid-template-columns: 1fr;
                max-width: 400px;
                gap: 20px;
            }
            
            .feature-item {
                padding: 20px;
            }
            
            .login-section {
                padding: 50px 25px;
            }
        }
        
        @media (max-width: 480px) {
            .header {
                padding: 12px 20px;
            }
            
            .logo-img {
                width: 40px;
                height: 40px;
            }
            
            .logo-container {
                gap: 15px;
            }
            
            .company-name {
                font-size: 18px;
            }
            
            .company-tagline {
                font-size: 11px;
            }
            
            .header-right {
                font-size: 12px;
                padding: 6px 12px;
            }
            
            .hero-title {
                font-size: 32px;
            }
            
            .hero-subtitle {
                font-size: 16px;
            }
            
            .hero-section {
                padding: 30px 20px;
            }
            
            .login-section {
                padding: 40px 20px;
            }
            
            .login-title h2 {
                font-size: 28px;
            }
            
            .form-options {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="bg-pattern"></div>
    
    <!-- Fixed Header with Logo -->
    <header class="header">
        <div class="logo-container">
            <!-- Actual logo image -->
            <img src="assets/images/logo-alt.png" alt="CyberGuard Logo" class="logo-img">
            <div class="logo-text">
                <div class="company-name">CYBERGUARD</div>
                <div class="company-tagline">Integrated Solutions LLC</div>
            </div>
        </div>
        <div class="header-right">
            Invoice Management System
        </div>
    </header>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Left Hero Section -->
        <section class="hero-section">
            <div class="hero-bg"></div>
            <div class="hero-content">
                <h1 class="hero-title">
                    Secure Access<br>to Your<br><span>Invoice System</span>
                </h1>
                
                <p class="hero-subtitle">
                    Enterprise-grade invoice management with military-level security and real-time analytics.
                </p>
                
                <div class="features-grid">
                    <div class="feature-item">
                        <div class="feature-icon">🛡️</div>
                        <h3 class="feature-title">Military-grade Encryption</h3>
                        <p class="feature-desc">Bank-level security for all your data and transactions</p>
                    </div>
                    
                    <div class="feature-item">
                        <div class="feature-icon">📊</div>
                        <h3 class="feature-title">Real-time Analytics</h3>
                        <p class="feature-desc">Live insights, reporting, and financial tracking</p>
                    </div>
                    
                    <div class="feature-item">
                        <div class="feature-icon">🔐</div>
                        <h3 class="feature-title">Multi-factor Auth</h3>
                        <p class="feature-desc">Enhanced account protection with multiple layers</p>
                    </div>
                    
                    <div class="feature-item">
                        <div class="feature-icon">⚡</div>
                        <h3 class="feature-title">High Performance</h3>
                        <p class="feature-desc">Lightning fast processing and seamless experience</p>
                    </div>
                </div>
            </div>
        </section>
        
        <!-- Right Login Section -->
        <section class="login-section">
            <div class="login-container">
                <div class="login-title">
                    <h2>Welcome Back</h2>
                    <p>Sign in to continue to dashboard</p>
                </div>
                
                <?php if ($error): ?>
                    <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <form method="POST" action="" id="loginForm">
                    <div class="form-group">
                        <label class="form-label" for="username">Username</label>
                        <div class="input-container">
                            <span class="input-icon">👤</span>
                            <input type="text" id="username" name="username" class="form-input" required placeholder="Enter your username" value="">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="password">Password</label>
                        <div class="input-container">
                            <span class="input-icon">🔒</span>
                            <input type="password" id="password" name="password" class="form-input" required placeholder="Enter your password" value="Enter your password">
                        </div>
                    </div>
                    
                    <div class="form-options">
                        <div class="remember-container">
                            <input type="checkbox" id="remember" name="remember">
                            <label for="remember">Remember me</label>
                        </div>
                        
                        <a href="#forgot" class="forgot-link">Forgot Password?</a>
                    </div>
                    
                    <button type="submit" class="login-btn">
                        <span>🔐</span>
                        <span>Sign In to Dashboard</span>
                    </button>
                </form>
                
                <div class="security-footer">
                    <div class="security-badge">
                        <span>🛡️</span>
                        <span>Protected by CyberGuard Security</span>
                    </div>
                    
                    <div class="copyright">
                        © 2024 CyberGuard Integrated Solutions LLC.<br>
                        All rights reserved. v2.1.0
                    </div>
                </div>
            </div>
        </section>
    </div>

    <script>
        // Add animation on page load
        document.addEventListener('DOMContentLoaded', function() {
            const heroContent = document.querySelector('.hero-content');
            const loginContainer = document.querySelector('.login-container');
            const header = document.querySelector('.header');
            
            // Initial hidden state
            heroContent.style.opacity = '0';
            heroContent.style.transform = 'translateY(30px)';
            loginContainer.style.opacity = '0';
            loginContainer.style.transform = 'translateY(30px)';
            header.style.transform = 'translateY(-100%)';
            
            // Animate header slide in
            setTimeout(() => {
                header.style.transition = 'transform 0.6s ease';
                header.style.transform = 'translateY(0)';
            }, 100);
            
            // Animate content with delay
            setTimeout(() => {
                heroContent.style.transition = 'opacity 0.8s ease, transform 0.8s cubic-bezier(0.34, 1.56, 0.64, 1)';
                loginContainer.style.transition = 'opacity 0.8s ease 0.2s, transform 0.8s cubic-bezier(0.34, 1.56, 0.64, 1) 0.2s';
                
                heroContent.style.opacity = '1';
                heroContent.style.transform = 'translateY(0)';
                loginContainer.style.opacity = '1';
                loginContainer.style.transform = 'translateY(0)';
            }, 400);
            
            // Input focus effects
            const inputs = document.querySelectorAll('.form-input');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    const icon = this.parentElement.querySelector('.input-icon');
                    icon.style.color = '#6366f1';
                    icon.style.transform = 'translateY(-50%) scale(1.2)';
                });
                
                input.addEventListener('blur', function() {
                    const icon = this.parentElement.querySelector('.input-icon');
                    icon.style.color = '#94a3b8';
                    icon.style.transform = 'translateY(-50%) scale(1)';
                });
            });
            
            // Checkbox styling
            const checkbox = document.querySelector('input[type="checkbox"]');
            if (checkbox) {
                checkbox.addEventListener('change', function() {
                    this.style.accentColor = this.checked ? '#6366f1' : '';
                });
            }
            
            // Form submission
            const form = document.getElementById('loginForm');
            form.addEventListener('submit', function(e) {
                const btn = this.querySelector('.login-btn');
                const originalHTML = btn.innerHTML;
                
                // Disable button and show loading
                btn.innerHTML = '<span>⏳</span><span>Authenticating...</span>';
                btn.disabled = true;
                btn.style.opacity = '0.8';
                btn.style.cursor = 'not-allowed';
                btn.style.transform = 'translateY(0)';
                
                // Revert after timeout (for demo purposes)
                setTimeout(() => {
                    if (btn.disabled) {
                        btn.innerHTML = originalHTML;
                        btn.disabled = false;
                        btn.style.opacity = '1';
                        btn.style.cursor = 'pointer';
                    }
                }, 3000);
            });
            
            // Feature card hover effects
            const featureItems = document.querySelectorAll('.feature-item');
            featureItems.forEach(item => {
                item.addEventListener('mouseenter', function() {
                    const icon = this.querySelector('.feature-icon');
                    icon.style.transform = 'scale(1.1) rotate(5deg)';
                    icon.style.transition = 'transform 0.3s ease';
                });
                
                item.addEventListener('mouseleave', function() {
                    const icon = this.querySelector('.feature-icon');
                    icon.style.transform = 'scale(1) rotate(0)';
                });
            });
            
            // Handle logo image loading
            const logoImg = document.querySelector('.logo-img');
            if (logoImg) {
                logoImg.onerror = function() {
                    console.log('Logo failed to load, using fallback');
                    // Create fallback SVG logo
                    this.src = 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200"><rect width="200" height="200" fill="%236366f1" rx="20"/><text x="100" y="110" font-family="Inter, sans-serif" font-size="60" fill="white" text-anchor="middle" font-weight="bold">CG</text></svg>';
                    this.style.filter = 'none';
                };
                
                logoImg.onload = function() {
                    console.log('Logo loaded successfully');
                };
            }
        });
    </script>
</body>
</html>