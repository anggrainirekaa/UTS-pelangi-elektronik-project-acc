@extends('layouts.app')
@section('title', 'Pengguna & Role')
@section('content')
    <x-card title="Pengguna & Role" subtitle="Kelola akun Owner, Staff, dan Pelanggan.">
        <x-slot:actions><a href="{{ route('pengguna.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700"><span>+</span> Tambah Pengguna</a></x-slot:actions>
        <div class="p-6 border-b border-slate-200">
            <form method="GET" class="flex flex-col sm:flex-row gap-2">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama atau email..." class="flex-1 rounded-lg border-slate-300 text-sm">
                <select name="role" class="rounded-lg border-slate-300 text-sm">
                    <option value="">Semua Role</option>
                    @foreach(['owner' => 'Owner', 'staff' => 'Staff', 'pelanggan' => 'Pelanggan'] as $v => $l)<option value="{{ $v }}" @selected(request('role') === $v)>{{ $l }}</option>@endforeach
                </select>
                <button class="px-4 py-2 bg-slate-800 text-white text-sm rounded-lg">Cari</button>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-slate-50 text-slate-600 uppercase text-xs"><tr><th class="px-6 py-3">Nama</th><th class="px-6 py-3">Email</th><th class="px-6 py-3">Role</th><th class="px-6 py-3">Telepon</th><th class="px-6 py-3 text-right">Aksi</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($users as $u)
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-4 font-medium text-slate-800">{{ $u->name }}</td>
                            <td class="px-6 py-4 text-slate-500">{{ $u->email }}</td>
                            <td class="px-6 py-4"><span class="px-2.5 py-1 rounded-full text-xs font-medium {{ $u->isManagement() ? 'bg-emerald-100 text-emerald-700' : ($u->isStaff() ? 'bg-indigo-100 text-indigo-700' : 'bg-sky-100 text-sky-700') }}">{{ $u->roleLabel() }}</span></td>
                            <td class="px-6 py-4 text-slate-500">{{ $u->phone ?: '-' }}</td>
                            <td class="px-6 py-4">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('pengguna.edit', $u) }}" class="px-3 py-1.5 bg-amber-50 text-amber-700 text-xs font-medium rounded-lg hover:bg-amber-100">Edit</a>
                                    @if($u->id !== auth()->id())
                                        <form method="POST" action="{{ route('pengguna.destroy', $u) }}" onsubmit="return confirm('Hapus pengguna ini?');">@csrf @method('DELETE')
                                            <button class="px-3 py-1.5 bg-red-50 text-red-700 text-xs font-medium rounded-lg hover:bg-red-100">Hapus</button></form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-8 text-center text-slate-400">Tidak ada pengguna.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($users->hasPages())<div class="px-6 py-4 border-t border-slate-200">{{ $users->links() }}</div>@endif
    </x-card>
@endsection
