<?php
include 'koneksi.php';

$id = $_POST['id'] ?? null;
$filepath = $_POST['filepath'] ?? '';
$thumbpath = $_POST['thumbpath'] ?? '';

if ($id) {
  // Hapus file dari server
  if (file_exists($filepath)) unlink($filepath);
  if (file_exists($thumbpath)) unlink($thumbpath);

  // Hapus data dari database
  $id = (int)$id; // pastikan aman
  $conn->query("DELETE FROM gambar_thumbnail WHERE id = $id");
}

// Redirect back to the previous page
header("Location: " . $_SERVER['HTTP_REFERER']);
exit;
