<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>Cetak Resi {{ $shipment->waybill_id }} — JualanYok</title>
    <style>
        :root { color-scheme: light; font-family: Arial, Helvetica, sans-serif; color: #111827; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #eef0f6; }
        .toolbar { position: sticky; top: 0; z-index: 10; display: flex; justify-content: center; gap: 10px; padding: 12px; background: #171622; }
        .toolbar button, .toolbar a { border: 0; border-radius: 9px; padding: 10px 16px; font: 700 13px Arial, sans-serif; text-decoration: none; cursor: pointer; }
        .toolbar button { color: white; background: linear-gradient(100deg, #7c3aed, #ef6461); }
        .toolbar a { color: #24202f; background: white; }
        .sheet { width: 100mm; min-height: 150mm; margin: 18px auto; background: white; border: 1px solid #111; }
        .header { display: grid; grid-template-columns: 1fr auto; align-items: center; gap: 8px; padding: 5mm; border-bottom: 1px solid #111; }
        .brand { width: 42mm; height: auto; display: block; }
        .courier { text-align: right; }
        .courier strong { display: block; font-size: 19px; line-height: 1; letter-spacing: .03em; }
        .courier span { display: block; margin-top: 4px; font-size: 9px; font-weight: 700; }
        .badges { display: flex; justify-content: space-between; align-items: center; gap: 8px; padding: 2.5mm 5mm; border-bottom: 1px solid #111; font-size: 10px; font-weight: 800; }
        .badge { padding: 3px 7px; border: 1px solid #111; border-radius: 999px; }
        .barcode-wrap { padding: 4mm 6mm 3mm; text-align: center; border-bottom: 1px solid #111; }
        .barcode { height: 20mm; }
        .waybill { margin-top: 2mm; font-size: 15px; font-weight: 900; letter-spacing: .04em; }
        .route { margin-top: 1mm; font-size: 9px; font-weight: 700; }
        .grid { display: grid; grid-template-columns: 1fr 1.35fr; border-bottom: 1px solid #111; }
        .cell { min-width: 0; padding: 3mm 4mm; font-size: 9px; line-height: 1.35; }
        .cell + .cell { border-left: 1px solid #111; }
        .label { margin-bottom: 1.5mm; font-size: 7px; font-weight: 800; text-transform: uppercase; letter-spacing: .08em; color: #5b6472; }
        .person { font-size: 11px; font-weight: 900; }
        .phone { margin: 1mm 0; font-weight: 800; }
        .package { padding: 3mm 4mm; border-bottom: 1px solid #111; }
        .package-head { display: flex; justify-content: space-between; gap: 8px; margin-bottom: 2mm; font-size: 8px; font-weight: 800; }
        .items { width: 100%; border-collapse: collapse; font-size: 8px; }
        .items th { color: #5b6472; text-align: left; text-transform: uppercase; letter-spacing: .05em; }
        .items th, .items td { padding: 1.2mm 1mm; border-top: 1px solid #d1d5db; vertical-align: top; }
        .items .qty { width: 10mm; text-align: center; }
        .items .detail { width: 31mm; }
        .note { padding: 2.5mm 4mm; border-bottom: 1px solid #111; font-size: 8px; }
        .footer { display: grid; grid-template-columns: 1fr 24mm; align-items: center; gap: 3mm; padding: 3mm 4mm; }
        .footer h2 { margin: 0 0 1.5mm; font-size: 10px; }
        .footer p { margin: .8mm 0; font-size: 7.5px; line-height: 1.35; }
        .tracking-code { font-family: Consolas, monospace; font-size: 8px; font-weight: 800; }
        .qr { width: 22mm; height: 22mm; display: block; }
        .disclaimer { color: #5b6472; }
        @page { size: 100mm 150mm; margin: 0; }
        @media print {
            body { background: white; }
            .toolbar { display: none; }
            .sheet { width: 100mm; min-height: 150mm; margin: 0; border: 1px solid #111; break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ route('creator.orders.show', $order) }}">Kembali</a>
        <button type="button" onclick="window.print()">Cetak / Simpan PDF</button>
    </div>

    <main class="sheet" aria-label="Label pengiriman {{ $shipment->waybill_id }}">
        <header class="header">
            <img class="brand" src="{{ asset('images/jualanyok-logo.svg') }}" alt="JualanYok">
            <div class="courier">
                <strong>{{ $courier }}</strong>
                <span>{{ $service }}</span>
            </div>
        </header>

        <section class="badges">
            <span class="badge">LUNAS · NON-COD</span>
            <span>Ongkir {{ $shippingCost }}</span>
            <span>{{ number_format($totalWeight / 1000, 2, ',', '.') }} kg</span>
        </section>

        <section class="barcode-wrap">
            <div class="barcode">{!! $barcode !!}</div>
            <div class="waybill">{{ $shipment->waybill_id }}</div>
            <div class="route">
                Ref. {{ $order->number }}
                @if($routingCode) · Routing {{ $routingCode }} @endif
            </div>
        </section>

        <section class="grid">
            <div class="cell">
                <div class="label">Pengirim</div>
                <div class="person">{{ $sender['name'] }}</div>
                <div class="phone">{{ $sender['phone'] }}</div>
                <div>{{ $sender['address'] }}{{ $sender['postal_code'] && !str_contains($sender['address'], $sender['postal_code']) ? ', '.$sender['postal_code'] : '' }}</div>
            </div>
            <div class="cell">
                <div class="label">Penerima</div>
                <div class="person">{{ $recipient['name'] }}</div>
                <div class="phone">{{ $recipient['phone'] }}</div>
                <div>
                    {{ collect([$recipient['address'], $recipient['district'], $recipient['city'], $recipient['province'], $recipient['postal_code']])->filter()->implode(', ') }}
                </div>
            </div>
        </section>

        <section class="package">
            <div class="package-head">
                <span>ISI PAKET</span>
                <span>{{ $items->sum('quantity') }} barang</span>
            </div>
            <table class="items">
                <thead><tr><th>Barang</th><th class="qty">Qty</th><th class="detail">Berat · Dimensi</th></tr></thead>
                <tbody>
                    @foreach($items->take(4) as $item)
                        <tr>
                            <td>{{ $item['name'] }}</td>
                            <td class="qty">{{ $item['quantity'] }}</td>
                            <td class="detail">
                                {{ number_format($item['weight'] / 1000, 2, ',', '.') }} kg
                                @if($item['length'] && $item['width'] && $item['height'])
                                    · {{ $item['length'] }}×{{ $item['width'] }}×{{ $item['height'] }} cm
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    @if($items->count() > 4)
                        <tr><td colspan="3"><strong>+ {{ $items->count() - 4 }} jenis barang lainnya</strong></td></tr>
                    @endif
                </tbody>
            </table>
        </section>

        @if($note)
            <section class="note"><strong>Catatan kurir:</strong> {{ $note }}</section>
        @endif

        <footer class="footer">
            <div>
                <h2>Lacak paket melalui JualanYok</h2>
                <p class="tracking-code">{{ $order->tracking_code }}</p>
                <p>Scan QR untuk melihat perjalanan paket dan status terbaru.</p>
                <p class="disclaimer">Pengiriman dipesan melalui Biteship. Jangan melipat atau menutup area barcode.</p>
            </div>
            <img class="qr" src="{{ $trackingQr }}" alt="QR pelacakan pesanan">
        </footer>
    </main>
</body>
</html>
