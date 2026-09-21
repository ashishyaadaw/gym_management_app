<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="{{ asset('images/icon-192.png') }}">
    <title>Payslip {{ $slip->slip_number }} &middot; {{ config('app.name') }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 1.5rem 1rem; background: #f4f4f5; color: #111; font: 14px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        .sheet { position: relative; max-width: 42rem; margin: 0 auto; background: #fff; padding: 1.75rem; border-radius: .5rem; box-shadow: 0 1px 6px rgba(0, 0, 0, .12); overflow: hidden; }
        .head { display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding-bottom: 1rem; border-bottom: 2px solid #111; }
        .head img { width: 9rem; background: #000; border-radius: .4rem; }
        .head h1 { margin: 0; font-size: 1.2rem; letter-spacing: .06em; text-transform: uppercase; text-align: right; }
        .head small { display: block; color: #666; font-weight: 400; letter-spacing: 0; text-transform: none; margin-top: .2rem; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: .25rem 2rem; margin: 1rem 0; font-size: .85rem; }
        .grid div { display: flex; justify-content: space-between; gap: 1rem; border-bottom: 1px dotted #ccc; padding: .15rem 0; }
        .grid span:first-child { color: #666; }
        h2 { margin: 1.2rem 0 .4rem; font-size: .7rem; letter-spacing: .12em; text-transform: uppercase; color: #666; }
        table { width: 100%; border-collapse: collapse; font-size: .88rem; }
        td { padding: .4rem 0; border-bottom: 1px solid #eee; }
        td:last-child { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        td small { color: #777; }
        .neg { color: #b91c1c; }
        .pos { color: #15803d; }
        .net { display: flex; justify-content: space-between; align-items: baseline; margin-top: .8rem; padding: .8rem 1rem; background: #111; color: #fff; border-radius: .4rem; font-size: 1.15rem; font-weight: 800; }
        .net span:last-child { color: #d4af37; font-size: 1.4rem; }
        .status { margin-top: .9rem; font-size: .82rem; color: #444; }
        .stamp { position: absolute; top: 45%; left: 50%; transform: translate(-50%, -50%) rotate(-20deg); font-size: 4.5rem; font-weight: 900; letter-spacing: .2em; color: rgba(185, 28, 28, .12); pointer-events: none; }
        .sign { display: flex; justify-content: space-between; gap: 2rem; margin-top: 2.2rem; font-size: .75rem; color: #666; }
        .sign div { flex: 1; border-top: 1px solid #999; padding-top: .3rem; text-align: center; }
        .foot { margin-top: 1rem; text-align: center; font-size: .7rem; color: #888; }
        .actions { max-width: 42rem; margin: 1rem auto 0; display: flex; gap: .5rem; }
        .actions button, .actions a { flex: 1; padding: .65rem; border: 0; border-radius: .5rem; background: #d4af37; color: #000; font: inherit; font-weight: 700; text-align: center; text-decoration: none; cursor: pointer; }
        .actions a { background: #e4e4e7; }
        @media (max-width: 34rem) { .grid { grid-template-columns: 1fr; } .head img { width: 6.5rem; } }
        @media print {
            body { background: #fff; padding: 0; }
            .sheet { box-shadow: none; max-width: none; padding: 0; }
            .actions { display: none; }
        }
    </style>
</head>
<body>
    @php($cur = config('gym.currency'))
    @php($fmt = fn ($n) => $cur.number_format((float) $n, 2))
    @php($p = $slip->user->staffProfile)
    @php($hourly = $slip->pay_type === 'hourly')

    <div class="sheet">
        @unless ($slip->isPaid())
            <div class="stamp">DRAFT</div>
        @endunless

        <div class="head">
            <img src="{{ asset('images/logo.jpg') }}" alt="{{ config('app.name') }}">
            <h1>Payslip<small>{{ $slip->month->format('F Y') }}</small></h1>
        </div>

        <div class="grid">
            <div><span>Employee</span><strong>{{ $slip->user->name }}</strong></div>
            <div><span>Payslip no.</span><strong>{{ $slip->slip_number }}</strong></div>
            <div><span>Employee code</span><span>{{ $p?->employee_code ?? '—' }}</span></div>
            <div><span>Designation</span><span>{{ $p?->designation ?? ucfirst($slip->user->role) }}</span></div>
            <div><span>Pay basis</span><span>{{ $hourly ? $fmt($slip->base_salary).' per hour' : $fmt($slip->base_salary).' per month' }}</span></div>
            <div><span>Days in month</span><span>{{ $slip->days_in_month }}</span></div>
        </div>

        <h2>Attendance</h2>
        <div class="grid">
            <div><span>Present</span><span>{{ $slip->present_days }}</span></div>
            <div><span>Half days</span><span>{{ $slip->half_days }}</span></div>
            <div><span>Paid leave</span><span>{{ $slip->leave_days }}</span></div>
            <div><span>Holidays</span><span>{{ $slip->holiday_days }}</span></div>
            <div><span>Weekly offs</span><span>{{ $slip->weekly_off_days }}</span></div>
            <div><span>Absent{{ $slip->unmarked_days ? ' (incl. '.$slip->unmarked_days.' unmarked)' : '' }}</span><span>{{ rtrim(rtrim(number_format($slip->absent_days, 1), '0'), '.') }}</span></div>
            <div><span>Late arrivals</span><span>{{ $slip->late_marks }}</span></div>
            <div><span>{{ $hourly ? 'Hours worked' : 'Payable days' }}</span><strong>{{ $hourly ? floor($slip->worked_minutes / 60).'h '.($slip->worked_minutes % 60).'m' : rtrim(rtrim(number_format($slip->payable_days, 1), '0'), '.') }}</strong></div>
        </div>

        <h2>Earnings &amp; deductions</h2>
        <table>
            <tr>
                <td>
                    {{ $hourly ? 'Hourly pay' : 'Salary for payable days' }}
                    <br><small>{{ $hourly ? floor($slip->worked_minutes / 60).'h '.($slip->worked_minutes % 60).'m × '.$fmt($slip->base_salary) : $fmt($slip->base_salary).' × '.rtrim(rtrim(number_format($slip->payable_days, 1), '0'), '.').' ÷ '.$slip->days_in_month }}</small>
                </td>
                <td>{{ $fmt($slip->gross_pay) }}</td>
            </tr>
            @if ((float) $slip->late_deduction > 0)
                <tr><td>Late deduction <br><small>{{ $slip->late_marks }} late arrival{{ $slip->late_marks === 1 ? '' : 's' }}</small></td><td class="neg">− {{ $fmt($slip->late_deduction) }}</td></tr>
            @endif
            @if ((float) $slip->bonus > 0)
                <tr><td>Bonus / incentive</td><td class="pos">+ {{ $fmt($slip->bonus) }}</td></tr>
            @endif
            @if ((float) $slip->other_deduction > 0)
                <tr><td>Other deduction</td><td class="neg">− {{ $fmt($slip->other_deduction) }}</td></tr>
            @endif
            @if ($slip->adjustment_note)
                <tr><td colspan="2"><small>Note: {{ $slip->adjustment_note }}</small></td></tr>
            @endif
        </table>

        <div class="net"><span>Net pay</span><span>{{ $fmt($slip->net_pay) }}</span></div>

        <div class="status">
            @if ($slip->isPaid())
                <strong>Paid</strong> on {{ $slip->paid_on->format('d M Y') }} by {{ str_replace('_', ' ', $slip->payment_method) }}{{ $slip->payment_reference ? ' (ref '.$slip->payment_reference.')' : '' }}.
            @else
                <strong>Draft</strong> — not paid yet. Figures may still change.
            @endif
        </div>

        <div class="sign"><div>Employee signature</div><div>Authorised signatory</div></div>
        <div class="foot">This is a computer-generated payslip · {{ config('app.name') }}</div>
    </div>

    <div class="actions">
        <button type="button" onclick="window.print()">Print payslip</button>
        <a href="javascript:window.close()">Close</a>
    </div>
</body>
</html>
