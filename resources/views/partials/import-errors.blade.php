@if (session('import_errors'))
    @php $errs = collect(session('import_errors')); @endphp
    <section role="alert" class="rise mb-8 rounded-md border border-bad/40 bg-bad/10 p-5">
        <div class="flex items-start gap-3">
            <x-icon name="warning-circle" class="mt-0.5 text-bad" />
            <div class="min-w-0 flex-1">
                <h2 class="text-lg">Berkas belum disimpan</h2>
                <p class="mt-1 text-ink-2">Ada {{ $errs->count() }} kesalahan. Perbaiki berkasnya lalu unggah lagi. Tidak ada data yang masuk.</p>
                <div class="mt-4 max-h-72 overflow-auto">
                    <table class="tbl">
                        <thead><tr><th class="w-20">Baris</th><th>Masalah</th></tr></thead>
                        <tbody>
                            @foreach ($errs->take(50) as $e)
                                <tr><td class="mono num">{{ $e['row'] }}</td><td>{{ $e['message'] }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                    @if ($errs->count() > 50)
                        <p class="mt-2 text-sm text-ink-2">Menampilkan 50 pertama dari {{ $errs->count() }}.</p>
                    @endif
                </div>
            </div>
        </div>
    </section>
@endif
