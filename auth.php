<?php
// auth.php

// Memulai sesi di paling atas, ini penting untuk manajemen login
session_start(); 

// Menyertakan file konfigurasi dan koneksi database
// Pastikan file 'db_connect.php' ada di direktori yang sama atau sesuaikan path-nya.
require_once 'db_connect.php'; 

// Logika untuk mengarahkan pengguna jika sudah login:
// Jika variabel sesi 'user_id' sudah ada, berarti pengguna sudah login.
// Maka, arahkan (redirect) pengguna ke halaman aplikasi utama (misalnya, app.php).
if (isset($_SESSION['user_id'])) {
    header('Location: app.php'); // Ganti 'app.php' dengan nama file aplikasi utama Anda
    exit; // Hentikan eksekusi skrip lebih lanjut setelah redirect
}

// Di sini bisa ditambahkan logika PHP lain jika diperlukan sebelum HTML dirender,
// misalnya untuk menampilkan pesan error global atau notifikasi.
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login / Registrasi - Sistem Manajemen Proyek Mini</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #111827; /* gray-900 */
            color: #d1d5db; /* gray-300 */
        }
        .auth-container-gradient {
            background: linear-gradient(160deg, #1f2937 0%, #111827 70%); /* gray-800 ke gray-900 */
        }
        .navbar-auth {
            background-color: rgba(31, 41, 55, 0.7); /* gray-800 dengan transparansi */
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
        }
        .form-input {
            background-color: #2d3748; 
            border: 1px solid #4a5568; 
            color: #e2e8f0; 
            border-radius: 0.375rem; 
            padding: 0.75rem 1rem;
            transition: border-color 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }
        .form-input:focus {
            outline: none;
            border-color: #8b5cf6; /* violet-500 */
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.3); 
        }
        .btn-auth-primary {
            background-color: #7c3aed; /* purple-600 */
            transition: background-color 0.3s ease;
        }
        .btn-auth-primary:hover {
            background-color: #6d28d9; /* purple-700 */
        }
        .tab-button {
            transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
        }
        .tab-button.active {
            background-color: #7c3aed; /* purple-600 */
            color: white;
            border-color: #7c3aed;
        }
        .tab-button:not(.active) {
            background-color: transparent;
            color: #9ca3af; /* gray-400 */
            border-color: #4b5563; /* gray-600 */
        }
        .tab-button:not(.active):hover {
            background-color: #374151; /* gray-700 */
            color: #d1d5db; /* gray-300 */
        }
    </style>
