<?php

namespace Tests\Feature\Admin;

use App\Models\Fasilitas;
use App\Models\Pengguna;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FasilitasTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dapat_menambah_fasilitas_dengan_foto_webp(): void
    {
        Storage::fake('public');
        $admin = Pengguna::factory()->create(['peran' => Pengguna::PERAN_ADMIN]);

        $response = $this->actingAs($admin)->post(route('admin.fasilitas.store'), [
            'nama_fasilitas' => 'Arcade Room',
            'deskripsi' => 'Mesin arcade dan sofa untuk bermain bersama.',
            'status_fasilitas' => 'aktif',
            'foto' => $this->fotoWebp(),
        ]);

        $fasilitas = Fasilitas::query()->where('nama_fasilitas', 'Arcade Room')->firstOrFail();

        $response->assertRedirect(route('admin.fasilitas.index'));
        $this->assertSame('aktif', $fasilitas->status_fasilitas);
        $this->assertMatchesRegularExpression('#^fasilitas/[a-f0-9-]+\.webp$#', $fasilitas->foto);
        Storage::disk('public')->assertExists($fasilitas->foto);
    }

    public function test_foto_selain_gambar_ditolak(): void
    {
        $admin = Pengguna::factory()->create(['peran' => Pengguna::PERAN_ADMIN]);

        $response = $this->actingAs($admin)->from(route('admin.fasilitas.index'))->post(route('admin.fasilitas.store'), [
            'nama_fasilitas' => 'Arcade Room',
            'deskripsi' => 'Mesin arcade dan sofa untuk bermain bersama.',
            'status_fasilitas' => 'aktif',
            'foto' => UploadedFile::fake()->create('foto.pdf', 20, 'application/pdf'),
        ]);

        $response->assertRedirect(route('admin.fasilitas.index'));
        $response->assertSessionHasErrors('foto');
    }

    public function test_admin_dapat_menambah_fasilitas_dengan_tagline_dan_foto_url(): void
    {
        $admin = Pengguna::factory()->create(['peran' => Pengguna::PERAN_ADMIN]);

        $this->actingAs($admin)->post(route('admin.fasilitas.store'), [
            'nama_fasilitas' => 'VR Room',
            'tagline' => 'masuk ke dunia lain.',
            'deskripsi' => 'Room VR dengan headset dan area bermain luas.',
            'status_fasilitas' => 'aktif',
            'foto_url' => 'https://contoh.com/vr-room.jpg',
        ])->assertRedirect(route('admin.fasilitas.index'));

        $fasilitas = Fasilitas::query()->where('nama_fasilitas', 'VR Room')->firstOrFail();

        $this->assertSame('masuk ke dunia lain.', $fasilitas->tagline);
        $this->assertSame('https://contoh.com/vr-room.jpg', $fasilitas->foto);
        $this->assertSame('https://contoh.com/vr-room.jpg', $fasilitas->fotoUrl());
    }

    public function test_pelanggan_tidak_boleh_mengelola_fasilitas(): void
    {
        $pelanggan = Pengguna::factory()->create(['peran' => Pengguna::PERAN_PELANGGAN]);

        $this->actingAs($pelanggan)->get(route('admin.fasilitas.index'))->assertForbidden();
    }

    public function test_fasilitas_yang_dipakai_room_tidak_dapat_dihapus(): void
    {
        $admin = Pengguna::factory()->create(['peran' => Pengguna::PERAN_ADMIN]);
        $fasilitas = Fasilitas::factory()->create();
        Room::factory()->create(['id_fasilitas' => $fasilitas->id_fasilitas]);

        $this->actingAs($admin)->delete(route('admin.fasilitas.destroy', $fasilitas))
            ->assertSessionHas('galat');

        $this->assertDatabaseHas('fasilitas', ['id_fasilitas' => $fasilitas->id_fasilitas]);
    }

    public function test_admin_dapat_memperbarui_nama_dan_status_fasilitas(): void
    {
        $admin = Pengguna::factory()->create(['peran' => Pengguna::PERAN_ADMIN]);
        $fasilitas = Fasilitas::factory()->create(['status_fasilitas' => 'aktif']);

        $this->actingAs($admin)->put(route('admin.fasilitas.update', $fasilitas), [
            'nama_fasilitas' => 'Nama diperbarui',
            'deskripsi' => 'Deskripsi fasilitas diperbarui.',
            'status_fasilitas' => 'nonaktif',
        ])->assertRedirect(route('admin.fasilitas.index'));

        $this->assertDatabaseHas('fasilitas', [
            'id_fasilitas' => $fasilitas->id_fasilitas,
            'nama_fasilitas' => 'Nama diperbarui',
            'status_fasilitas' => 'nonaktif',
        ]);
    }

    private function fotoWebp(): UploadedFile
    {
        $gambar = imagecreatetruecolor(20, 20);
        ob_start();
        imagewebp($gambar);
        $isi = ob_get_clean();
        imagedestroy($gambar);

        return UploadedFile::fake()->createWithContent('fasilitas.webp', $isi);
    }
}
