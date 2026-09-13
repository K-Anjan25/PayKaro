@props(['noun' => 'results'])

{{--
    The workspace's own pager, styled to the existing .pkg-pagination markup
    instead of a bundled Tailwind/Bootstrap partial. Wired up in
    AppServiceProvider so every paginator on every page uses it.
--}}
@if ($paginator->hasPages())
    <div class="pkg-pagination">
        <span>
            Showing {{ $paginator->firstItem() }} to {{ $paginator->lastItem() }} of {{ $paginator->total() }} {{ $noun }}
        </span>
        <div class="pkg-pageno">
            @if ($paginator->onFirstPage())
                <span class="pkg-pno" aria-hidden="true">‹</span>
            @else
                <a class="pkg-pno" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous page">‹</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="pkg-pno">{{ $element }}</span>
                @elseif (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="pkg-pno is-active" aria-current="page">{{ $page }}</span>
                        @else
                            <a class="pkg-pno" href="{{ $url }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a class="pkg-pno" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next page">›</a>
            @else
                <span class="pkg-pno" aria-hidden="true">›</span>
            @endif
        </div>
    </div>
@endif
