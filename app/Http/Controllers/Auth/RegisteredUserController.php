<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Support\Username;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('Auth/Register', [
            'googleConfigured' => filled(config('services.google.client_id'))
                && filled(config('services.google.client_secret')),
            'selectedTemplate' => $request->query('template'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'username' => [
                'required', 'string', 'min:3', 'max:30',
                'regex:'.Username::PATTERN,
                Rule::unique('users', 'username'),
                Rule::unique('stores', 'username'),
                function ($attribute, $value, $fail) {
                    if (Username::isReserved($value)) {
                        $fail('Username ini dipakai sistem JualanYok, pilih yang lain ya.');
                    }
                },
            ],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:32'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'terms' => ['accepted'],
            'template' => ['nullable', 'string', 'exists:storefront_templates,slug'],
        ], [
            'terms.accepted' => 'Kamu harus setuju dengan Syarat & Ketentuan.',
        ]);

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'username' => Username::normalize($data['username']),
                'email' => strtolower($data['email']),
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'tos_accepted_at' => now(),
            ]);

            $user->profile()->create(['display_name' => $data['name']]);

            // Everyone starts as a customer; the creator role is granted when
            // onboarding actually creates a store.
            $user->roles()->attach(Role::where('slug', Role::CUSTOMER)->value('id'));

            $user->walletOrCreate();

            return $user;
        });

        event(new Registered($user));

        Auth::login($user, remember: true);

        if (! empty($data['template'])) {
            $request->session()->put('onboarding_template', $data['template']);
        }

        return redirect()->route('onboarding.index');
    }
}
