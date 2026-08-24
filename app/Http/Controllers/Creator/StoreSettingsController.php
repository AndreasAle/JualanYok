<?php

namespace App\Http\Controllers\Creator;

use App\Http\Controllers\Controller;
use App\Services\PlanService;
use App\Support\Username;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StoreSettingsController extends Controller
{
    public function __construct(private readonly PlanService $plans) {}

    public function index(Request $request): Response
    {
        $store = $request->user()->store->load(['theme', 'domains']);
        $user = $request->user();

        return Inertia::render('Creator/Settings', [
            'store' => $store->only([
                'id', 'username', 'name', 'tagline', 'bio', 'whatsapp',
                'seo_title', 'seo_description', 'show_platform_branding', 'is_published',
            ]) + [
                'avatar_url' => $store->avatarUrl(),
                'cover_url' => $store->coverUrl(),
                'socials' => $store->socials ?? [],
                'public_url' => $store->publicUrl(),
            ],
            'theme' => $store->theme,
            'domains' => $store->domains,
            'account' => [
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'phone' => $user->phone,
                'email_verified' => $user->hasVerifiedEmail(),
            ],
            'permissions' => [
                'custom_domain' => $this->plans->allows($user, PlanService::CUSTOM_DOMAIN),
                'remove_branding' => $this->plans->allows($user, PlanService::REMOVE_BRANDING),
            ],
        ]);
    }

    public function update(Request $request)
    {
        $store = $request->user()->store;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'username' => [
                'required', 'string', 'min:3', 'max:30',
                'regex:'.Username::PATTERN,
                Rule::unique('stores', 'username')->ignore($store->id),
                Rule::unique('users', 'username')->ignore($request->user()->id),
            ],
            'tagline' => ['nullable', 'string', 'max:160'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'whatsapp' => ['nullable', 'string', 'max:32'],
            'socials' => ['nullable', 'array'],
            'socials.*' => ['nullable', 'string', 'max:200'],
            'seo_title' => ['nullable', 'string', 'max:190'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'show_platform_branding' => ['boolean'],
            'avatar' => ['nullable', 'image', 'mimes:'.implode(',', config('jualanyok.uploads.image_mimes')), 'max:'.config('jualanyok.uploads.image_max_kb')],
            'cover' => ['nullable', 'image', 'mimes:'.implode(',', config('jualanyok.uploads.image_mimes')), 'max:'.config('jualanyok.uploads.image_max_kb')],
            'remove_avatar' => ['boolean'],
            'remove_cover' => ['boolean'],
        ]);

        if (Username::isReserved($data['username'])) {
            return back()->withErrors(['username' => 'Username ini dipakai sistem JualanYok.']);
        }

        // Removing the JualanYok badge is a paid feature; the server decides,
        // not the toggle in the UI.
        if (! ($data['show_platform_branding'] ?? true)
            && ! $this->plans->allows($request->user(), PlanService::REMOVE_BRANDING)) {
            $data['show_platform_branding'] = true;
        }

        // Replacing or clearing an image deletes the old file so uploads do not
        // accumulate forever on the disk.
        foreach ([['avatar', 'avatar_path', 'stores/avatars'], ['cover', 'cover_path', 'stores/covers']] as [$field, $column, $directory]) {
            if ($request->hasFile($field)) {
                $this->forgetFile($store->{$column});
                $data[$column] = $request->file($field)->store($directory, 'public');
            } elseif ($request->boolean("remove_{$field}")) {
                $this->forgetFile($store->{$column});
                $data[$column] = null;
            }
        }

        $store->update(
            collect($data)->except(['avatar', 'cover', 'remove_avatar', 'remove_cover'])->all()
        );

        return back()->with('success', 'Pengaturan toko disimpan.');
    }

    /** Deletes a stored upload, ignoring generated demo art that is shared. */
    private function forgetFile(?string $path): void
    {
        if ($path && ! str_starts_with($path, 'demo/')) {
            Storage::disk('public')->delete($path);
        }
    }

    public function updateTheme(Request $request)
    {
        $data = $request->validate([
            'primary_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'background_type' => ['required', Rule::in(['solid', 'gradient', 'image'])],
            'background_value' => ['required', 'string', 'max:255'],
            'font_family' => ['required', Rule::in([
                'jakarta', 'inter', 'poppins', 'nunito', 'space', 'manrope',
                'dm-sans', 'outfit', 'sora', 'playfair', 'lora', 'system',
            ])],
            'button_style' => ['required', Rule::in(['rounded', 'pill', 'square'])],
            'card_style' => ['required', Rule::in(['soft', 'outline', 'flat'])],
            'product_layout' => ['required', Rule::in(['grid', 'list'])],
            'color_scheme' => ['required', Rule::in(['light', 'dark', 'auto'])],
            'extras' => ['sometimes', 'array'],
            'extras.surface_color' => ['required_with:extras', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'extras.badge_background_color' => ['required_with:extras', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'extras.badge_text_color' => ['required_with:extras', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'extras.contact_button_color' => ['required_with:extras', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'extras.spacing' => ['required_with:extras', Rule::in(['compact', 'balanced', 'airy'])],
        ]);

        $request->user()->store->theme()->updateOrCreate([], $data);

        return back()->with('success', 'Tampilan toko diperbarui.');
    }
}
