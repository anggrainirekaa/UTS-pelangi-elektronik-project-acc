@php $u = $user ?? null; @endphp
<div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
    <x-field name="name" label="Nama" :value="$u?->name" required />
    <x-field name="email" label="Email" type="email" :value="$u?->email" required />
    <x-select-field name="role" label="Role" :options="['owner' => 'Owner', 'staff' => 'Staff', 'pelanggan' => 'Pelanggan']" :value="$u?->role ?? 'staff'" required />
    <x-field name="phone" label="Telepon" :value="$u?->phone" />
    <x-field name="address" label="Alamat" type="textarea" :value="$u?->address" class="sm:col-span-2" />
    <x-field name="password" label="Password" type="password" :required="! $u" :hint="$u ? 'Kosongkan jika tidak diubah.' : null" autocomplete="new-password" />
    <x-field name="password_confirmation" label="Konfirmasi Password" type="password" autocomplete="new-password" />
</div>
