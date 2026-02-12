<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http; // Menggunakan HTTP Client Laravel (Guzzle wrapper)
use App\Models\Ktp;

class OcrController extends Controller
{
    public function index()
    {
        return view('ocr');
    }

    public function process(Request $request)
    {
        // 1. Validasi gambar
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        // 2. Simpan gambar sementara (opsional, tapi berguna untuk preview)
        $image = $request->file('image');
        $imageName = time() . '_' . $image->getClientOriginalName();
        $image->move(public_path('uploads'), $imageName);
        $imagePath = public_path('uploads/' . $imageName);

        try {
            // 3. Kirim ke OCR.space API
            // Dokumentasi: https://ocr.space/ocrapi
            $response = Http::attach(
                'file',
                file_get_contents($imagePath),
                $imageName
            )->post('https://api.ocr.space/parse/image', [
                'apikey' => env('OCR_SPACE_KEY', 'helloworld'),
                'language' => 'eng', // 'helloworld' key hanya support 'eng'. Ubah ke 'ind' jika punya key pribadi.
                'isOverlayRequired' => 'false',
                'OCREngine' => '2', // Engine 2 lebih bagus untuk angka/ID card
            ]);

            $result = $response->json();

            // Cek apakah API berhasil
            if (isset($result['OCRExitCode']) && $result['OCRExitCode'] == 1) {
                // Ambil teks dari hasil parsing
                $text = $result['ParsedResults'][0]['ParsedText'];

                // 4. Parsing Teks menjadi Data Terstruktur
                $data = $this->parseKtp($text);
                $data['image_path'] = 'uploads/' . $imageName;

                // 5. Kembalikan ke View untuk Review
                return view('ocr-review', compact('data', 'text'));
            } else {
                // Jika API error
                $errorMessage = $result['ErrorMessage'][0] ?? 'Gagal memproses gambar.';
                return back()->with('error', 'API Error: ' . $errorMessage);
            }
        } catch (\Exception $e) {
            return back()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    public function store(Request $request)
    {
        // Validasi data sebelum disimpan
        $validated = $request->validate([
            'nik' => 'required|unique:ktps,nik',
            'nama' => 'required',
            'tempat_lahir' => 'nullable',
            'tanggal_lahir' => 'nullable|date',
            'alamat' => 'nullable',
            'image_path' => 'required',
        ]);

        // Simpan ke Database
        Ktp::create($request->all());

        return redirect()->route('ocr.index')->with('success', 'Data KTP berhasil diregistrasi!');
    }

    private function parseKtp($text)
    {
        $data = [];
        $lines = preg_split('/\r\n|\r|\n/', $text);

        // 1. Cari NIK
        $nikIndex = -1;
        foreach ($lines as $index => $line) {
            // Cari 16 digit angka, abaikan karakter aneh sebelumnya (misal • 3302...)
            if (preg_match('/(\d{16})/', $line, $matches)) {
                $data['nik'] = $matches[1];
                $nikIndex = $index;
                break;
            }
        }

        // 2. Cari Tanggal Lahir (Format: 0308-20 atau 03-08-2004)
        $tglLahirIndex = -1;
        foreach ($lines as $index => $line) {
            // Regex lebih longgar: 2 digit, pemisah bebas, 2 digit, pemisah bebas, 2-4 digit
            if (preg_match('/(\d{2})[- \/]?(\d{2})[- \/]?(\d{2,4})/', $line, $matches)) {
                $day = $matches[1];
                $month = $matches[2];
                $year = $matches[3];

                // Normalisasi tahun (misal 20 -> 2020, tapi untuk KTP biasanya 19xx atau 20xx)
                if (strlen($year) == 2) {
                    $year = ($year > 50) ? '19' . $year : '20' . $year; // Asumsi sederhana
                }

                $possibleDate = "$year-$month-$day";
                // Validasi checkdate
                if (checkdate($month, $day, $year)) {
                    $data['tanggal_lahir'] = $possibleDate;
                    $tglLahirIndex = $index;

                    // AMBIL TEMPAT LAHIR
                    // Hapus string tanggal yang sudah ketemu
                    $lineWithoutDate = str_replace($matches[0], '', $line);

                    // Bersihkan Label "Tempat", "Tgl", "Lahir", ":"
                    // Regex: Hapus kata-kata tersebut (case insensitive)
                    $cleanPlace = preg_replace('/\b(Tempat|Tempal|Tgl|Lahir)\b/i', '', $lineWithoutDate);

                    // Hapus karakter sisa selain huruf dan spasi (misal : / - ,)
                    // Kita sisakan koma sebentar untuk explode, tapi biasanya koma memisahkan kota.
                    // Jika format "JAKARTA, 18...", koma ada di antara kota dan tgl.

                    $cleanPlace = str_replace([':', '/', '-'], '', $cleanPlace);

                    $parts = explode(',', $cleanPlace);
                    $data['tempat_lahir'] = trim($parts[0]);
                    break;
                }
            }
        }

        // 3. Cari Nama (Antara NIK dan Tgl Lahir)
        // 3. Cari Nama (Antara NIK dan Tgl Lahir)
        if ($nikIndex != -1) {
            $namaCandidates = [];

            $startIndex = $nikIndex + 1;
            $endIndex = ($tglLahirIndex != -1) ? $tglLahirIndex : $startIndex + 3;

            for ($i = $startIndex; $i < $endIndex; $i++) {
                if (isset($lines[$i])) {
                    $line = trim($lines[$i]);

                    // Filter Baris Sampah
                    $ignored = ['NIK', 'PROVINSI', 'KABUPATEN', 'KOTA', 'GOL. DARAH', 'Gol', 'Darah', 'LAKI-LAKI', 'PEREMPUAN', 'AGAMA', 'KARYAWAN'];
                    $isIgnored = false;

                    // Cek panjang minimal
                    if (strlen($line) < 3) continue;

                    foreach ($ignored as $ign) {
                        if (str_contains(strtoupper($line), $ign)) $isIgnored = true;
                    }

                    // Jika baris mengandung angka, kemungkinan bukan nama (kecuali gelar yg aneh, tapi aman di skip)
                    if (preg_match('/\d/', $line)) $isIgnored = true;

                    if (!$isIgnored) {
                        $namaCandidates[] = $line;
                    }
                }
            }

            $rawNama = implode(' ', $namaCandidates);
            // BERSISHKAN Label "Nama", "Tempat", "Tgl", "Lahir" jika tertinggal
            // Regex: Hapus kata "Nama", "Tempat", "Tempal", "Tgl", "Lahir", serta tanda titik dua ":"
            $cleanNama = preg_replace('/\b(Nama|Tempat|Tempal|Tgl|Lahir|:)\b/i', '', $rawNama);
            $cleanNama = str_replace(':', '', $cleanNama); // Extra clean for colons
            $data['nama'] = trim(preg_replace('/[^A-Za-z\s\.]/', '', $cleanNama));
        }

        // 4. Cari Alamat
        $alamatIndex = -1;
        foreach ($lines as $index => $line) {
            // Cari kata kunci Alamat
            if (preg_match('/(Alamat)/i', $line)) {
                $alamatIndex = $index;
                // Cek apakah di baris yang sama ada isinya? (Misal: "Alamat : Jl. Mawar")
                $cleanLine = trim(preg_replace('/(Alamat|:)/i', '', $line));
                // Hapus karakter non-huruf/angka di awal string (misal : atau .)
                $cleanLine = ltrim($cleanLine, ":. ");

                if (!empty($cleanLine)) {
                    $data['alamat'] = $cleanLine;
                } elseif (isset($lines[$index + 1])) {
                    // Jika kosong, ambil baris bawahnya (Misal: "Alamat" \n "MESS KESDAM...")
                    $data['alamat'] = ltrim(trim($lines[$index + 1]), ":. ");
                }
                break;
            }
        }

        // Fallback Alamat jika tidak ketemu kata "Alamat"
        if (!isset($data['alamat'])) {
            foreach ($lines as $line) {
                if (preg_match('/\b(Jalan|Jln|Jl\.|Dusun|Kmp|Blok|Mess|Perum)\b/i', $line)) {
                    $clean = preg_replace('/(Alamat|:)/i', '', $line);
                    $data['alamat'] = trim($clean);
                    break;
                }
            }
        }

        return $data;
    }
}
