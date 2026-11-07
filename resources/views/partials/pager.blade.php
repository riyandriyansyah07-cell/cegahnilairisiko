@if ($paginator->hasPages())
    <nav class="mt-6 flex items-center justify-between text-sm" aria-label="Halaman">
        <p class="text-ink-2 num">Halaman {{ $paginator->currentPage() }} dari {{ $paginator->lastPage() }}</p>
        <div class="flex gap-2">
            @if ($paginator->onFirstPage())
                <span class="btn btn-quiet btn-sm pointer-events-none opacity-40">Sebelumnya</span>
            @else
                <a class="btn btn-quiet btn-sm" href="{{ $paginator->previousPageUrl() }}">Sebelumnya</a>
            @endif
            @if ($paginator->hasMorePages())
                <a class="btn btn-quiet btn-sm" href="{{ $paginator->nextPageUrl() }}">Berikutnya</a>
            @else
                <span class="btn btn-quiet btn-sm pointer-events-none opacity-40">Berikutnya</span>
            @endif
        </div>
    </nav>
@endif
