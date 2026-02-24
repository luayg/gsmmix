@if ($paginator->hasPages())
    @php
        $lastPage = $paginator->lastPage();
        $currentPage = $paginator->currentPage();

        $pages = collect(range(1, min(5, $lastPage)));

        if ($currentPage > 5 && $currentPage < $lastPage) {
            $pages->push($currentPage);
        }

        if ($lastPage > 5) {
            $pages->push($lastPage);
        }

        $pages = $pages->unique()->sort()->values();
        $previousPage = null;
    @endphp

    <nav aria-label="Service pagination">
        <ul class="pagination pagination-sm mb-0 justify-content-center">
            <li class="page-item {{ $paginator->onFirstPage() ? 'disabled' : '' }}">
                <a class="page-link" href="{{ $paginator->previousPageUrl() ?: '#' }}" rel="prev" aria-label="Previous">Previous</a>
            </li>

            @foreach ($pages as $page)
                @if (!is_null($previousPage) && $page - $previousPage > 1)
                    <li class="page-item disabled" aria-disabled="true">
                        <span class="page-link">&hellip;</span>
                    </li>
                @endif

                <li class="page-item {{ $page === $currentPage ? 'active' : '' }}" aria-current="{{ $page === $currentPage ? 'page' : 'false' }}">
                    <a class="page-link" href="{{ $paginator->url($page) }}">{{ $page }}</a>
                </li>

                @php $previousPage = $page; @endphp
            @endforeach

            <li class="page-item {{ $paginator->hasMorePages() ? '' : 'disabled' }}">
                <a class="page-link" href="{{ $paginator->nextPageUrl() ?: '#' }}" rel="next" aria-label="Next">Next</a>
            </li>
        </ul>
    </nav>
@endif