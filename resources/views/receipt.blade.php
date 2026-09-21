<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="{{ asset('images/icon-192.png') }}">
    <title>Receipt {{ $sale->receipt_number }} &middot; {{ config('app.name') }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 1.5rem 1rem; background: #f4f4f5; color: #111; font: 14px/1.45 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        .sheet { max-width: 22rem; margin: 0 auto; background: #fff; padding: 1.25rem 1.25rem 1rem; border-radius: .5rem; box-shadow: 0 1px 6px rgba(0, 0, 0, .12); }
        .brand { text-align: center; }
        .brand img { width: 100%; max-width: 12rem; background: #000; border-radius: .4rem; }
        .brand h1 { margin: .6rem 0 0; font-size: 1rem; letter-spacing: .08em; text-transform: uppercase; }
        .meta { margin: .9rem 0; padding: .6rem 0; border-top: 1px dashed #999; border-bottom: 1px dashed #999; font-size: .8rem; }
        .meta div { display: flex; justify-content: space-between; gap: 1rem; }
        table { width: 100%; border-collapse: collapse; font-size: .85rem; }
        th { text-align: left; font-size: .7rem; text-transform: uppercase; letter-spacing: .06em; color: #666; padding-bottom: .3rem; border-bottom: 1px solid #ddd; }
        th:last-child, td:last-child { text-align: right; }
        td { padding: .35rem 0; vertical-align: top; }
        td.qty { white-space: nowrap; padding-right: .5rem; color: #555; }
        .total { display: flex; justify-content: space-between; margin-top: .6rem; padding-top: .6rem; border-top: 2px solid #111; font-size: 1.1rem; font-weight: 800; }
        .foot { margin-top: 1rem; text-align: center; font-size: .75rem; color: #666; }
        .void { margin: .8rem 0 0; padding: .5rem; border: 2px solid #b91c1c; color: #b91c1c; text-align: center; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; }
        .actions { max-width: 22rem; margin: 1rem auto 0; display: flex; gap: .5rem; }
        .actions button, .actions a { flex: 1; padding: .65rem; border: 0; border-radius: .5rem; background: #d4af37; color: #000; font: inherit; font-weight: 700; text-align: center; text-decoration: none; cursor: pointer; }
        .actions a { background: #e4e4e7; }
        @media print {
            body { background: #fff; padding: 0; }
            .sheet { box-shadow: none; max-width: none; padding: 0; }
            .actions { display: none; }
        }
    </style>
</head>
<body>
    @php($cur = config('gym.currency'))
    @php($fmt = fn ($n) => $cur.number_format((float) $n, floor((float) $n) == (float) $n ? 0 : 2))

    <div class="sheet">
        <div class="brand">
            <img src="{{ asset('images/logo.jpg') }}" alt="{{ config('app.name') }}">
            <h1>{{ config('app.name') }}</h1>
        </div>

        <div class="meta">
            <div><span>Receipt</span><strong>{{ $sale->receipt_number }}</strong></div>
            <div><span>Date</span><span>{{ $sale->sold_at->format('d M Y, h:i a') }}</span></div>
            <div><span>Customer</span><span>{{ $sale->member?->name ?? 'Walk-in' }}</span></div>
            <div><span>Payment</span><span>{{ strtoupper($sale->method) }}</span></div>
            <div><span>Served by</span><span>{{ $sale->seller?->name ?? '—' }}</span></div>
        </div>

        <table>
            <thead><tr><th>Item</th><th>Qty</th><th>Amount</th></tr></thead>
            <tbody>
                @foreach ($sale->items as $item)
                    <tr>
                        <td>{{ $item->name }}<br><small style="color:#777">{{ $fmt($item->unit_price) }} each</small></td>
                        <td class="qty">× {{ $item->quantity }}</td>
                        <td>{{ $fmt($item->unit_price * $item->quantity) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="total"><span>Total</span><span>{{ $fmt($sale->total) }}</span></div>

        @if ($sale->isVoided())
            <div class="void">Voided{{ $sale->void_reason ? ' — '.$sale->void_reason : '' }}</div>
        @endif

        <p class="foot">Thank you for training with us!<br>Supplements are non-refundable once opened.</p>
    </div>

    <div class="actions">
        <button type="button" onclick="window.print()">Print receipt</button>
        <a href="javascript:window.close()">Close</a>
    </div>
</body>
</html>
