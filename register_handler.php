<?php
require_once 'db_connect.php';

// Terima data JSON dari request
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Data tidak valid']);
    exit;
}

$username = trim($data['username']);
$email = trim($data['email']);
$password = trim($data['password']);

// Validasi input
if (empty($username) || empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Semua field harus diisi']);
    exit;
}

if (strlen($password) < 6) {
    echo json_encode(['success' => false, 'message' => 'Password minimal 6 karakter']);
    exit;
}

// Cek username dan email sudah ada atau belum
$stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
$stmt->bind_param("ss", $username, $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Username atau email sudah terdaftar']);
    exit;
}

// Hash password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Insert user baru
$stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name, role) VALUES (?, ?, ?, ?, 'member')");
$full_name = $username; // Gunakan username sebagai full_name default
$stmt->bind_param("ssss", $username, $email, $hashed_password, $full_name);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Registrasi berhasil! Silakan login.'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Gagal melakukan registrasi: ' . $conn->error
    ]);
}

$stmt->close();
?> 