</head>
<body class="antialiased">

    <nav class="navbar-auth fixed w-full z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-20">
                <div class="flex items-center">
                    <a href="landing.html" class="flex-shrink-0 text-white text-3xl font-extrabold tracking-tight"> Proyek<span class="text-purple-400">Mini</span> AI
                    </a>
                </div>
                <div>
                    <a href="landing.html" class="text-gray-300 hover:text-purple-400 px-3 py-2 rounded-md text-sm font-medium transition-colors duration-200">
                        <i class="fas fa-arrow-left mr-2"></i>Kembali ke Beranda
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main class="min-h-screen flex items-center justify-center pt-20 auth-container-gradient">
        <div class="bg-gray-800 p-8 md:p-10 rounded-xl shadow-2xl w-full max-w-lg border border-purple-700 m-4">
            <div class="mb-8 flex justify-center border-b border-gray-700">
                <button id="loginTabButton" class="tab-button active flex-1 py-3 px-4 text-center font-semibold text-sm rounded-t-md border-b-2">
                    Login
                </button>
                <button id="registerTabButton" class="tab-button flex-1 py-3 px-4 text-center font-semibold text-sm rounded-t-md border-b-2">
                    Registrasi
                </button>
            </div>

            <div id="loginFormContainer">
                <h2 class="text-2xl sm:text-3xl font-bold text-purple-400 text-center mb-6">Selamat Datang Kembali!</h2>
                <form id="loginForm" class="space-y-6">
                    <div>
                        <label for="login_username" class="block text-sm font-medium text-gray-400 mb-1">Username atau Email</label>
                        <input type="text" name="login_username" id="login_username" required class="form-input block w-full">
                    </div>
                    <div>
                        <label for="login_password" class="block text-sm font-medium text-gray-400 mb-1">Password</label>
                        <input type="password" name="login_password" id="login_password" required class="form-input block w-full">
                    </div>
                    <button type="submit" class="w-full btn-auth-primary text-white font-semibold py-3 px-4 rounded-md shadow-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-gray-800 focus:ring-purple-500">
                        Login <i class="fas fa-sign-in-alt ml-2"></i>
                    </button>
                </form>
                <p id="loginMessage" class="mt-4 text-center text-sm"></p>
            </div>

            <div id="registerFormContainer" class="hidden">
                <h2 class="text-2xl sm:text-3xl font-bold text-purple-400 text-center mb-6">Buat Akun Baru</h2>
                <form id="registerForm" class="space-y-6">
                    <div>
                        <label for="reg_username" class="block text-sm font-medium text-gray-400 mb-1">Username</label>
                        <input type="text" name="reg_username" id="reg_username" required class="form-input block w-full">
                    </div>
                    <div>
                        <label for="reg_email" class="block text-sm font-medium text-gray-400 mb-1">Email</label>
                        <input type="email" name="reg_email" id="reg_email" required class="form-input block w-full">
                    </div>
                    <div>
                        <label for="reg_password" class="block text-sm font-medium text-gray-400 mb-1">Password</label>
                        <input type="password" name="reg_password" id="reg_password" required class="form-input block w-full">
                    </div>
                     <div>
                        <label for="reg_confirm_password" class="block text-sm font-medium text-gray-400 mb-1">Konfirmasi Password</label>
                        <input type="password" name="reg_confirm_password" id="reg_confirm_password" required class="form-input block w-full">
                    </div>
                    <button type="submit" class="w-full btn-auth-primary text-white font-semibold py-3 px-4 rounded-md shadow-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-gray-800 focus:ring-purple-500">
                        Daftar <i class="fas fa-user-plus ml-2"></i>
                    </button>
                </form>
                <p id="registerMessage" class="mt-4 text-center text-sm"></p>
            </div>
        </div>
    </main>

    <script>
        const loginTabButton = document.getElementById('loginTabButton');
        const registerTabButton = document.getElementById('registerTabButton');
        const loginFormContainer = document.getElementById('loginFormContainer');
        const registerFormContainer = document.getElementById('registerFormContainer');
        const loginForm = document.getElementById('loginForm');
        const registerForm = document.getElementById('registerForm');
        const loginMessage = document.getElementById('loginMessage');
        const registerMessage = document.getElementById('registerMessage');

        function switchToLogin() {
            loginTabButton.classList.add('active');
            registerTabButton.classList.remove('active');
            loginFormContainer.classList.remove('hidden');
            registerFormContainer.classList.add('hidden');
            loginMessage.textContent = '';
            registerMessage.textContent = '';
        }

        function switchToRegister() {
            registerTabButton.classList.add('active');
            loginTabButton.classList.remove('active');
            registerFormContainer.classList.remove('hidden');
            loginFormContainer.classList.add('hidden');
            loginMessage.textContent = '';
            registerMessage.textContent = '';
        }

        loginTabButton.addEventListener('click', switchToLogin);
        registerTabButton.addEventListener('click', switchToRegister);

        // Handle Login Form Submission
        loginForm.addEventListener('submit', async function(event) {
            event.preventDefault();
            const username = event.target.login_username.value;
            const password = event.target.login_password.value;
            loginMessage.textContent = 'Memproses...'; 
            loginMessage.style.color = '#9ca3af'; 

            try {
                // Ganti 'login_handler.php' dengan path yang benar jika berbeda
                const response = await fetch('login_handler.php', { 
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username, password })
                });
                const result = await response.json();

                if (result.success) {
                    loginMessage.style.color = '#34d399'; 
                    loginMessage.textContent = result.message + ' Mengalihkan...';
                    setTimeout(() => { window.location.href = 'app.php'; }, 1500); 
                } else {
                    loginMessage.style.color = '#f87171'; 
                    loginMessage.textContent = result.message || 'Login gagal. Periksa kembali kredensial Anda.';
                }
            } catch (error) {
                loginMessage.style.color = '#f87171'; 
                loginMessage.textContent = 'Terjadi kesalahan koneksi. Coba lagi nanti.';
                console.error('Login error:', error);
            }
        });

        // Handle Register Form Submission
        registerForm.addEventListener('submit', async function(event) {
            event.preventDefault();
            const username = event.target.reg_username.value;
            const email = event.target.reg_email.value;
            const password = event.target.reg_password.value;
            const confirmPassword = event.target.reg_confirm_password.value;
            registerMessage.textContent = 'Memproses...'; 
            registerMessage.style.color = '#9ca3af'; 

            if (password !== confirmPassword) {
                registerMessage.style.color = '#f87171'; 
                registerMessage.textContent = 'Password dan konfirmasi password tidak cocok.';
                return;
            }
            if (password.length < 6) { 
                registerMessage.style.color = '#f87171'; 
                registerMessage.textContent = 'Password minimal harus 6 karakter.';
                return;
            }

            try {
                // Ganti 'register_handler.php' dengan path yang benar jika berbeda
                const response = await fetch('register_handler.php', { 
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username, email, password })
                });
                const result = await response.json();

                if (result.success) {
                    registerMessage.style.color = '#34d399'; 
                    registerMessage.textContent = result.message + ' Silakan login.';
                    event.target.reset(); 
                    setTimeout(switchToLogin, 2000);
                } else {
                    registerMessage.style.color = '#f87171'; 
                    registerMessage.textContent = result.message || 'Registrasi gagal. Coba lagi.';
                }
            } catch (error) {
                registerMessage.style.color = '#f87171'; 
                registerMessage.textContent = 'Terjadi kesalahan koneksi. Coba lagi nanti.';
                console.error('Registration error:', error);
            }
        });

        // Set default tab to login
        switchToLogin();
    </script>
</body>
</html>
