<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * The in-app guided tours.
 *
 * A creator who has just signed up is looking at a sidebar with twenty links
 * and no idea which three of them matter today. The signup wizard cannot fix
 * that — it runs before they have ever seen the workspace. So the explanation
 * has to happen in the workspace, pointing at the real controls.
 *
 * The definitions live on the server rather than in the bundle for two
 * reasons. Progress is written back per user, so the tour id arriving from the
 * browser has to be checked against a list the browser did not author. And the
 * copy is the product's own explanation of itself — it belongs next to the
 * routes it describes, where it will be noticed when those routes change.
 *
 * Each step points at a `data-tour` attribute on a real element. A step whose
 * target is missing is skipped by the client rather than drawn against
 * nothing, so a tour survives a screen it no longer perfectly describes.
 */
class Tours
{
    /**
     * Tours keyed by id, in the order a creator meets them.
     *
     * `route` is the route name the tour belongs to. `steps[].target` is the
     * `data-tour` value; a step with no target is shown centred, which is what
     * the opening and closing cards want.
     */
    public static function all(): array
    {
        return [
            'creator-dashboard' => [
                'route' => 'creator.dashboard',
                'title' => 'Keliling dashboard',
                'steps' => [
                    [
                        'target' => null,
                        'title' => 'Selamat datang di workspace kamu',
                        'body' => 'Sebentar aja — kenalan dulu sama menu yang paling sering kamu pakai. '
                            .'Bisa dilewati kapan aja, dan bisa diputar ulang lewat tombol tanda tanya di atas.',
                    ],
                    [
                        'target' => 'sidebar',
                        'title' => 'Menu dikelompokkan per pekerjaan',
                        'body' => 'Toko untuk mengatur etalase, Penjualan untuk pesanan yang masuk, '
                            .'Tumbuh untuk promo dan analitik, Uang untuk saldo dan pencairan.',
                        'placement' => 'right',
                    ],
                    [
                        'target' => 'store-link',
                        'title' => 'Ini alamat toko kamu',
                        'body' => 'Satu link yang kamu bagikan ke followers. Selama statusnya masih Draft, '
                            .'link ini belum bisa dibuka orang lain.',
                        'placement' => 'bottom',
                    ],
                    [
                        'target' => 'stats',
                        'title' => 'Angka penting di satu baris',
                        'body' => 'Penjualan kotor adalah total yang dibayar pembeli. Pendapatan bersih adalah '
                            .'bagian kamu setelah biaya platform — angka inilah yang masuk ke saldo.',
                        'placement' => 'bottom',
                    ],
                    [
                        'target' => 'quick-actions',
                        'title' => 'Empat pekerjaan utama',
                        'body' => 'Tambah produk, atur tampilan toko, bikin kupon promo, dan lihat performa. '
                            .'Semuanya bisa diakses dari sini tanpa mencari di menu.',
                        'placement' => 'bottom',
                    ],
                    [
                        'target' => 'wallet',
                        'title' => 'Uang kamu ada di sini',
                        'body' => 'Hasil penjualan masuk sebagai saldo tertahan dulu, lalu otomatis pindah ke '
                            .'saldo siap dicairkan setelah masa refund lewat. Dari situ baru bisa ditarik ke rekening.',
                        'placement' => 'top',
                    ],
                    [
                        'target' => 'checklist',
                        'title' => 'Mulai dari sini',
                        'body' => 'Selesaikan daftar ini sampai penuh dan tokomu siap menerima pesanan pertama. '
                            .'Tiap barisnya langsung membawa kamu ke halaman yang tepat.',
                        'placement' => 'top',
                    ],
                ],
            ],

            'creator-builder' => [
                'route' => 'creator.builder',
                'title' => 'Cara menyusun tampilan toko',
                'steps' => [
                    [
                        'target' => null,
                        'title' => 'Toko kamu disusun dari block',
                        'body' => 'Tiap bagian halaman — header, daftar produk, testimoni, FAQ — adalah satu block '
                            .'yang bisa ditambah, diatur ulang, atau dihapus.',
                    ],
                    [
                        'target' => 'block-list',
                        'title' => 'Urutan block = urutan halaman',
                        'body' => 'Yang paling atas di daftar ini tampil paling atas di toko. Geser untuk menukar '
                            .'urutannya.',
                        'placement' => 'right',
                    ],
                    [
                        'target' => 'add-block',
                        'title' => 'Tambah bagian baru',
                        'body' => 'Pilih dari koleksi block: galeri, carousel, statistik, logo brand, sebelum-sesudah, '
                            .'dan lainnya.',
                        'placement' => 'bottom',
                    ],
                    [
                        'target' => 'preview',
                        'title' => 'Pratinjau yang jujur',
                        'body' => 'Yang kamu lihat di sini persis seperti yang dilihat pembeli, termasuk di layar HP.',
                        'placement' => 'left',
                    ],
                    [
                        'target' => 'publish',
                        'title' => 'Terbitkan kalau sudah siap',
                        'body' => 'Selama belum diterbitkan, toko masih draft dan hanya kamu yang bisa melihatnya.',
                        'placement' => 'bottom',
                    ],
                ],
            ],

            'creator-product' => [
                'route' => 'creator.products.create',
                'title' => 'Membuat produk pertama',
                'steps' => [
                    [
                        'target' => null,
                        'title' => 'Empat jenis produk',
                        'body' => 'Digital (file yang otomatis terkirim), fisik (dikirim pakai kurir), jasa, '
                            .'dan affiliate. Jenisnya menentukan apa yang diminta di form ini.',
                    ],
                    [
                        'target' => 'product-type',
                        'title' => 'Pilih jenisnya dulu',
                        'body' => 'Ini yang paling menentukan. Produk digital butuh file, produk fisik butuh berat '
                            .'dan ongkir, jasa tidak butuh keduanya.',
                        'placement' => 'bottom',
                    ],
                    [
                        'target' => 'product-price',
                        'title' => 'Form-nya dibagi per tab',
                        'body' => 'Harga & Stok, SEO, dan pengaturan lanjutan ada di tab masing-masing. '
                            .'Untuk produk digital, tab File baru muncul setelah produknya tersimpan.',
                        'placement' => 'bottom',
                    ],
                    [
                        'target' => 'product-publish',
                        'title' => 'Aktif atau simpan dulu',
                        'body' => 'Produk yang belum aktif tidak muncul di toko. Produk digital juga wajib punya '
                            .'minimal satu file sebelum bisa diaktifkan — supaya pembeli tidak pernah membayar '
                            .'sesuatu yang kosong.',
                        'placement' => 'top',
                    ],
                ],
            ],
        ];
    }

