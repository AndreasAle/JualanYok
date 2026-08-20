<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Username;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UsernameController extends Controller
{
    /** Backs the live availability indicator on the register/onboarding forms. */
    public function check(Request $request): JsonResponse
    {
        $data = $request->validate(['username' => ['required', 'string', 'max:40']]);

        $result = Username::check($data['username'], $request->user()?->id);

        return response()->json($result + [
            'username' => Username::normalize($data['username']),
            'suggestion' => $result['available'] ? null : Username::suggestFrom($data['username']),
        ]);
    }
}
