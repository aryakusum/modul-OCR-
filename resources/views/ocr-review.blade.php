<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Data KTP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light p-5">
    <div class="container">
        <div class="row">
            <!-- Kolom Gambar -->
            <div class="col-md-5">
                <div class="card shadow-sm">
                    <div class="card-header bg-secondary text-white">Gambar KTP</div>
                    <div class="card-body">
                        <img src="{{ asset($data['image_path']) }}" class="img-fluid rounded">
                    </div>
                </div>
            </div>

            <!-- Kolom Form -->
            <div class="col-md-7">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">Registrasi Data</div>
                    <div class="card-body">

                        <p class="text-muted">Silakan periksa dan koreksi data hasil OCR dibawah ini sebelum disimpan.</p>

                        <form action="{{ route('ocr.store') }}" method="POST">
                            @csrf
                            <input type="hidden" name="image_path" value="{{ $data['image_path'] }}">

                            <div class="mb-3">
                                <label class="form-label">NIK</label>
                                <input type="text" name="nik" class="form-control" value="{{ $data['nik'] ?? '' }}">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Nama</label>
                                <input type="text" name="nama" class="form-control" value="{{ $data['nama'] ?? '' }}">
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Tempat Lahir</label>
                                    <input type="text" name="tempat_lahir" class="form-control" value="{{ $data['tempat_lahir'] ?? '' }}">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Tanggal Lahir</label>
                                    <input type="date" name="tanggal_lahir" class="form-control" value="{{ $data['tanggal_lahir'] ?? '' }}">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Alamat</label>
                                <textarea name="alamat" class="form-control" rows="2">{{ $data['alamat'] ?? '' }}</textarea>
                            </div>

                            <!-- Tambahkan field lain sesuai kebutuhan (Agama, Status, dll) -->

                            <div class="d-flex justify-content-between">
                                <a href="{{ route('ocr.index') }}" class="btn btn-secondary">Batal / Upload Ulang</a>
                                <button type="submit" class="btn btn-success">Simpan Data</button>
                            </div>
                        </form>

                    </div>
                </div>

                <!-- Debug Raw Text -->
                <div class="mt-4">
                    <button class="btn btn-sm btn-outline-info" type="button" data-bs-toggle="collapse" data-bs-target="#rawText">
                        Lihat Teks Mentah OCR
                    </button>
                    <div class="collapse mt-2" id="rawText">
                        <div class="card card-body bg-dark text-white font-monospace">
                            <small>{!! nl2br(e($text)) !!}</small>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>