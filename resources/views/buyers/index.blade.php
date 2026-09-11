<x-layouts.app title="Buyers" active="buyers">
    <x-page-header title="Buyers" subtitle="Who owes you money.">
        @can('create', App\Models\Buyer::class)
            <a class="pkg-btn pkg-btn--primary" href="{{ route('buyers.create') }}">+ Add buyer</a>
        @endcan
    </x-page-header>

    @if ($buyers->isEmpty())
        <div class="pkg-card pkg-empty">
            <p class="pkg-sub">No buyers yet.</p>
            @can('create', App\Models\Buyer::class)
                <a class="pkg-btn pkg-btn--primary" href="{{ route('buyers.create') }}">+ Add buyer</a>
            @endcan
        </div>
    @else
        <div class="pkg-card pkg-tablewrap">
            <div class="pkg-cardhead">
                <h2 class="pkg-h2">{{ plural($buyers->count(), 'buyer') }}</h2>
                <span class="pkg-filter" style="padding:.35rem .6rem;">TReDS status decides what you can finance</span>
            </div>
            <table class="pkg-table">
                <thead>
                <tr>
                    <th>Buyer</th>
                    <th>GSTIN</th>
                    <th>Type</th>
                    <th>TReDS</th>
                    <th>Outstanding</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($buyers as $buyer)
                    <tr>
                        <td><strong>{{ $buyer->name }}</strong></td>
                        <td class="num">{{ $buyer->gstin ?: '—' }}</td>
                        <td>{{ $buyer->type->label() }}</td>
                        <td><x-treds-badge :treds="$buyer->treds_onboarded" /></td>
                        <td>
                            <strong class="num">{{ money($outstanding[$buyer->id] ?? 0) }}</strong>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-layouts.app>
