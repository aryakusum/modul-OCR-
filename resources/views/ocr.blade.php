<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Praktikum OCR KTP Laravel</title>
    <!-- Gunakan Bootstrap 5 dari CDN untuk tampilan yang rapi tanpa ribet setup CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Aplikasi OCR Sederhana (KTP)</h4>
                    </div>
                    <div class="card-body">

                        <!-- Tips untuk hasil maksimal -->
                        <div class="alert alert-info border-0 shadow-sm mb-4">
                            <h6 class="fw-bold"><i class="bi bi-info-circle"></i> Tips agar Data Terbaca Akurat:</h6>
                            <ul class="mb-0 small">
                                <li>Pastikan foto KTP <strong>terang dan fokus</strong> (tidak blur).</li>
                                <li>Posisi KTP harus <strong>lurus (horizontal)</strong>, jangan miring.</li>
                                <li>Usahakan tidak ada pantulan cahaya (flash) pada teks.</li>
                                <li>Potong (crop) gambar agar hanya menampilkan KTP saja (buang background meja/lantai).</li>
                            </ul>
                        </div>

                        <!-- Menampilkan Pesan Error jika ada -->
                        @if(session('error'))
                        <div class="alert alert-danger">
                            {{ session('error') }}
                        </div>
                        @endif

                        <!-- Form Upload Gambar -->
                        <form action="{{ route('ocr.process') }}" method="POST" enctype="multipart/form-data">
                            @csrf <!-- Token keamanan wajib di Laravel -->

                            <div class="mb-3">
                                <label for="image" class="form-label">Pilih Gambar KTP</label>
                                <input type="file" name="image" class="form-control" required>
                                <div class="form-text">Format: JPG, JPEG, PNG. Maks: 2MB.</div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">Ekstrak Teks</button>
                        </form>

                        <hr class="my-4">

                        <!-- Hasil OCR akan muncul di sini jika berhasil -->
                        @if(session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>

                        <div class="row">
                            <!-- Tampilkan Gambar yang diupload -->
                            <div class="col-md-6 mb-3">
                                <h5>Gambar Asli:</h5>
                                <img src="{{ asset(session('image')) }}" class="img-fluid rounded border" alt="Uploaded Image">
                            </div>

                            <!-- Tampilkan Teks Hasil Ekstraksi -->
                            <div class="col-md-6">
                                <h5>Hasil Teks OCR:</h5>
                                <div class="p-3 bg-white border rounded" style="white-space: pre-wrap; min-height: 200px;">
                                    {{ session('text') }}
                                </div>
                            </div>
                        </div>
                        @endif

                    </div>
                </div>

                <div class="text-center mt-3 text-muted">
                    <small>Dibuat untuk Praktikum OCR Laravel</small>
                </div>

            </div>
        </div>
    </div>

</body>

</html>