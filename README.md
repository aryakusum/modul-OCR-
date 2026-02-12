# MODUL PRAKTIKUM: SISTEM REGISTRASI OTOMATIS BERBASIS OCR (KTP)

**Mata Kuliah:** [Isi Mata Kuliah]
**Topik:** Integrasi API, Regular Expression, dan Penanganan Citra
**Framework:** Laravel

---

## A. TUJUAN PEMBELAJARAN

1.  Mahasiswa mampu mengimplementasikan layanan pihak ketiga (**OCR.space API**) dalam aplikasi Laravel.
2.  Mahasiswa memahami penggunaan **Environment Variables (`.env`)** untuk keamanan kredensial.
3.  Mahasiswa mampu menerapkan logika **Regular Expression (Regex)** untuk mengekstrak data tidak terstruktur menjadi terstruktur.
4.  Mahasiswa mampu menangani input file gambar dan menyimpannya ke storage server.

## B. DASAR TEORI

**OCR (Optical Character Recognition)** adalah teknologi yang mengubah gambar teks (ketikan, tulisan tangan, atau cetakan) menjadi teks mesin yang dapat diedit dan dicari. Dalam modul ini, kita menggunakan **OCR.space API**, layanan cloud gratis yang memproses gambar dan mengembalikan hasil dalam format JSON.

Pentingnya **Regex**: Hasil OCR seringkali tidak rapi (berantakan, baris tertukar, ada simbol aneh). Regex digunakan untuk mencari pola tertentu (seperti NIK yang pasti 16 digit angka) di tengah teks yang acak.

## C. ALAT DAN BAHAN

1.  PC/Laptop dengan koneksi Internet (Wajib).
2.  XAMPP (PHP 8.x, MySQL).
3.  Composer & Git.
4.  Text Editor (VS Code).
5.  API Key OCR.space (Gratis).

---

## D. LANGKAH KERJA

### 1. Persiapan Project

Buat project Laravel baru dan masuk ke direktorinya.

```bash
composer create-project laravel/laravel modul-ocr
cd modul-ocr
```

### 2. Konfigurasi Database & Environment

Buka file `.env` dan sesuaikan konfigurasi berikut. Tambahkan `OCR_SPACE_KEY` di paling bawah.

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE="modul OCR"  # Sesuaikan dengan nama database di PHPMyAdmin
DB_USERNAME=root
DB_PASSWORD=

# API Key OCR (Gunakan 'helloworld' untuk testing atau daftar di ocr.space)
OCR_SPACE_KEY=helloworld
```

> **Tugas:** Buat database baru di PHPMyAdmin dengan nama `modul OCR`.

### 3. Membuat Database Migration

Kita membutuhkan tabel untuk menyimpan data hasil scan.

```bash
php artisan make:model Ktp -m
```

Buka file migration di `database/migrations/xxxx_xx_xx_create_ktps_table.php` dan isi strukturnya:

```php
public function up()
{
    Schema::create('ktps', function (Blueprint $table) {
        $table->id();
        $table->string('nik')->unique();
        $table->string('nama');
        $table->string('tempat_lahir')->nullable();
        $table->date('tanggal_lahir')->nullable();
        $table->text('alamat')->nullable(); // Pakai Text agar muat panjang
        $table->string('image_path');
        $table->timestamps();
    });
}
```

Simpan file, lalu jalankan migrasi:

```bash
php artisan migrate
```

### 4. Setup Model

Buka `app/Models/Ktp.php` dan tambahkan `fillable` agar data bisa disimpan.

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ktp extends Model
{
    use HasFactory;

    protected $fillable = [
        'nik',
        'nama',
        'tempat_lahir',
        'tanggal_lahir',
        'alamat',
        'image_path'
    ];
}
```

### 5. Membuat Controller (Inti Program)

Di sinilah logika utama berada. Kita tidak menggunakan library tambahan, cukup fitur bawaan Laravel (`Http Client`).

```bash
php artisan make:controller OcrController
```

Buka `app/Http/Controllers/OcrController.php` dan salin kode lengkap berikut:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Ktp;

class OcrController extends Controller
{
    // Menampilkan Halaman Upload
    public function index()
    {
        return view('ocr');
    }

    // Proses OCR: Upload -> API -> Parsing
    public function process(Request $request)
    {
        // 1. Validasi File
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        // 2. Simpan Gambar ke Public Folder
        $image = $request->file('image');
        $imageName = time() . '_' . $image->getClientOriginalName();
        $image->move(public_path('uploads'), $imageName);
        $imagePath = public_path('uploads/' . $imageName);

        try {
            // 3. Kirim Request ke OCR.space API
            $response = Http::attach(
                'file', file_get_contents($imagePath), $imageName
            )->post('https://api.ocr.space/parse/image', [
                'apikey' => env('OCR_SPACE_KEY', 'helloworld'),
                'language' => 'eng', // Gunakan 'eng' untuk key 'helloworld'
                'isOverlayRequired' => 'false',
                'OCREngine' => '2', // Engine 2 lebih optimal untuk ID Card
            ]);

            $result = $response->json();

            // 4. Cek Keberhasilan API
            if (isset($result['OCRExitCode']) && $result['OCRExitCode'] == 1) {
                $text = $result['ParsedResults'][0]['ParsedText'];

                // Panggil fungsi parsing cerdas
                $data = $this->parseKtp($text);
                $data['image_path'] = 'uploads/' . $imageName;

                return view('ocr-review', compact('data', 'text'));
            } else {
                $errorMessage = $result['ErrorMessage'][0] ?? 'Gagal menghubungi API.';
                return back()->with('error', 'OCR Error: ' . $errorMessage);
            }

        } catch (\Exception $e) {
            return back()->with('error', 'System Error: ' . $e->getMessage());
        }
    }

