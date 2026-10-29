<form method="GET" class="sf-noprint p-6 border-b border-slate-200 flex flex-col sm:flex-row gap-2 sm:items-end">
    <div><label class="block text-xs text-slate-500 mb-1">Dari</label><input type="date" name="start_date" value="{{ $start->toDateString() }}" class="rounded-lg border-slate-300 text-sm"></div>
    <div><label class="block text-xs text-slate-500 mb-1">Sampai</label><input type="date" name="end_date" value="{{ $end->toDateString() }}" class="rounded-lg border-slate-300 text-sm"></div>
    @isset($extra){{ $extra }}@endisset
    <button class="px-4 py-2 bg-slate-800 text-white text-sm rounded-lg">Terapkan</button>
    <button type="button" onclick="window.print()" class="no-loading sf-btn sf-btn-ghost sf-btn-sm" style="min-height:40px"><x-icon name="printer" :size="16" /> Cetak</button>
</form>
@error('end_date')<p class="px-6 pt-3 text-sm text-red-600">{{ $message }}</p>@enderror
