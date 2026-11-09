<?php

namespace Tests\Feature\Auth;

use App\Models\Pengguna;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AutentikasiTest extends TestCase
{
    use RefreshDatabase;

    public function test_pelanggan_dapat_registrasi_dengan_password_terhash(): void
    {
        $response = $this->post(route('register.store'), [
            'nama' => 'Pelanggan Baru',
            'email' => 'baru@ruangmain.test',
            'no_hp' => '081234567890',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'peran' => 'admin',
        ]);

        $response->assertRedirect(route('pelanggan.katalog'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('pengguna', [
            'email' => 'baru@ruangmain.test',
            'peran' => Pengguna::PERAN_PELANGGAN,
            'status_akun' => Pengguna::STATUS_AKTIF,
        ]);
        $this->assertTrue(Hash::check('Password123!', Pengguna::query()->where('email', 'baru@ruangmain.test')->value('password')));
    }

    public function test_registrasi_dengan_email_sama_ditolak(): void
    {
        Pengguna::factory()->create(['email' => 'sama@ruangmain.test']);

        $response = $this->from(route('register'))->post(route('register.store'), [
            'nama' => 'Pelanggan Lain',
            'email' => 'sama@ruangmain.test',
            'no_hp' => '081234567890',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors('email');
    }

    public function test_login_pelanggan_mengarahkan_ke_katalog(): void
    {
        $pengguna = Pengguna::factory()->create([
            'email' => 'pelanggan@ruangmain.test',
            'password' => Hash::make('Password123!'),
        ]);

        $response = $this->post(route('login.store'), [
            'email' => $pengguna->email,
            'password' => 'Password123!',
        ]);

        $response->assertRedirect(route('pelanggan.katalog'));
        $this->assertAuthenticatedAs($pengguna);
    }

    public function test_akun_nonaktif_ditolak_saat_login(): void
    {
        Pengguna::factory()->create([
            'email' => 'nonaktif@ruangmain.test',
            'password' => Hash::make('Password123!'),
            'status_akun' => Pengguna::STATUS_NONAKTIF,
        ]);

        $response = $this->from(route('login'))->post(route('login.store'), [
            'email' => 'nonaktif@ruangmain.test',
            'password' => 'Password123!',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_enam_percobaan_login_salah_dibatasi(): void
    {
        foreach (range(1, 5) as $percobaan) {
            $this->post(route('login.store'), [
                'email' => 'tidakada@ruangmain.test',
                'password' => 'PasswordSalah!',
            ]);
        }

        $response = $this->post(route('login.store'), [
            'email' => 'tidakada@ruangmain.test',
            'password' => 'PasswordSalah!',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString('Terlalu banyak percobaan', session('errors')->first('email'));
    }

    public function test_pelanggan_ditolak_dari_area_admin(): void
    {
        $pelanggan = Pengguna::factory()->create(['peran' => Pengguna::PERAN_PELANGGAN]);

        $this->actingAs($pelanggan)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_pelanggan_dapat_memperbarui_profil_dan_password(): void
    {
        $pelanggan = Pengguna::factory()->create(['password' => Hash::make('PasswordLama!')]);

        $this->actingAs($pelanggan)->put(route('profil.update'), [
            'nama' => 'Nama Diperbarui',
            'email' => 'profil@ruangmain.test',
            'no_hp' => '081298765432',
        ])->assertSessionHasNoErrors()->assertRedirect(route('profil.edit'));

        $this->actingAs($pelanggan)->put(route('profil.password.update'), [
            'password_lama' => 'PasswordLama!',
            'password' => 'PasswordBaru!',
            'password_confirmation' => 'PasswordBaru!',
        ])->assertSessionHasNoErrors()->assertRedirect(route('profil.edit'));

        $pelanggan->refresh();
        $this->assertSame('Nama Diperbarui', $pelanggan->nama);
        $this->assertTrue(Hash::check('PasswordBaru!', $pelanggan->password));
    }

    public function test_logout_menghapus_sesi_login(): void
    {
        $pelanggan = Pengguna::factory()->create();

        $response = $this->actingAs($pelanggan)->post(route('logout'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_beranda_menyesuaikan_tautan_untuk_pengguna_login(): void
    {
        $this->get(route('beranda'))
            ->assertOk()
            ->assertSee(route('login'), false)
            ->assertSee(route('register'), false);

        $this->actingAs(Pengguna::factory()->create())
            ->get(route('beranda'))
            ->assertOk()
            ->assertDontSee('href="'.route('login').'"', false)
            ->assertDontSee('href="'.route('register').'"', false)
            ->assertSee(route('pelanggan.katalog'), false)
            ->assertSee(route('logout'), false);

        $this->actingAs(Pengguna::factory()->create(['peran' => Pengguna::PERAN_ADMIN]))
            ->get(route('beranda'))
            ->assertOk()
            ->assertDontSee('href="'.route('login').'"', false)
            ->assertSee(route('admin.dashboard'), false);
    }
}
