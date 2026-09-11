<x-layouts.app title="Reports" active="reports">
    <x-page-header :title="'Cash-flow & reports'"
                   subtitle="Outstanding by buyer and the next-30-day receivable picture." />

    <div class="pkg-grid pkg-grid--2">
        <div class="pkg-card">
            <h2 class="pkg-h2">Outstanding by buyer</h2>
            <div class="pkg-buckets">
                @if ($byBuyer === [])
                    <p class="pkg-sub">No outstanding invoices.</p>
                @else
                    @php
                        $axis = max(0.001, max(array_column($byBuyer, 'amount')));
                    @endphp
                    @foreach ($byBuyer as $row)
                        <div class="pkg-bucket">
                            <div class="pkg-bucket-row">
                                <span class="pkg-bucket-name">{{ $row['name'] }}</span>
                                <span class="pkg-bucket-value num">{{ money($row['amount']) }}</span>
                            </div>
                            <div class="pkg-bucket-bar">
                                <span class="pkg-bucket-fill pkg-bucket-fill--info"
                                      style="width:{{ max(2, round($row['amount'] / $axis * 100)) }}%"></span>
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>

        <div class="pkg-card">
            <h2 class="pkg-h2">Summary</h2>
            <ul class="pkg-statlist">
                <li><span>Total outstanding</span><strong class="num">{{ money($summary->total) }}</strong></li>
                <li><span>Overdue</span><strong class="num pkg-neg">{{ money($summary->overdue) }}</strong></li>
                <li><span>Invoices due in 30d</span><strong class="num">{{ money($summary->dueIn30Days) }}</strong></li>
                <li><span>Interest to demand</span><strong class="num pkg-neg">{{ money($summary->interest) }}</strong></li>
            </ul>
            <a class="pkg-btn pkg-btn--link" href="{{ route('treds') }}">Go to finance queue →</a>
        </div>
    </div>

    <div class="pkg-card">
        <div class="pkg-cardhead">
            <h2 class="pkg-h2">Ageing</h2>
            <span class="pkg-filter" style="padding:.35rem .6rem;">{{ plural($summary->invoiceCount, 'invoice') }} open</span>
        </div>
        <ul class="pkg-pipe-list">
            @foreach ($summary->buckets as $bucket)
                <li class="pkg-pipe-row">
                    <span class="pkg-pipe-name">{{ $bucket['bucket']->label() }}</span>
                    <span class="pkg-pipe-count">{{ plural($bucket['count'], 'invoice') }}</span>
                    <span class="pkg-pipe-right">
                        <span class="pkg-pipe-amt num">{{ money($bucket['amount']) }}</span>
                        <span class="pkg-pipe-pct pkg-badge--{{ $bucket['bucket']->badgeTone() }}">{{ $bucket['percent'] }}%</span>
                    </span>
                </li>
            @endforeach
        </ul>
        <div class="pkg-pipe-total">
            <span>Total</span>
            <span class="num">{{ money($summary->total) }}</span>
        </div>
    </div>
</x-layouts.app>
