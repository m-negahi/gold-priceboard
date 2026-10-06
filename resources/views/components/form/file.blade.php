@props([
    'name' => 'files',
    'id' => null,
    'multiple' => true,
    'accept' => '.pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp',
])
@use('App\Modules\Panel\Support\FormControl')

{{-- Input e file (madarek e ticket — P7-02). Native: bedun e JS kar mikone؛
     validate e vaghei (mime az mohtava + size) samt e server ast. --}}
@php $id ??= FormControl::id($name); @endphp

<div x-data="mediaUpload({ localMultiple: true })" @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false" @drop.prevent="dropLocal($event)" data-private-attachments>
<input x-ref="file" @change="pickLocal($event)" type="file" name="{{ $name }}{{ $multiple ? '[]' : '' }}" id="{{ $id }}" @if ($multiple) multiple @endif accept="{{ $accept }}"
       :aria-invalid="{{ FormControl::ARIA_INVALID }}"
       :aria-describedby="{{ FormControl::ARIA_DESCRIBED_BY }}"
       {{ $attributes->merge(['class' => 'block w-full min-w-0 rounded-xl border border-dashed border-line-3 bg-surface-2 p-2 text-sm text-muted file:me-3 file:rounded-lg file:border-0 file:bg-surface file:px-3 file:py-2 file:text-sm file:font-semibold file:text-ink hover:border-muted-3 group-data-[invalid=true]/field:border-danger']) }}>

    <ul x-show="localFiles.length" x-cloak class="mt-2 space-y-1 text-xs text-muted" aria-live="polite">
        <template x-for="(file, index) in localFiles" :key="index"><li class="flex min-w-0 gap-2"><span class="truncate" x-text="file.name"></span><span class="num shrink-0" x-text="file.size + ' KB'"></span></li></template>
    </ul>
</div>
