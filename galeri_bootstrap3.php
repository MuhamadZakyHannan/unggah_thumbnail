<?php include 'koneksi.php'; ?>

<?php
// Proses upload gambar
if (isset($_POST['submit']) && isset($_FILES['gambar'])) {
  $file = $_FILES['gambar'];
  $filename = $file['name'];
  $tempname = $file['tmp_name'];

  $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
  $max_size = 1 * 1024 * 1024; // 1MB

  $mime = mime_content_type($tempname);
  $filesize = filesize($tempname);

  // Validasi tipe dan ukuran file
  if (!in_array($mime, $allowed_types)) {
    die("Hanya file JPG, JPEG, dan PNG yang diperbolehkan.");
  }

  if ($filesize > $max_size) {
    die("Ukuran file maksimal 1MB.");
  }

  // Ubah nama file menjadi id_pengguna_timestamp.jpg
  $user_id = 123;  // Sesuaikan dengan ID pengguna yang relevan
  $new_filename = $user_id . "_" . time() . ".jpg";

  $folder = "profile_pics/" . $new_filename;
  $thumb = "thumbnails/" . uniqid() . "_thumb_" . basename($new_filename);

  // Membuat folder thumbnails jika belum ada
  if (!is_dir('thumbnails')) {
    mkdir('thumbnails', 0777, true);
  }

  // Fungsi resize gambar
  function resizeImage($source, $destination, $new_width = 300)
  {
    list($width, $height) = getimagesize($source);
    $ratio = $height / $width;
    $new_height = $new_width * $ratio;

    $image_ext = pathinfo($source, PATHINFO_EXTENSION);
    if ($image_ext == 'jpg' || $image_ext == 'jpeg') {
      $src_img = imagecreatefromjpeg($source);
    } elseif ($image_ext == 'png') {
      $src_img = imagecreatefrompng($source);
    } else {
      return false;
    }

    $dst_img = imagecreatetruecolor($new_width, $new_height);
    imagecopyresampled($dst_img, $src_img, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

    if ($image_ext == 'jpg' || $image_ext == 'jpeg') {
      imagejpeg($dst_img, $destination);
    } elseif ($image_ext == 'png') {
      imagepng($dst_img, $destination);
    }
    imagedestroy($src_img);
    imagedestroy($dst_img);
  }

  // Pindahkan file gambar ke folder yang sesuai
  if (move_uploaded_file($tempname, $folder)) {
    // Resize gambar untuk thumbnail
    resizeImage($folder, $thumb, 150); // Resize thumbnail menjadi 150px lebar

    // Mendapatkan ukuran gambar
    list($width, $height) = getimagesize($folder);

    // Menyimpan data ke database
    $stmt = $conn->prepare("INSERT INTO gambar_thumbnail (filepath, thumbpath, width, height, uploaded_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssii", $folder, $thumb, $width, $height);
    $stmt->execute();
  }
}

$tanggal = $_GET['tanggal'] ?? null;

$limit = 6;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$count_sql = "SELECT COUNT(*) FROM gambar_thumbnail" . ($tanggal ? " WHERE DATE(uploaded_at) = '$tanggal'" : "");
$total_result = $conn->query($count_sql)->fetch_row()[0];
$total_pages = ceil($total_result / $limit);

$sql = "SELECT * FROM gambar_thumbnail" . ($tanggal ? " WHERE DATE(uploaded_at) = '$tanggal'" : "") . " ORDER BY uploaded_at DESC LIMIT $limit OFFSET $offset";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Galeri Responsive</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .thumb {
      max-width: 100%;
      height: auto;
      cursor: pointer;
    }

    .image-card {
      margin-bottom: 20px;
    }
  </style>
</head>

<body>
  <div class="container py-5">
    <h2 class="text-center mb-4">Galeri Gambar</h2>

    <!-- Form Upload -->
    <form method="POST" enctype="multipart/form-data" class="row mb-4 g-3">
      <div class="col-md-6">
        <input type="file" name="gambar" class="form-control" required>
      </div>
      <div class="col-md-auto">
        <button type="submit" name="submit" class="btn btn-primary">Upload</button>
      </div>
    </form>

    <!-- Filter -->
    <form method="GET" class="row g-3 mb-4">
      <div class="col-md-4">
        <input type="date" name="tanggal" class="form-control" value="<?= htmlspecialchars($tanggal) ?>">
      </div>
      <div class="col-md-auto">
        <button type="submit" class="btn btn-success">Filter</button>
        <a href="galeri_bootstrap3.php" class="btn btn-secondary">Reset</a>
      </div>
    </form>

    <?php if ($tanggal): ?>
      <p class="text-muted">Menampilkan gambar tanggal: <strong><?= htmlspecialchars($tanggal) ?></strong></p>
    <?php endif; ?>

    <div class="row">
      <?php if ($result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()): ?>
          <div class="col-md-4 image-card">
            <div class="card h-100">
              <img src="<?= $row['thumbpath'] ?>" class="card-img-top thumb" data-bs-toggle="modal" data-bs-target="#modal<?= $row['id'] ?>">
              <div class="card-body">
                <p class="card-text">Ukuran: <?= $row['width'] ?>x<?= $row['height'] ?></p>
              </div>
              <div class="card-footer d-flex justify-content-between">
                <a href="<?= $row['filepath'] ?>" class="btn btn-sm btn-primary" target="_blank">Lihat Asli</a>
                <form action="hapus.php" method="POST" onsubmit="return confirm('Yakin ingin menghapus gambar ini?')">
                  <input type="hidden" name="id" value="<?= $row['id'] ?>">
                  <input type="hidden" name="filepath" value="<?= $row['filepath'] ?>">
                  <input type="hidden" name="thumbpath" value="<?= $row['thumbpath'] ?>">
                  <button type="submit" class="btn btn-sm btn-danger">Hapus</button>
                </form>
              </div>
            </div>
          </div>

          <!-- Modal -->
          <div class="modal fade" id="modal<?= $row['id'] ?>" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered modal-lg">
              <div class="modal-content">
                <div class="modal-body p-0">
                  <img src="<?= $row['filepath'] ?>" class="img-fluid w-100" alt="Full Image">
                </div>
              </div>
            </div>
          </div>
        <?php endwhile; ?>
      <?php else: ?>
        <p class="text-center">Belum ada gambar.</p>
      <?php endif; ?>
    </div>

    <!-- Pagination -->
    <nav aria-label="Pagination">
      <ul class="pagination justify-content-center mt-4">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
          <li class="page-item <?= $i == $page ? 'active' : '' ?>">
            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
          </li>
        <?php endfor; ?>
      </ul>
    </nav>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>