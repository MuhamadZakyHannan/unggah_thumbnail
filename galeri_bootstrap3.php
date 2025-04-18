<?php include 'koneksi.php'; ?>

<?php
// Proses upload gambar
if (isset($_POST['submit']) && isset($_FILES['gambar'])) {
    $file = $_FILES['gambar'];
    $filename = $file['name'];
    $tempname = $file['tmp_name'];

    $folder = "uploads/" . uniqid() . "_" . basename($filename);
    $thumb = "thumbnails/" . uniqid() . "_thumb_" . basename($filename);

    // Pastikan folder 'thumbnails' ada, buat jika belum
    if (!is_dir('thumbnails')) {
        mkdir('thumbnails', 0777, true); // Membuat folder dengan izin yang tepat
    }

    if (move_uploaded_file($tempname, $folder)) {
        // Buat thumbnail sederhana (copy, bisa diganti resize pakai GD)
        copy($folder, $thumb);
        list($width, $height) = getimagesize($folder);

        $stmt = $conn->prepare("INSERT INTO gambar_thumbnail (filepath, thumbpath, width, height, uploaded_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssii", $folder, $thumb, $width, $height);
        $stmt->execute();
    }
}

// Ambil tanggal dari parameter GET
$tanggal = $_GET['tanggal'] ?? null;

// Pagination setup
$limit = 6;  // Display 6 images per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Hitung total gambar
$count_sql = "SELECT COUNT(*) FROM gambar_thumbnail" . ($tanggal ? " WHERE DATE(uploaded_at) = '$tanggal'" : "");
$total_result = $conn->query($count_sql)->fetch_row()[0];
$total_pages = ceil($total_result / $limit);

// Query gambar sesuai filter + pagination
$sql = "SELECT * FROM gambar_thumbnail" . ($tanggal ? " WHERE DATE(uploaded_at) = '$tanggal'" : "") . " ORDER BY uploaded_at DESC LIMIT $limit OFFSET $offset";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Galeri Gambar Responsive</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .thumb {
      width: 100%;  /* Make the thumbnails take up full width of the column */
      max-width: 150px;  /* Limit max width */
      height: auto;  /* Maintain aspect ratio */
      margin: 0 auto;  /* Center align thumbnails */
    }

    .card-body {
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      height: 100%;
    }

    .card-footer {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: auto;
    }

    .card-body .card-text {
      margin-bottom: 10px;  /* Add space between image size text and footer */
      font-size: 14px; /* Adjust font size */
      color: #555;  /* Slightly lighter color for the text */
    }

    .btn-sm {
      padding: 5px 10px;  /* Make buttons smaller */
    }
  </style>
</head>

<body>
  <div class="container py-5">
    <h2 class="text-center mb-4">Galeri Gambar</h2>

    <!-- Form Upload Gambar -->
    <div class="mb-4">
      <form method="POST" enctype="multipart/form-data" class="row g-3">
        <div class="col-md-6">
          <input type="file" name="gambar" class="form-control" required>
        </div>
        <div class="col-md-auto">
          <button type="submit" name="submit" class="btn btn-primary">Upload Gambar</button>
        </div>
      </form>
    </div>

    <!-- Filter berdasarkan tanggal -->
    <form method="GET" class="row g-3 mb-4">
      <div class="col-md-4">
        <input type="date" name="tanggal" class="form-control" value="<?= htmlspecialchars($tanggal) ?>">
      </div>
      <div class="col-md-auto">
        <button type="submit" class="btn btn-success">Filter Tanggal</button>
        <a href="galeri_bootstrap2.php" class="btn btn-secondary">Reset</a>
      </div>
    </form>

    <?php if ($tanggal): ?>
      <p class="text-muted">Menampilkan gambar yang diunggah pada tanggal: <strong><?= htmlspecialchars($tanggal) ?></strong></p>
    <?php endif; ?>

    <div class="row g-4">
      <?php
      if ($result->num_rows > 0) {
        $counter = 0;
        while ($row = $result->fetch_assoc()) {
          // Start a new row after every 3 images
          if ($counter % 3 == 0 && $counter > 0) {
            echo "</div><div class='row g-4'>"; // Close and open new row
          }

          echo "
          <div class='col-12 col-sm-6 col-md-4 col-lg-4'>
            <div class='card shadow-sm h-100'>
              <img src='{$row['thumbpath']}' class='card-img-top img-thumbnail thumb' alt='Thumbnail' data-bs-toggle='modal' data-bs-target='#modal{$row['id']}'>
              <div class='card-body'>
                <p class='card-text'><strong>Ukuran:</strong> {$row['width']}x{$row['height']}</p>
              </div>
              <div class='card-footer'>
                <a href='{$row['filepath']}' class='btn btn-sm btn-primary' target='_blank'>Lihat Asli</a>
                <form action='hapus.php' method='POST' onsubmit='return confirm(\"Yakin ingin menghapus gambar ini?\")'>
                  <input type='hidden' name='id' value='{$row['id']}'>
                  <input type='hidden' name='filepath' value='{$row['filepath']}'>
                  <input type='hidden' name='thumbpath' value='{$row['thumbpath']}'>
                  <button type='submit' class='btn btn-sm btn-danger'>Hapus</button>
                </form>
              </div>
            </div>
          </div>

          <!-- Modal -->
          <div class='modal fade' id='modal{$row['id']}' tabindex='-1'>
            <div class='modal-dialog modal-dialog-centered modal-lg'>
              <div class='modal-content'>
                <div class='modal-body p-0'>
                  <img src='{$row['filepath']}' class='img-fluid w-100' alt='Full Image'>
                </div>
              </div>
            </div>
          </div>
          ";

          $counter++;
        }
      } else {
        echo "<p class='text-center'>Belum ada gambar diunggah.</p>";
      }
      ?>
    </div>

    <!-- Pagination -->
    <nav aria-label="Page navigation">
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
