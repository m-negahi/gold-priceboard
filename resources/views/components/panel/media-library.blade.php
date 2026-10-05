{{--
    Modal-e ketabkhane-ye rasane — yek bar rooye har safhe-i ke picker ya rich-text dare. Baz mishe
    ba event-e `media-library-open` (detail: { onPick }); do tab: «کتابخانه» (search + grid az
    hamun endpoint-e datatable) va «بارگذاری» (form-e moshtarak). Alpine: media-library.js
--}}
<div x-data="mediaLibrary({ url: @js(route('panel.media.table')) })" @media-library-open.window="openFor($event.detail ?? {})" data-media-library>
    <x-panel.modal x-model="libraryOpen" title="کتابخانه‌ی رسانه" description="تصویری را انتخاب کنید یا تصویر تازه‌ای بارگذاری کنید." size="xl">
        <div class="mb-4 flex gap-1 border-b border-line" role="tablist" aria-label="کتابخانه">
            <button type="button" role="tab" :aria-selected="tab === 'library'" @click="tab = 'library'"
                    class="-mb-px h-10 border-b-2 px-3 text-sm font-medium" :class="tab === 'library' ? 'border-brand text-brand-2' : 'border-transparent text-muted hover:text-ink'">کتابخانه</button>
            <button type="button" role="tab" :aria-selected="tab === 'upload'" @click="tab = 'upload'"
                    class="-mb-px h-10 border-b-2 px-3 text-sm font-medium" :class="tab === 'upload' ? 'border-brand text-brand-2' : 'border-transparent text-muted hover:text-ink'" data-media-library-upload-tab>بارگذاری</button>
        </div>

        <div x-show="tab === 'library'" role="tabpanel">
            <div class="relative mb-3">
                <x-icon name="search" class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-muted-2" />
                <input type="search" x-model="q" placeholder="جستجو در نام، alt یا عنوان…" aria-label="جستجوی رسانه"
                       class="{{ \App\Modules\Panel\Support\FormControl::classes('sm') }} pr-9">
            </div>

            <div x-show="loading" class="grid grid-cols-3 gap-3 sm:grid-cols-4 md:grid-cols-6" aria-hidden="true">
                <template x-for="i in 12" :key="i"><div class="aspect-square animate-pulse rounded-lg bg-surface-3"></div></template>
            </div>

            <div x-show="!loading && rows.length === 0" x-cloak class="rounded-xl border border-dashed border-line-3 p-8 text-center text-sm text-muted">
                <span x-show="q === ''">هنوز تصویری در کتابخانه نیست.</span>
                <span x-show="q !== ''">چیزی پیدا نشد.</span>
                <button type="button" class="ms-1 text-brand hover:underline" @click="tab = 'upload'">بارگذاری کنید</button>
            </div>

            <ul x-show="!loading && rows.length > 0" class="grid max-h-[50vh] grid-cols-3 gap-3 overflow-y-auto p-0.5 sm:grid-cols-4 md:grid-cols-6" role="listbox" aria-label="تصویرها">
                <template x-for="row in rows" :key="row.id">
                    <li role="option" :aria-selected="selected?.id === row.id">
                        <button type="button" @click="select(row)" @dblclick="pick(row)" :title="row.alt"
                                class="group relative block aspect-square w-full overflow-hidden rounded-lg bg-surface-2 ring-1 ring-line focus-visible:outline-none focus-visible:shadow-(--bp-focus-ring)"
                                :class="selected?.id === row.id && 'ring-2 ring-brand'" data-media-library-item>
                            <img :src="row.thumb_url" :alt="row.alt" loading="lazy" decoding="async" class="size-full object-cover">
                            <span class="absolute inset-x-0 bottom-0 truncate bg-ink/60 px-1.5 py-1 text-start text-xs text-on-brand" x-text="row.alt"></span>
                        </button>
                    </li>
                </template>
            </ul>

            <div class="mt-3 flex items-center justify-between text-xs text-muted">
                <span class="num" x-text="`${fa(meta.total)} تصویر`"></span>
                <nav class="flex gap-1" aria-label="صفحه‌بندی" x-show="meta.last_page > 1">
                    <x-panel.button variant="ghost" size="sm" ::disabled="meta.page <= 1" @click="load(meta.page - 1)">قبلی</x-panel.button>
                    <span class="num self-center" x-text="`${fa(meta.page)} / ${fa(meta.last_page)}`"></span>
                    <x-panel.button variant="ghost" size="sm" ::disabled="meta.page >= meta.last_page" @click="load(meta.page + 1)">بعدی</x-panel.button>
                </nav>
            </div>
        </div>

        <div x-show="tab === 'upload'" x-cloak role="tabpanel" @media-uploaded="uploaded($event.detail)">
            @include('media::panel.media._upload')
        </div>

        <x-slot:footer>
            <x-panel.button variant="secondary" @click="closeLibrary()">انصراف</x-panel.button>
            <x-panel.button x-show="tab === 'library'" ::disabled="!selected" @click="confirm()" data-media-library-confirm>انتخاب</x-panel.button>
        </x-slot:footer>
    </x-panel.modal>
</div>
