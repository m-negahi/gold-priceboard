<x-layouts.panel title="رسانه" current="media" :breadcrumbs="['پنل', 'محتوا', 'رسانه']">
    @php
        $base = route('panel.media.index');
        $urls = ['update' => $base.'/{id}', 'destroy' => $base.'/{id}'];
        $mimeOptions = [
            ['value' => 'image/jpeg', 'label' => 'JPG'],
            ['value' => 'image/png', 'label' => 'PNG'],
            ['value' => 'image/webp', 'label' => 'WebP'],
            ['value' => 'image/svg+xml', 'label' => 'SVG'],
        ];
    @endphp

    <div x-data="mediaPage(@js(['urls' => $urls]))" @media-view="window.open($event.detail.url, '_blank')" @media-edit="openEdit($event.detail)" @media-delete="remove($event.detail)">
        <x-panel.page-header title="کتابخانه‌ی رسانه" description="تصویرهای سایت و لوگوی منابع قیمت را بارگذاری و مدیریت کنید.">
            <x-slot:actions>
                <x-panel.help key="media" title="راهنمای کتابخانه‌ی رسانه">
                    <h3>این صفحه برای چیست؟</h3>
                    <p>تصویر را یک بار بارگذاری کنید و در فرم منابع قیمت به‌عنوان لوگو انتخاب کنید. تا زمانی که تصویر در حال استفاده است، حذف نمی‌شود.</p>

                    <h3>بارگذاری</h3>
                    <dl>
                        <dt>فایل</dt>
                        <dd>JPG، PNG، WebP یا SVG تا ۵ مگابایت. نوع فایل از محتوای آن تشخیص داده می‌شود، نه از پسوند.</dd>
                        <dt>متن جایگزین (alt)</dt>
                        <dd><strong>الزامی.</strong> یک جمله که بگوید تصویر چه چیزی را نشان می‌دهد. گوگل و صفحه‌خوان‌ها فقط همین را «می‌بینند». بدون alt هیچ تصویری وارد کتابخانه نمی‌شود.</dd>
                        <dt>عنوان</dt>
                        <dd>اختیاری؛ فقط برای جستجو در همین کتابخانه.</dd>
                    </dl>
                    <p>برای هر تصویر رستری، خودکار یک نسخه‌ی WebP هم‌اندازه، یک بندانگشتی ۴۰۰ پیکسلی و نسخه‌های ۴۰۰/۸۰۰/۱۲۰۰ برای <span class="ltr">srcset</span> ساخته می‌شود. محتوای ناامن SVG پیش از ذخیره پاک می‌شود.</p>

                    <h3>ستون‌ها</h3>
                    <dl>
                        <dt>ابعاد</dt><dd>عرض × ارتفاع اصل تصویر؛ SVG «برداری» است.</dd>
                        <dt>استفاده</dt><dd>در چند جای سایت استفاده شده. تا این عدد صفر نشود، حذف رد می‌شود.</dd>
                    </dl>

                    <h3>حذف</h3>
                    <p>حذف، فایل اصلی و همه‌ی نسخه‌های آن را از دیسک پاک می‌کند و قابل بازگشت نیست. تصویری که جایی استفاده شده حذف نمی‌شود — اول آن را از صفحه بردارید.</p>
                </x-panel.help>
                @can('media.manage')
                    <x-panel.button icon="upload" @click="openUpload()" data-media-open-upload>بارگذاری تصویر</x-panel.button>
                @endcan
            </x-slot:actions>
        </x-panel.page-header>

        <x-panel.datatable
            caption="فهرست رسانه‌ها"
            :endpoint="route('panel.media.table')"
            search-placeholder="جستجو در نام، alt یا عنوان…"
            :columns="[
                ['key' => 'thumb_url', 'label' => 'تصویر', 'type' => 'image', 'altKey' => 'alt', 'class' => 'w-20'],
                ['key' => 'name', 'label' => 'نام فایل', 'sortable' => true, 'ltr' => true, 'strong' => true],
                ['key' => 'alt', 'label' => 'متن جایگزین'],
                ['key' => 'kind', 'label' => 'نوع', 'type' => 'badge', 'badges' => [
                    'JPG' => ['label' => 'JPG', 'tone' => 'muted'], 'PNG' => ['label' => 'PNG', 'tone' => 'muted'],
                    'WEBP' => ['label' => 'WebP', 'tone' => 'info'], 'برداری' => ['label' => 'SVG', 'tone' => 'brand'],
                ]],
                ['key' => 'dimensions', 'label' => 'ابعاد', 'num' => true, 'muted' => true],
                ['key' => 'size_human', 'label' => 'حجم', 'sortable' => true, 'sort' => 'size', 'num' => true, 'muted' => true],
                ['key' => 'usages_count', 'label' => 'استفاده', 'sortable' => true, 'num' => true, 'align' => 'center'],
                ['key' => 'created_at', 'label' => 'بارگذاری', 'sortable' => true, 'type' => 'date-jalali', 'muted' => true],
            ]"
            :filters="[['key' => 'mime', 'label' => 'نوع', 'options' => $mimeOptions]]"
            :row-actions="[
                ['label' => 'مشاهده', 'icon' => 'external', 'event' => 'media-view'],
                ...(auth()->user()->can('media.manage') ? [
                    ['label' => 'ویرایش', 'icon' => 'pencil', 'event' => 'media-edit'],
                    ['label' => 'حذف', 'icon' => 'trash', 'event' => 'media-delete', 'tone' => 'danger'],
                ] : []),
            ]"
            :empty="[
                'title' => 'هنوز تصویری بارگذاری نشده',
                'text' => 'اولین تصویر را بارگذاری کنید؛ سپس در فرم منبع از کتابخانه انتخاب کنید.',
            ]" />

        @can('media.manage')
        {{-- ── Modal-e upload ── --}}
        <x-panel.modal x-model="uploadOpen" title="بارگذاری تصویر" description="متن جایگزین را حتماً بنویسید؛ بدون آن تصویر ذخیره نمی‌شود." size="md">
            <div @media-uploaded="uploaded($event.detail)">
                @include('media::panel.media._upload')
            </div>
            <x-slot:footer>
                <x-panel.button variant="secondary" @click="uploadOpen = false">بستن</x-panel.button>
            </x-slot:footer>
        </x-panel.modal>

        {{-- ── Modal-e virayesh-e alt/title ── --}}
        <x-panel.modal x-model="editOpen" title="ویرایش رسانه" description="فقط متن‌ها عوض می‌شوند؛ فایل همان می‌ماند." size="md">
            <form class="grid gap-4" @submit.prevent="saveEdit()">
                <div class="flex items-center gap-3">
                    <img :src="edit.thumb_url" :alt="edit.alt" class="size-16 rounded-lg bg-surface-2 object-cover ring-1 ring-line">
                    <span class="ltr truncate text-sm text-muted" x-text="edit.name"></span>
                </div>
                <x-form.field label="متن جایگزین (alt)" name="edit[alt]" required>
                    <x-form.input name="edit[alt]" x-model="edit.alt" ::readonly="saving" />
                </x-form.field>
                <x-form.field label="عنوان" name="edit[title]">
                    <x-form.input name="edit[title]" x-model="edit.title" ::readonly="saving" />
                </x-form.field>
                <button type="submit" class="sr-only" tabindex="-1" aria-hidden="true"></button>
            </form>
            <x-slot:footer>
                <x-panel.button variant="secondary" @click="editOpen = false" ::disabled="saving">انصراف</x-panel.button>
                <x-panel.button @click="saveEdit()" ::disabled="saving">ذخیره</x-panel.button>
            </x-slot:footer>
        </x-panel.modal>
        @endcan
    </div>
</x-layouts.panel>
