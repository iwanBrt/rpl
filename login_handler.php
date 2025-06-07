<?php
session_start();
require_once 'db_connect.php';

// Terima data JSON dari request
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Data tidak valid']);
    exit;
}

$username = trim($data['username']);
$password = trim($data['password']);

if (empty($username) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Username dan password harus diisi']);
    exit;
}

// Cek user di database
$stmt = $conn->prepare("SELECT id, username, password, full_name, role FROM users WHERE username = ? OR email = ?");
$stmt->bind_param("ss", $username, $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 1) {
    $user = $result->fetch_assoc();
    if (password_verify($password, $user['password'])) {
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        
        echo json_encode([
            'success' => true,
            'message' => 'Login berhasil!'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Password salah!'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Username atau email tidak ditemukan!'
    ]);
}

$stmt->close();
?> 