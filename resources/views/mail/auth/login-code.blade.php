<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light only">
    <meta name="supported-color-schemes" content="light only">
    <title>Kode masuk JualanYok</title>
    <style>
        @media only screen and (max-width: 640px) {
            .email-shell { padding: 16px 10px 28px !important; }
            .email-card { border-radius: 22px !important; }
            .email-header { padding: 20px !important; }
            .email-body { padding: 30px 22px 26px !important; }
            .email-title { font-size: 28px !important; line-height: 34px !important; }
            .otp-code { font-size: 36px !important; letter-spacing: 8px !important; }
            .otp-panel { padding: 24px 12px !important; }
            .desktop-badge { display: none !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background:#f4f3f8;color:#11121c;font-family:'Segoe UI',Arial,sans-serif;-webkit-text-size-adjust:100%;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">
        Kode verifikasi JualanYok kamu sudah siap dan berlaku {{ $expiresInMinutes }} menit.
    </div>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;background:#f4f3f8;">
        <tr>
            <td class="email-shell" align="center" style="padding:36px 16px 44px;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:600px;">
                    <tr>
                        <td class="email-card" style="overflow:hidden;background:#ffffff;border:1px solid #e7e4ee;border-radius:28px;box-shadow:0 18px 50px rgba(31,24,52,.10);">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td class="email-header" style="padding:24px 30px;background:#11121c;border-top:4px solid #8b3cf5;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                            <tr>
                                                <td valign="middle">
                                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                                                        <tr>
                                                            <td valign="middle" style="width:42px;height:42px;text-align:center;background:#8b3cf5;border-radius:13px;color:#ffffff;font-size:15px;font-weight:800;letter-spacing:-1px;">JY</td>
                                                            <td valign="middle" style="padding-left:12px;color:#ffffff;font-size:20px;font-weight:800;letter-spacing:-.6px;">Jualan<span style="color:#e75caf;">Yok</span></td>
                                                        </tr>
                                                    </table>
                                                </td>
                                                <td class="desktop-badge" align="right" valign="middle">
                                                    <span style="display:inline-block;padding:8px 12px;border:1px solid #353643;border-radius:999px;color:#d8d6e0;font-size:11px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;">Akses aman</span>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                <tr>
                                    <td class="email-body" style="padding:42px 42px 36px;">
                                        <div style="margin-bottom:16px;color:#7c3aed;font-size:12px;font-weight:800;letter-spacing:1.5px;text-transform:uppercase;">Login tanpa password</div>
                                        <h1 class="email-title" style="margin:0 0 14px;color:#11121c;font-size:34px;line-height:41px;font-weight:800;letter-spacing:-1.3px;">Satu langkah lagi untuk masuk.</h1>
                                        <p style="margin:0 0 28px;color:#626272;font-size:16px;line-height:25px;">Masukkan kode berikut di halaman JualanYok. Kode ini khusus untuk kamu dan hanya dapat digunakan satu kali.</p>

                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                            <tr>
                                                <td class="otp-panel" align="center" style="padding:28px 18px;background:#f5f0ff;border:1px solid #dfd2ff;border-radius:20px;">
                                                    <div style="margin-bottom:11px;color:#75688e;font-size:11px;font-weight:800;letter-spacing:1.8px;text-transform:uppercase;">Kode verifikasi</div>
                                                    <div class="otp-code" style="color:#5120b5;font-family:Consolas,'Courier New',monospace;font-size:44px;line-height:52px;font-weight:800;letter-spacing:12px;white-space:nowrap;">{{ $code }}</div>
                                                </td>
                                            </tr>
                                        </table>

                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin-top:18px;">
                                            <tr>
                                                <td valign="top" style="width:36px;">
                                                    <div style="width:30px;height:30px;line-height:30px;text-align:center;background:#fff2e9;border-radius:9px;color:#e0522d;font-size:15px;font-weight:800;">⏱</div>
                                                </td>
                                                <td style="padding:3px 0 0 4px;color:#545464;font-size:13px;line-height:20px;">
                                                    Kode berlaku selama <strong style="color:#11121c;">{{ $expiresInMinutes }} menit</strong>. Setelah itu, minta kode baru dari halaman login.
                                                </td>
                                            </tr>
                                        </table>

                                        <div style="height:1px;margin:30px 0;background:#eceaf1;"></div>

                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                            <tr>
                                                <td style="padding:18px 20px;background:#f8f8fa;border-radius:16px;">
                                                    <div style="margin-bottom:5px;color:#242530;font-size:13px;font-weight:800;">Bukan kamu yang meminta kode?</div>
                                                    <div style="color:#747482;font-size:12px;line-height:19px;">Abaikan email ini. Jangan pernah membagikan kode masuk kepada siapa pun, termasuk pihak yang mengaku dari JualanYok.</div>
                                                </td>
                                            </tr>
                                        </table>

                                        <p style="margin:24px 0 0;color:#777684;font-size:12px;line-height:19px;">Email otomatis ini dikirim untuk melindungi akses akunmu. Kamu tidak perlu membalasnya.</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding:24px 20px 0;color:#8b8996;font-size:11px;line-height:18px;">
                            <strong style="color:#555461;">JualanYok</strong> · Etalase digital untuk kreator Indonesia<br>
                            © {{ $year }} JualanYok. Seluruh hak dilindungi.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