    public static function has(string $id): bool
    {
        return array_key_exists($id, self::all());
    }

    /** The tour attached to a route name, if any. */
    public static function forRoute(?string $routeName): ?string
    {
        if ($routeName === null) {
            return null;
        }

        foreach (self::all() as $id => $tour) {
            if ($tour['route'] === $routeName) {
                return $id;
            }
        }

        return null;
    }

    /**
     * The tour belonging to this screen, or null.
     *
     * A finished tour is still shared, marked `seen`, so the help button can
     * replay it without another round trip — but it never opens on its own
     * again. Being taught the same thing twice reads as the product having
     * forgotten you.
     */
    public static function forRequest(Request $request, ?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        $id = self::forRoute($request->route()?->getName());

        if ($id === null) {
            return null;
        }

        return self::payload($id) + ['seen' => self::seen($user, $id)];
    }

    /** @return array{id: string, title: string, steps: array<int, array>} */
    public static function payload(string $id): array
    {
        $tour = self::all()[$id];

        return [
            'id' => $id,
            'title' => $tour['title'],
            'steps' => array_map(fn (array $step) => [
                'target' => $step['target'] ?? null,
                'title' => $step['title'],
                'body' => $step['body'],
                'placement' => $step['placement'] ?? 'bottom',
            ], $tour['steps']),
        ];
    }

    public static function seen(User $user, string $id): bool
    {
        $state = $user->profile?->onboarding_state ?? [];

        return isset($state['tours'][$id]['finished_at']);
    }

    /**
     * Records that a tour is done with, whether it was completed or skipped.
     *
     * Both end the tour; the distinction is kept because "skipped at step 2"
     * and "read all seven steps" say very different things about the copy.
     */
    public static function finish(User $user, string $id, string $outcome, ?int $step = null): void
    {
        $profile = $user->profile()->firstOrCreate([]);
        $state = $profile->onboarding_state ?? [];

        $state['tours'][$id] = [
            'finished_at' => now()->toIso8601String(),
            'outcome' => $outcome,
            'step' => $step,
        ];

        $profile->forceFill(['onboarding_state' => $state])->save();
    }

    /** Clears the record so the tour runs again on the next visit. */
    public static function reset(User $user, string $id): void
    {
        $profile = $user->profile()->firstOrCreate([]);
        $state = $profile->onboarding_state ?? [];

        unset($state['tours'][$id]);

        $profile->forceFill(['onboarding_state' => $state])->save();
    }
}
