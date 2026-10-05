{{--
    Form-e upload-e yek tasvir — moshtarak beyn-e modal-e safhe-ye رسانه va tab-e «بارگذاری»-e
    ketabkhane. Parent ba `@media-uploaded` row-e jadid ro migire. Alt ejbari-e.
    Id-haye control ha namespaced-and (`media[alt]` → `f-media-alt`) ta ba `f-title`-e safhe-ye mizban barkhord nakonand.
    Alpine: resources/js/alpine/media-upload.js
--}}
<div x-data="mediaUpload({ url: @js(route('panel.media.store')), maxBytes: @js(\App\Modules\Media\Actions\UploadMediaAction::MAX_BYTES) })"
     class="space-y-4" data-media-upload>
    <div @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false" @drop.prevent="drop($event)"
         :class="dragging ? 'border-brand bg-brand-soft' : 'border-line-3 bg-surface-2'"
         class="relative rounded-xl border-2 border-dashed p-4 text-center transition-colors duration-(--bp-dur-fast)">
        <input type="file" x-ref="file" accept="image/jpeg,image/png,image/webp,image/svg+xml" @change="pick($event)"
               class="sr-only" id="f-media-file" data-media-upload-input>

        <template x-if="preview">
            <img :src="preview" alt="" class="mx-auto mb-3 max-h-40 rounded-lg object-contain">
        </template>

        <p class="text-sm text-ink" x-show="!file">تصویر را این‌جا رها کنید یا
            <label for="f-media-file" class="cursor-pointer font-medium text-brand hover:underline">انتخاب کنید</label>
        </p>
        <p class="text-sm text-ink" x-show="file" x-cloak>
            <span x-text="file?.name" class="ltr inline-block font-medium"></span>
            · <label for="f-media-file" class="cursor-pointer text-brand hover:underline">فایل دیگر</label>
        </p>
        <p class="mt-1 text-xs text-muted-2">JPG، PNG، WebP یا SVG — حداکثر ۵ مگابایت. نسخه‌ی WebP و بندانگشتی خودکار ساخته می‌شود.</p>
        <p x-show="errors['media.file']" x-cloak class="mt-2 text-xs text-danger" role="alert" x-text="Array.isArray(errors['media.file']) ? errors['media.file'][0] : errors['media.file']"></p>
    </div>

    <x-form.field label="متن جایگزین (alt)" name="media[alt]" required hint="بگویید تصویر چه چیزی را نشان می‌دهد؛ برای گوگل و نابینایان همین متن «تصویر» است.">
        <x-form.input name="media[alt]" x-model="alt" ::readonly="uploading" placeholder="مثلاً: شمش طلای ۲۴ عیار روی ترازو" />
    </x-form.field>
    <x-form.field label="عنوان" name="media[title]" hint="اختیاری؛ در کتابخانه برای جستجو استفاده می‌شود.">
        <x-form.input name="media[title]" x-model="title" ::readonly="uploading" />
    </x-form.field>

    <div x-show="uploading" x-cloak class="space-y-1" role="status" aria-live="polite">
        <div class="h-2 overflow-hidden rounded-full bg-surface-3">
            <div class="h-full rounded-full bg-brand transition-[width] duration-(--bp-dur-fast)" :style="`width:${progress}%`"></div>
        </div>
        <p class="num text-xs text-muted">در حال بارگذاری… <span x-text="new Intl.NumberFormat('fa-IR').format(progress)"></span>٪</p>
    </div>

    <div class="flex justify-end gap-2">
        <x-panel.button icon="upload" @click="submit()" ::disabled="!canSubmit" data-media-upload-submit>بارگذاری</x-panel.button>
    </div>
</div>
