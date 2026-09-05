<?php

namespace App\Http\Controllers;

use App\Support\Tours;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Progress through the in-app guided tours.
 *
 * The tour id is validated against the server's own registry rather than
 * trusted from the request, so nothing can write an arbitrary key into a
 * user's stored onboarding state.
 */
class TourController extends Controller
{
    public function update(Request $request, string $tour): RedirectResponse
    {
        abort_unless(Tours::has($tour), 404);

        $data = $request->validate([
            'outcome' => ['required', Rule::in(['completed', 'skipped'])],
            'step' => ['nullable', 'integer', 'min:0', 'max:50'],
        ]);

        Tours::finish($request->user(), $tour, $data['outcome'], $data['step'] ?? null);

        return back();
    }

    /** Replays a tour the creator has already been through. */
    public function replay(Request $request, string $tour): RedirectResponse
    {
        abort_unless(Tours::has($tour), 404);

        Tours::reset($request->user(), $tour);

        return back();
    }
}
