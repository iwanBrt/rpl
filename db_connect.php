<?php
// Konfigurasi database
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'proyek_mini_db';

// Membuat koneksi
$conn = new mysqli($host, $username, $password, $database);

// Cek koneksi
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Set karakter encoding
$conn->set_charset("utf8");
?> 