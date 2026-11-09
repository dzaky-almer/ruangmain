<?php

// File ini mengatur data fasilitas (kategori seperti PlayStation, Nintendo, Netflix Room).
// Fungsi: CRUD fasilitas termasuk unggah foto dan pembersihan file foto lama.
// Dipakai oleh: routes/web.php baris 58-59 (Route::resource('fasilitas', ...)).
// Mengambil data dari model: Fasilitas.php.
// Validasi input ditangani oleh: app/Http/Requests/SimpanFasilitasRequest.php.
//
// Supaya foto hasil unggahan bisa dibuka lewat browser, jalankan sekali:
//   php artisan storage:link
// Untuk menguji fitur CRUD fasilitas:
//   php artisan test --filter=Fasilitas

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SimpanFasilitasRequest;
use App\Models\Fasilitas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class FasilitasController extends Controller
{
    // GET /admin/fasilitas — daftar fasilitas + pencarian nama dan filter status.
    // Untuk mengujinya:
    //   php artisan test --filter=FasilitasTest
    public function index(Request $request): View
    {
        $fasilitas = Fasilitas::query()
            // withCount('room') menambah kolom jumlah room, dipakai di tabel admin.
            ->withCount('room')
            // Relasi room dimuat supaya view bisa menyebut nama room tanpa query tambahan.
            ->with('room')
            ->when($request->string('cari')->trim()->value(), function ($query, string $cari): void {
                $query->where('nama_fasilitas', 'like', "%{$cari}%");
            })
            ->when(in_array($request->input('status'), ['aktif', 'nonaktif'], true), function ($query, string $status): void {
                $query->where('status_fasilitas', $status);
            })
            ->orderBy('nama_fasilitas')
            ->get();

        return view('admin.fasilitas.index', compact('fasilitas'));
    }

    // GET /admin/fasilitas/create — halaman form tambah fasilitas.
    // Untuk mencoba manual: buka http://localhost:8000/admin/fasilitas/create
    public function create(): View
    {
        // Objek kosong dipakai supaya form yang sama bisa dipakai untuk tambah dan edit.
        return view('admin.fasilitas.form', ['fasilitas' => new Fasilitas]);
    }

    // POST /admin/fasilitas — menyimpan fasilitas baru beserta fotonya.
    public function store(SimpanFasilitasRequest $request): RedirectResponse
    {
        Fasilitas::query()->create($this->dataFasilitas($request));

        return redirect()->route('admin.fasilitas.index')
            ->with('sukses', 'Fasilitas berhasil ditambahkan.');
    }

    // GET /admin/fasilitas/{fasilitas}/edit — halaman form ubah fasilitas.
    public function edit(Fasilitas $fasilitas): View
    {
        return view('admin.fasilitas.form', compact('fasilitas'));
    }

    // PUT/PATCH /admin/fasilitas/{fasilitas} — menyimpan perubahan fasilitas.
    public function update(SimpanFasilitasRequest $request, Fasilitas $fasilitas): RedirectResponse
    {
        // Foto lama disimpan dulu supaya bisa dihapus setelah data baru tersimpan.
        $fotoLama = $fasilitas->foto;
        $data = $this->dataFasilitas($request, $fasilitas);
        $fasilitas->update($data);

        // Kalau ada foto baru, file foto lama dibersihkan dari storage.
        if (array_key_exists('foto', $data) && $fotoLama) {
            $this->hapusFoto($fotoLama);
        }

        return redirect()->route('admin.fasilitas.index')
            ->with('sukses', 'Fasilitas berhasil diperbarui.');
    }

    // DELETE /admin/fasilitas/{fasilitas} — menghapus fasilitas.
    public function destroy(Fasilitas $fasilitas): RedirectResponse
    {
        // Fasilitas yang masih dipakai room tidak boleh dihapus,
        // supaya data room tidak kehilangan kategorinya.
        if ($fasilitas->room()->exists()) {
            return back()->with('galat', 'Fasilitas tidak dapat dihapus karena masih dipakai oleh room.');
        }

        $foto = $fasilitas->foto;
        $fasilitas->delete();

        // File foto ikut dihapus supaya tidak menumpuk di storage.
        if ($foto) {
            $this->hapusFoto($foto);
        }

        return redirect()->route('admin.fasilitas.index')
            ->with('sukses', 'Fasilitas berhasil dihapus.');
    }

    // Menyusun data yang akan disimpan dari input form.
    // Tiga sumber foto: file unggahan, URL teks, atau tidak ada (tetap memakai foto lama).
    private function dataFasilitas(SimpanFasilitasRequest $request, ?Fasilitas $fasilitas = null): array
    {
        $data = $request->safe()->only(['nama_fasilitas', 'tagline', 'deskripsi', 'status_fasilitas']);

        if ($request->hasFile('foto')) {
            // Nama file dibuat acak (UUID) supaya tidak bentrok dengan unggahan lain.
            $data['foto'] = $request->file('foto')->storeAs(
                'fasilitas',
                Str::uuid().'.'.$request->file('foto')->extension(),
                'public',
            );
        } elseif ($request->filled('foto_url')) {
            // Alternatif: admin menempelkan URL gambar dari luar.
            $data['foto'] = $request->string('foto_url')->trim()->value();
        }

        return $data;
    }

    // Menghapus file foto dari disk `public`.
    // URL luar (berawalan http) dilewati karena bukan file milik aplikasi ini.
    private function hapusFoto(?string $foto): void
    {
        if ($foto && ! str_starts_with($foto, 'http')) {
            Storage::disk('public')->delete($foto);
        }
    }
}
