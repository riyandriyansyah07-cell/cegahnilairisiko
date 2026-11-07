@if (session('status'))
    <div role="status" class="rise mb-8 flex items-start gap-3 rounded-md border border-ok/40 bg-ok/10 px-4 py-3 text-ink">
        <x-icon name="check-circle" class="mt-0.5 text-ok" />
        <p>{{ session('status') }}</p>
    </div>
@endif
@if ($errors->has('activate') || $errors->has('is_active'))
    <div role="alert" class="mb-8 flex items-start gap-3 rounded-md border border-bad/40 bg-bad/10 px-4 py-3 text-ink">
        <x-icon name="warning-circle" class="mt-0.5 text-bad" />
        <p>{{ $errors->first('activate') ?: $errors->first('is_active') }}</p>
    </div>
@endif