    // Fungsi Menyimpan Data Final
    public function store(Request $request)
    {
        $request->validate([
            'nik' => 'required|unique:ktps,nik',
            'nama' => 'required',
            'image_path' => 'required',
        ]);

        Ktp::create($request->all());

        return redirect()->route('ocr.index')->with('success', 'Registrasi Berhasil!');
    }

    // LOGIKA PARSING (REGEX)
    private function parseKtp($text)
    {
        $data = [];
        $lines = preg_split('/\r\n|\r|\n/', $text);

        // A. Cari NIK (16 Digit Angka)
        $nikIndex = -1;
        foreach ($lines as $index => $line) {
            if (preg_match('/(\d{16})/', $line, $matches)) {
                $data['nik'] = $matches[1];
                $nikIndex = $index;
                break;
            }
        }

        // B. Cari Tanggal Lahir & Tempat Lahir
        $tglLahirIndex = -1;
        foreach ($lines as $index => $line) {
            // Regex mencari pola tanggal (dd-mm-yyyy atau dd/mm/yyyy)
            if (preg_match('/(\d{2})[- \/]?(\d{2})[- \/]?(\d{2,4})/', $line, $matches)) {
                $day = $matches[1];
                $month = $matches[2];
                $year = $matches[3];
                // Koreksi tahun 2 digit (misal 86 -> 1986)
                if (strlen($year) == 2) $year = ($year > 50 ? '19' : '20') . $year;

                if (checkdate($month, $day, $year)) {
                    $data['tanggal_lahir'] = "$year-$month-$day";
                    $tglLahirIndex = $index;

                    // Ekstrak Tempat Lahir (Hapus tanggal & label dari baris ini)
                    $lineClean = str_replace($matches[0], '', $line); // Hapus tanggal
                    $lineClean = preg_replace('/\b(Tempat|Tempal|Tgl|Lahir)\b/i', '', $lineClean); // Hapus label
                    $lineClean = str_replace([':', '/', '-'], '', $lineClean); // Hapus simbol

                    // Biasanya format: "JAKARTA," -> Ambil kata sebelum koma
                    $parts = explode(',', $lineClean);
                    $data['tempat_lahir'] = trim($parts[0]);
                    break;
                }
            }
        }

        // C. Cari Nama (Looping baris antara NIK dan Tgl Lahir)
        if ($nikIndex != -1) {
            $namaCandidates = [];
            $start = $nikIndex + 1;
            // Jika Tgl Lahir ketemu, stop di sana. Jika tidak, ambil maksimal 3 baris ke bawah.
            $end = ($tglLahirIndex != -1) ? $tglLahirIndex : $start + 3;

            for ($i = $start; $i < $end; $i++) {
                if (isset($lines[$i])) {
                    $line = trim($lines[$i]);
                    // Filter kata-kata sampah
                    $badWords = ['NIK', 'PROVINSI', 'KOTA', 'GOL', 'DARAH', 'LAKI', 'PEREMPUAN', 'AGAMA'];
                    $isBad = false;
                    foreach ($badWords as $w) { if (str_contains(strtoupper($line), $w)) $isBad = true; }
                    if (preg_match('/\d/', $line)) $isBad = true; // Nama jarang mengandung angka

                    if (!$isBad && strlen($line) > 2) {
                        $namaCandidates[] = $line;
                    }
                }
            }

            // Bersihkan sisa label "Nama :" jika terbawa
            $rawNama = implode(' ', $namaCandidates);
            $cleanNama = preg_replace('/\b(Nama|Tempat|Tgl|:)\b/i', '', $rawNama);
            $data['nama'] = trim(preg_replace('/[^A-Za-z\s\.]/', '', $cleanNama));
        }

        // D. Cari Alamat
        foreach ($lines as $index => $line) {
            if (preg_match('/(Alamat)/i', $line)) {
                // Coba ambil isi di baris yang sama (Alamat : Jl. Mawar)
                $clean = trim(preg_replace('/(Alamat|:)/i', '', $line));
                $clean = ltrim($clean, ":. ");

                if (!empty($clean)) {
                    $data['alamat'] = $clean;
                } elseif (isset($lines[$index + 1])) {
                    // Jika kosong, ambil baris di bawahnya
                    $data['alamat'] = ltrim(trim($lines[$index + 1]), ":. ");
                }
                break;
            }
        }

        return $data;
    }
}
```

### 6. Membuat Views (Tampilan)

#### A. File `resources/views/ocr.blade.php` (Halaman Upload)

Gunakan Bootstrap sederhana agar rapi.

```html
<!DOCTYPE html>
<html>
    <head>
        <title>Modul Praktikum OCR Laravel</title>
        <link
            href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
            rel="stylesheet"
        />
    </head>
    <body class="bg-light">
        <div class="container mt-5">
            <h2 class="text-center mb-4">
                Sistem Registrasi KTP Otomatis (OCR)
            </h2>

            <div class="card shadow-sm mx-auto" style="max-width: 600px;">
                <div class="card-body">
                    @if(session('success'))
                    <div class="alert alert-success">
                        {{ session('success') }}
                    </div>
                    @endif @if(session('error'))
                    <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif

                    <div class="alert alert-info">
                        <strong>Tips Foto:</strong> Pastikan KTP lurus
                        (horizontal), terang, dan hasil crop rapi.
                    </div>

                    <form
                        action="{{ route('ocr.process') }}"
                        method="POST"
                        enctype="multipart/form-data"
                    >
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Upload Foto KTP</label>
                            <input
                                type="file"
                                name="image"
                                class="form-control"
                                required
                                accept="image/*"
                            />
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            Proses Baca Data
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </body>
</html>
```

#### B. File `resources/views/ocr-review.blade.php` (Halaman Konfirmasi)

Penting: Gunakan `value="{{ $data['key'] ?? '' }}"` untuk mengisi form otomatis.

```html
<!DOCTYPE html>
<html>
    <head>
        <title>Review Data KTP</title>
        <link
            href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
            rel="stylesheet"
        />
    </head>
    <body class="bg-light">
        <div class="container mt-5 pb-5">
            <div class="row">
                <!-- Kolom Kiri: Gambar Asli -->
                <div class="col-md-5">
                    <div class="card">
                        <img
                            src="{{ asset($data['image_path']) }}"
                            class="card-img-top"
                        />
                        <div class="card-body">
                            <h5>Foto KTP</h5>
                            <hr />
                            <h6>Raw Text (Hasil Mentah OCR):</h6>
                            <pre
                                class="bg-dark text-white p-2"
                                style="font-size: 10px; max-height: 200px; overflow-y:scroll;"
                            >
{{ $text }}</pre
                            >
                        </div>
                    </div>
                </div>

                <!-- Kolom Kanan: Form Edit -->
                <div class="col-md-7">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Verifikasi Data</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small">
                                Silakan koreksi jika ada kesalahan baca.
                            </p>

                            <form
                                action="{{ route('ocr.store') }}"
                                method="POST"
                            >
                                @csrf
                                <input
                                    type="hidden"
                                    name="image_path"
                                    value="{{ $data['image_path'] }}"
                                />

                                <div class="mb-3">
                                    <label>NIK</label>
                                    <input
                                        type="text"
                                        name="nik"
                                        class="form-control fw-bold"
                                        value="{{ $data['nik'] ?? '' }}"
                                    />
                                </div>

                                <div class="mb-3">
                                    <label>Nama Lengkap</label>
                                    <input
                                        type="text"
                                        name="nama"
                                        class="form-control"
                                        value="{{ $data['nama'] ?? '' }}"
                                    />
                                </div>

                                <div class="row">
                                    <div class="col-6 mb-3">
                                        <label>Tempat Lahir</label>
                                        <input
                                            type="text"
                                            name="tempat_lahir"
                                            class="form-control"
                                            value="{{ $data['tempat_lahir'] ?? '' }}"
                                        />
                                    </div>
                                    <div class="col-6 mb-3">
                                        <label>Tanggal Lahir</label>
                                        <input
                                            type="date"
                                            name="tanggal_lahir"
                                            class="form-control"
                                            value="{{ $data['tanggal_lahir'] ?? '' }}"
                                        />
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label>Alamat</label>
                                    <textarea
                                        name="alamat"
                                        class="form-control"
                                        rows="2"
                                    >
{{ $data['alamat'] ?? '' }}</textarea
                                    >
                                </div>

                                <div class="d-flex justify-content-between">
                                    <a
                                        href="{{ route('ocr.index') }}"
                                        class="btn btn-secondary"
                                        >Batal / Upload Ulang</a
                                    >
                                    <button
                                        type="submit"
                                        class="btn btn-success"
                                    >
                                        Simpan ke Database
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
```

### 7. Routing

Buka `routes/web.php` dan daftarkan route kita.

```php
use App\Http\Controllers\OcrController;

Route::get('/', function () { return redirect()->route('ocr.index'); });

Route::get('/ocr', [OcrController::class, 'index'])->name('ocr.index');
Route::post('/ocr/process', [OcrController::class, 'process'])->name('ocr.process');
Route::post('/ocr/store', [OcrController::class, 'store'])->name('ocr.store');
```

---



**Selamat Mengerjakan!**
