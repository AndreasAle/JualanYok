<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\StaticPage;
use App\Models\Store;
use App\Models\StorefrontTemplate;
use App\Models\SupportTicket;
use App\Services\MarketplaceService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LandingController extends Controller
{
    public function __construct(private readonly MarketplaceService $marketplace) {}

    public function home(Request $request): Response
    {
        $this->marketplace->recordEvent($request, 'marketplace_home_view');

        return Inertia::render('Marketing/Home', [
            'plans' => $this->plans(),
            'templates' => $this->templates_data(4),
            'marketplace' => $this->marketplace->homepage(),
            // Real published stores power the showcase, so the landing page
            // never drifts from what the product actually produces.
            'showcase' => Store::live()
                ->with('theme')
                ->withCount('products')
                ->latest('published_at')
                ->limit(3)
                ->get()
                ->map(fn (Store $store) => [
                    'username' => $store->username,
                    'name' => $store->name,
                    'tagline' => $store->tagline,
                    'bio' => $store->bio,
                    'avatar_url' => $store->avatarUrl(),
                    'cover_url' => $store->coverUrl(),
                    'products_count' => $store->products_count,
                    'primary_color' => $store->theme?->primary_color,
                ]),
        ]);
    }

    public function pricing(): Response
    {
        return Inertia::render('Marketing/Pricing', ['plans' => $this->plans()]);
    }

    public function features(): Response
    {
        return Inertia::render('Marketing/Features');
    }

    public function templates(): Response
    {
        return Inertia::render('Marketing/Templates', ['templates' => $this->templates_data()]);
    }

    public function templateDemo(StorefrontTemplate $template): Response
    {
        abort_unless($template->is_active, 404);

        return Inertia::render('Marketing/TemplateDemo', [
            'template' => [
                'slug' => $template->slug,
                'name' => $template->name,
                'tagline' => $template->tagline,
                'description' => $template->description,
                'use_case' => $template->use_case,
                'is_premium' => (bool) $template->is_premium,
                'theme' => $template->theme ?? [],
            ],
        ]);
    }

    public function contact(): Response
    {
        return Inertia::render('Marketing/Contact');
    }

    public function faq(): Response
    {
        return Inertia::render('Marketing/Faq');
    }

    public function submitContact(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'subject' => ['required', 'string', 'max:190'],
            'message' => ['required', 'string', 'max:4000'],
        ]);

        $ticket = SupportTicket::create([
            'number' => SupportTicket::generateNumber(),
            'user_id' => $request->user()?->id,
            'requester_email' => $data['email'],
            'subject' => $data['subject'],
            'category' => 'general',
        ]);

        $ticket->messages()->create([
            'author_name' => $data['name'],
            'body' => $data['message'],
        ]);

        return back()->with('success', "Pesan kamu terkirim! Nomor tiket {$ticket->number}.");
    }

    public function page(string $slug): Response
    {
        $page = StaticPage::where('slug', $slug)->where('is_published', true)->firstOrFail();

        return Inertia::render('Marketing/StaticPage', [
            'page' => $page->only(['slug', 'title', 'body', 'seo_description']),
        ]);
    }

    private function plans(): array
    {
        return Plan::with('features')
            ->where('is_active', true)
            ->where('is_public', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Plan $plan) => [
                'slug' => $plan->slug,
                'name' => $plan->name,
                'tagline' => $plan->tagline,
                'price_monthly' => (float) $plan->price_monthly,
                'price_yearly' => (float) $plan->price_yearly,
                'transaction_fee_percent' => (float) $plan->transaction_fee_percent,
                'trial_days' => $plan->trial_days,
                'highlights' => $plan->highlights ?? [],
                'features' => $plan->features->map(fn ($f) => [
                    'key' => $f->key,
                    'label' => $f->label,
                    'enabled' => (bool) $f->enabled,
                    'limit' => $f->limit,
                ]),
            ])
            ->all();
    }

    private function templates_data(?int $limit = null): array
    {
        return StorefrontTemplate::where('is_active', true)
            ->orderBy('sort_order')
            ->when($limit, fn ($q) => $q->limit($limit))
            ->get()
            ->map(fn (StorefrontTemplate $t) => [
                'slug' => $t->slug,
                'name' => $t->name,
                'tagline' => $t->tagline,
                'description' => $t->description,
                'use_case' => $t->use_case,
                'is_premium' => (bool) $t->is_premium,
                'theme' => $t->theme,
                'block_count' => count($t->blueprint ?? []),
                'blueprint' => collect($t->blueprint ?? [])->pluck('type'),
            ])
            ->all();
    }
}
