<?php
require_once 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }

    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $mysqli->prepare("SELECT ua.id, ua.username, ua.password, ua.status, r.kode_role, r.nama_role FROM user_account ua JOIN role r ON ua.role_id = r.id WHERE ua.username=? LIMIT 1");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows === 1) {
        $stmt->bind_result($id, $user, $hash, $acc_status, $kode_role, $role_name);
        $stmt->fetch();
        // Block login when status is not Aktif
        if (strtolower(trim($acc_status)) !== 'aktif') {
            $error = 'Akun Anda tidak aktif. Hubungi administrator.';
        } else {
        // Untuk contoh, password di database admin = 'admin' (plain), user lain harus hash
        if ($password === $hash || verify_password($password, $hash)) {
            $_SESSION['user_id'] = $id;
            $_SESSION['username'] = $user;
            // Simpan slug/keyword role ke session (mis. 'admin','pimpinan','driver','user')
            $role_slug = strtolower(trim($kode_role));
            $role_slug = preg_replace('/[^a-z0-9_\-]+/', '_', $role_slug);
            $role_slug = trim($role_slug, '_');
            $_SESSION['role'] = $role_slug;
            // Simpan juga nama role sebagai label jika diperlukan di UI
            $_SESSION['role_name'] = $role_name;
            
            // Log aktivitas login
            log_activity("LOGIN", "User berhasil login ke sistem");
            
            // Redirect ke Beranda agar active sidebar adalah 'Beranda' pada login pertama
            header('Location: index.php');
            exit;
        } else {
            $error = 'Password salah!';
        }
        }
    } else {
        $error = 'Username tidak ditemukan!';
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SI-KENDI</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-image: url('assets/images/bg.png'), linear-gradient(135deg, #154a6b 0%, #5b8aa8 100%);
            background-size: cover, auto;
            background-position: center center, center;
            background-repeat: no-repeat, no-repeat;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        /* Background pattern */
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                radial-gradient(circle at 20% 80%, rgba(255, 215, 0, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255, 215, 0, 0.1) 0%, transparent 50%);
            pointer-events: none;
        }

        .login-container {
            width: 100%;
            max-width: 500px;
            padding: 2rem;
            position: relative;
            z-index: 1;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 3rem 2.5rem;
            box-shadow: 
                0 25px 50px rgba(0, 0, 0, 0.2),
                0 0 0 1px rgba(255, 255, 255, 0.1);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, #154a6b 0%, #5b8aa8 100%);
        }

        .login-header {
            margin-bottom: 2.5rem;
        }

        .logo-container {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .logo {
            width: 100px;
            height: 100px;
            margin: 0 auto;
            background: linear-gradient(135deg, #f0f6fa, #d7e6ee);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow:
                0 10px 30px rgba(21, 74, 107, 0.35),
                inset 0 0 0 3px rgba(91, 138, 168, 0.4);
            position: relative;
        }

        .logo-image {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #FFD700;
        }

        /* Fallback untuk logo jika file tidak ada */
        .logo-fallback {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #154a6b, #5b8aa8);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo-fallback i {
            font-size: 2.5rem;
            color: #ffffff;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.25);
        }

        .logo::after {
            content: '';
            position: absolute;
            inset: -5px;
            border-radius: 50%;
            background: linear-gradient(45deg, #154a6b, transparent, #5b8aa8);
            z-index: -1;
            animation: rotate 3.5s linear infinite;
        }

        @keyframes rotate {
            to { transform: rotate(360deg); }
        }

        .login-header h1 {
            margin: 0 0 0.5rem 0;
            font-size: 2.2rem;
            font-weight: 800;
            background: linear-gradient(135deg, #154a6b, #5b8aa8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: 1px;
        }

        .login-header .subtitle {
            color: #666;
            font-size: 1rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .login-header .description {
            color: #888;
            font-size: 0.85rem;
            line-height: 1.4;
        }

        .form-group {
            margin-bottom: 1.5rem;
            text-align: left;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
            font-size: 0.9rem;
        }

        .input-wrapper {
            position: relative;
        }

        .form-control {
            width: 100%;
            padding: 1rem 1rem 1rem 3.5rem;
            border: 2px solid #e9ecef;
            border-radius: 16px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
            box-sizing: border-box;
        }

        .form-control:focus {
            outline: none;
            border-color: #154a6b;
            box-shadow: 0 0 0 3px rgba(21, 74, 107, 0.15);
            transform: translateY(-1px);
        }

        .input-icon {
            position: absolute;
            left: 1.2rem;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            font-size: 1.1rem;
            transition: color 0.3s ease;
        }

        .form-control:focus + .input-icon,
        .input-wrapper:hover .input-icon {
            color: #154a6b;
        }

        .btn-login {
            width: 100%;
            padding: 1.2rem;
            background: linear-gradient(135deg, #154a6b, #5b8aa8);
            color: white;
            border: none;
            border-radius: 16px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 1rem;
            position: relative;
            overflow: hidden;
        }

        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.35), transparent);
            transition: left 0.55s;
        }

        .btn-login:hover::before {
            left: 100%;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 35px rgba(21, 74, 107, 0.45);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .error-message {
            background: linear-gradient(135deg, #ffebee, #ffcdd2);
            color: #c62828;
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            border: 1px solid #ffcdd2;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        .back-link {
            margin-top: 2rem;
            text-align: center;
        }

        .back-link a {
            color: #154a6b;
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            padding: 0.5rem 1rem;
            border-radius: 8px;
        }

        .back-link a:hover {
            background: rgba(21, 74, 107, 0.12);
            transform: translateX(-3px);
        }

        .institutional-badge {
            position: absolute;
            top: -10px;
            right: -10px;
            background: linear-gradient(135deg, #154a6b, #5b8aa8);
            color: #ffffff;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 12px rgba(21, 74, 107, 0.35);
        }

        @media (max-width: 768px) {
            .login-container {
                padding: 1rem;
            }

            .login-card {
                padding: 2.5rem 2rem;
            }

            .login-header h1 {
                font-size: 1.8rem;
            }

            .logo {
                width: 80px;
                height: 80px;
            }

            .logo-image {
                width: 55px;
                height: 55px;
            }
        }

        /* Brand Gradient Variables */
        :root {
            --brand-start: #154a6b;
            --brand-end: #5b8aa8;
            --brand-mid: #3e6f8a;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="logo-container">
                    <div class="logo">
                        <!-- Logo TNI -->
                        <img src="assets/images/logo.png" alt="Logo TNI" class="logo-image" 
                             onerror="this.src='assets/images/logo.svg'; this.onerror=null;">
                        <!-- Fallback jika logo tidak ditemukan -->
                        <div class="logo-fallback" style="display: none;">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                    </div>
                </div>
                <h1>SI-KENDI</h1>
                <p class="subtitle">Sistem Informasi Kendaraan Dinas</p>
                <p class="description">Platform Digital Kendaraan Dinas TNI/PNS</p>
            </div>

            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token(); ?>">
                
                <div class="form-group">
                    <label for="username">Username</label>
                    <div class="input-wrapper">
                        <input type="text" id="username" name="username" class="form-control" 
                               placeholder="Masukkan username Anda" required autocomplete="username">
                        <i class="fas fa-user input-icon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrapper">
                        <input type="password" id="password" name="password" class="form-control" 
                               placeholder="Masukkan password Anda" required autocomplete="current-password">
                        <i class="fas fa-lock input-icon"></i>
                    </div>
                </div>

                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i>
                    Masuk Sistem
                </button>
            </form>

            <div class="back-link">
                <a href="index.php">
                    <i class="fas fa-arrow-left"></i>
                    Kembali ke Beranda
                </a>
            </div>
        </div>
    </div>

    <script>
        // Enhanced form interactions
        document.addEventListener('DOMContentLoaded', function() {
            const formInputs = document.querySelectorAll('.form-control');
            
            formInputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.classList.add('focused');
                });
                
                input.addEventListener('blur', function() {
                    if (!this.value) {
                        this.parentElement.classList.remove('focused');
                    }
                });

                // Add validation feedback
                input.addEventListener('input', function() {
                    if (this.validity.valid) {
                        this.style.borderColor = '#28a745';
                    } else {
                        this.style.borderColor = '#dc3545';
                    }
                });
            });

            // Form submission with loading state
            const form = document.querySelector('form');
            const submitBtn = document.querySelector('.btn-login');
            
            form.addEventListener('submit', function() {
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
                submitBtn.disabled = true;
            });
        });
    </script>
</body>
</html>
