<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\StorefrontTemplate;
use App\Services\StoreProvisionService;
use App\Support\Username;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function __construct(private readonly StoreProvisionService $stores) {}

    public function index(Request $request): Response|RedirectResponse
    {
        if ($request->user()->store) {
            return redirect()->route('creator.dashboard');
        }

        return Inertia::render('Onboarding/Index', [
            'suggestedUsername' => $request->user()->username,
            'selectedTemplate' => $request->session()->pull('onboarding_template'),
            'templates' => StorefrontTemplate::where('is_active', true)
                ->orderBy('sort_order')
                ->get()
                ->map(fn ($t) => [
                    'slug' => $t->slug,
                    'name' => $t->name,
                    'tagline' => $t->tagline,
                    'use_case' => $t->use_case,
                    'is_premium' => (bool) $t->is_premium,
                    'theme' => $t->theme,
                    'blocks' => collect($t->blueprint ?? [])->pluck('type'),
                ]),
            'goals' => [
                ['key' => 'digital', 'label' => 'Jual produk digital', 'description' => 'E-book, template, preset, audio'],
                ['key' => 'service', 'label' => 'Buka jasa', 'description' => 'Konsultasi, desain, coaching'],
                ['key' => 'course', 'label' => 'Jual kelas online', 'description' => 'Video, modul, sertifikat'],
                ['key' => 'physical', 'label' => 'Jual produk fisik', 'description' => 'Fashion, F&B, merch'],
                ['key' => 'affiliate', 'label' => 'Jadi afiliator', 'description' => 'Promosiin produk orang lain'],
            ],
            'niches' => ['Content Creator', 'Desain & Kreatif', 'Edukasi', 'Fashion', 'Food & Beverage',
                'Kesehatan & Fitness', 'Keuangan', 'Teknologi', 'Fotografi', 'Musik', 'Lainnya'],
        ]);
    }

    public function store(Request $request)
    {
        abort_if($request->user()->store, 409, 'Kamu sudah punya toko.');

        $data = $request->validate([
            'goal' => ['required', 'string', 'max:40'],
            'niche' => ['nullable', 'string', 'max:80'],
            'template' => ['nullable', 'string', 'exists:storefront_templates,slug'],
            'store_name' => ['required', 'string', 'max:120'],
            'username' => [
                'required', 'string', 'min:3', 'max:30',
                'regex:'.Username::PATTERN,
                Rule::unique('stores', 'username'),
                Rule::unique('users', 'username')->ignore($request->user()->id),
            ],
            'tagline' => ['nullable', 'string', 'max:160'],
            'bio' => ['nullable', 'string', 'max:500'],
            'whatsapp' => ['nullable', 'string', 'max:32'],
            'socials' => ['nullable', 'array'],
            'publish' => ['boolean'],
        ]);

        abort_if(Username::isReserved($data['username']), 422, 'Username ini dipakai sistem JualanYok.');

        $template = $data['template']
            ? StorefrontTemplate::where('slug', $data['template'])->first()
            : null;

        $user = $request->user();

        $store = $this->stores->create($user, [
            'username' => Username::normalize($data['username']),
            'name' => $data['store_name'],
            'tagline' => $data['tagline'] ?? null,
            'bio' => $data['bio'] ?? null,
            'whatsapp' => $data['whatsapp'] ?? null,
            'socials' => $data['socials'] ?? [],
        ], $template);

        $user->profile()->updateOrCreate([], [
            'goal' => $data['goal'],
            'niche' => $data['niche'] ?? null,
            'onboarding_state' => ['completed_at' => now()->toIso8601String()],
        ]);

        $user->roles()->syncWithoutDetaching([
            Role::where('slug', Role::CREATOR)->value('id'),
        ]);

        if ($data['goal'] === 'affiliate') {
            $user->forceFill(['is_affiliate' => true])->save();
            $user->roles()->syncWithoutDetaching([Role::where('slug', Role::AFFILIATE)->value('id')]);
        }

        // A new store deliberately starts as a draft. The creator first adds a
        // real product, checks the exact storefront preview, then publishes.
        return redirect()->route('creator.products.create', ['first' => 1])
            ->with('success', 'Template sudah dipasang. Sekarang buat produk pertamamu.');
    }
}
