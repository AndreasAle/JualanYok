<?php

namespace App\Http\Controllers\Creator;

use App\Http\Controllers\Controller;
use App\Models\IdentityVerification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Submitting an identity check.
 *
 * The most sensitive form in the application. Everything about how it is
 * handled is a consequence of that: the ID number is encrypted before it is
 * stored, the photographs go to a private disk that is never served directly,
 * and consent is recorded with a timestamp rather than assumed from the fact
 * that someone pressed a button.
 */
class IdentityVerificationController extends Controller
{
    /** A KTP photo is a phone photo; nothing here needs to be larger. */
    private const MAX_IMAGE_KB = 5120;

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $existing = IdentityVerification::where('user_id', $user->id)->first();

        // An approved check is not something its subject can quietly replace.
        // Changing it goes through support, so a stolen session cannot redirect
        // someone else's payouts by re-verifying as a different person.
        abort_if($existing?->isApproved(), 422, 'Identitasmu sudah terverifikasi.');

        $data = $request->validate([
            'full_name' => ['required', 'string', 'min:3', 'max:120'],
            // 16 digits, the length of every NIK. Rejected here rather than by
            // a reviewer three days later.
            'nik' => ['required', 'string', 'regex:/^\d{16}$/'],
            'birth_place' => ['required', 'string', 'max:80'],
            'birth_date' => ['required', 'date', 'before:-17 years'],
            'address' => ['required', 'string', 'min:10', 'max:500'],
            'id_card' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:'.self::MAX_IMAGE_KB],
            'selfie' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:'.self::MAX_IMAGE_KB],
            'consent' => ['accepted'],
        ], [
            'nik.regex' => 'NIK terdiri dari 16 angka.',
            'birth_date.before' => 'Penarikan dana hanya untuk usia 17 tahun ke atas.',
            'id_card.mimetypes' => 'Foto KTP harus JPG, PNG, atau WEBP.',
            'selfie.mimetypes' => 'Foto selfie harus JPG, PNG, atau WEBP.',
            'consent.accepted' => 'Centang persetujuan pengelolaan data dulu.',
        ]);

        if ($existing && $existing->status === IdentityVerification::PENDING) {
            throw ValidationException::withMessages([
                'nik' => 'Pengajuanmu masih ditinjau. Tunggu hasilnya dulu ya.',
            ]);
        }

        DB::transaction(function () use ($request, $user, $data, $existing) {
            $existing?->delete();

            IdentityVerification::create([
                'user_id' => $user->id,
                'status' => IdentityVerification::PENDING,
                'full_name' => $data['full_name'],
                'nik' => $data['nik'],
                'nik_last4' => substr($data['nik'], -4),
                'birth_place' => $data['birth_place'],
                'birth_date' => $data['birth_date'],
                'address' => $data['address'],
                /*
                 * A private disk, always. These two files are a photograph of
                 * someone's identity card and their face; a public folder with
                 * a random name would put both one guessed URL away.
                 */
                'id_card_path' => $this->keep($request->file('id_card'), $user->id),
                'selfie_path' => $this->keep($request->file('selfie'), $user->id),
                'consented_at' => now(),
                'consent_ip' => $request->ip(),
            ]);
        });

        return back()->with(
            'success',
            'Data identitasmu terkirim. Biasanya ditinjau dalam 1×24 jam kerja.',
        );
    }

    private function keep(UploadedFile $file, int $userId): string
    {
        // Laravel names the file, so nothing the uploader typed becomes a path.
        return $file->store("kyc/{$userId}", 'local');
    }
}
