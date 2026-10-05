<x-layouts.panel title="کیت اجزا" current="kit" :breadcrumbs="['پنل', 'کیت اجزا']">
    {{--
        Namayeshgah-e primitive-haye panel (docs/frontend/admin-panel.md §26). Faghat local/testing.
        Har demo hamun API-i ro seda mizane ke yek safhe-ye vaghei mizane: `$store.toasts`,
        `$store.confirm.ask`, `panelRequest`, `x-model` rooye modal va form.
    --}}
    <div x-data="{
            modal: false,
            confirmed: null,
            errors: {},
            form: { title: '', slug: '', kind: 'metal', weight: '', note: '', active: true, sitemap: false, sms: true, purity: '750' },
            async request(outcome) {
                const response = await panelRequest(@js(route('panel.kit.request')), { body: { outcome } });
                this.errors = response.errors;
            },
            async remove() {
                this.confirmed = await $store.confirm.ask({
                    title: 'این ابزار حذف شود؟',
                    body: 'نگاشت‌های وابسته هم حذف می‌شوند. این کار برگشت‌پذیر نیست.',
                    confirm: 'حذف',
                    danger: true,
                });
            },
            async publish() {
                this.confirmed = await $store.confirm.ask({ title: 'صفحه منتشر شود؟', body: 'نسخه‌ی عمومی همین حالا به‌روز می‌شود.', confirm: 'انتشار' });
            },
            showErrors() {
                this.errors = { title: 'عنوان الزامی است.', slug: ['شناسه تکراری است.'], weight: 'وزن باید بزرگ‌تر از صفر باشد.', purity: 'عیار را انتخاب کنید.', active: 'ابزار غیرفعال منتشر نمی‌شود.' };
            },
         }">

        <x-panel.page-header title="کیت اجزای پنل" description="هر primitive با یک نمونه‌ی زنده؛ همان چیزی که صفحه‌های واقعی از آن ساخته می‌شوند.">
            <x-slot:breadcrumb>
                <a href="{{ route('panel.dashboard') }}" class="hover:text-ink">میزکار</a>
                <x-icon name="chevron" class="size-3 text-muted-3" />
                <span class="text-ink">کیت اجزا</span>
            </x-slot:breadcrumb>
            <x-slot:actions>
            <x-panel.help key="kit" title="راهنمای کیت">
                <h3>این صفحه چیست؟</h3>
                <p>نمونهٔ زندهٔ همهٔ اجزای پنل. هر صفحهٔ فرم یا فهرست یک دکمهٔ «راهنما» مثل همین دارد.</p>
                <dl><dt>toast</dt><dd>پیام کوتاه پس از هر عمل.</dd><dt>modal</dt><dd>فرم‌ها داخل پنجرهٔ شناور.</dd></dl>
            </x-panel.help>
                <x-panel.button variant="secondary" size="sm" href="{{ url('/panel/kit/date') }}" icon="clock">تاریخ شمسی</x-panel.button>
                <x-panel.button variant="secondary" size="sm" href="{{ url('/panel/kit/slug') }}" icon="link">شناسه (slug)</x-panel.button>
            </x-slot:actions>
        </x-panel.page-header>

        <div class="grid gap-6 xl:grid-cols-2">

            {{-- ── Toast ─────────────────────────────────────────────────────────── --}}
            <x-panel.section title="توست" description="چهار نوع، بستن خودکار، مکث روی hover، انباشته تا پنج‌تا.">
                <x-slot:actions>
                    <x-panel.button variant="ghost" size="sm" @click="$store.toasts.clear()">پاک کردن همه</x-panel.button>
                </x-slot:actions>

                <div class="flex flex-wrap gap-2" data-demo="toasts">
                    <x-panel.button variant="secondary" size="sm" @click="$store.toasts.success('ذخیره شد', 'ابزار «طلای ۱۸ عیار» به‌روز شد.')">توست موفق</x-panel.button>
                    <x-panel.button variant="secondary" size="sm" @click="$store.toasts.error('ذخیره نشد', 'اتصال به پایگاه داده قطع شد.')">توست خطا</x-panel.button>
                    <x-panel.button variant="secondary" size="sm" @click="$store.toasts.warning('منبع کند است', 'آخرین پاسخ tgju بیش از ۳۰ ثانیه طول کشید.')">توست هشدار</x-panel.button>
                    <x-panel.button variant="secondary" size="sm" @click="$store.toasts.info('نکته', 'شناسه پس از ذخیره اصلاح می‌شود.')">توست اطلاع</x-panel.button>
                    <x-panel.button variant="secondary" size="sm" @click="$store.toasts.info('این توست می‌ماند', 'تا وقتی خودتان ببندید.', { duration: 0 })">بدون بستن خودکار</x-panel.button>
                </div>

                <h3 class="mt-6 text-sm font-semibold text-ink">قرارداد <span class="ltr">panelRequest</span></h3>
                <p class="mt-1 text-sm text-muted">یک POST جی‌سان با CSRF؛ پیام سرور خودش توست می‌شود. ۴۲۲ اولین خطای اعتبارسنجی را نشان می‌دهد و <span class="ltr">errors</span> را برمی‌گرداند تا زیر فیلدها بنشیند.</p>
                <div class="mt-3 flex flex-wrap gap-2" data-demo="request">
                    <x-panel.button size="sm" @click="request('ok')">۲۰۰ با message</x-panel.button>
                    <x-panel.button size="sm" @click="request('toast')">۲۰۰ با toast سفارشی</x-panel.button>
                    <x-panel.button variant="secondary" size="sm" @click="request('invalid')">۴۲۲ اعتبارسنجی</x-panel.button>
                    <x-panel.button variant="secondary" size="sm" @click="request('forbidden')">۴۰۳</x-panel.button>
                    <x-panel.button variant="secondary" size="sm" @click="request('error')">۵۰۰</x-panel.button>
                </div>

                <h3 class="mt-6 text-sm font-semibold text-ink">فلش پس از redirect</h3>
                <p class="mt-1 text-sm text-muted">فرم کلاسیک: سرور <span class="ltr">session('toast')</span> می‌گذارد و میزبان توست هنگام بارگذاری نشانش می‌دهد.</p>
                <form method="POST" action="{{ route('panel.kit.flash') }}" class="mt-3">
                    @csrf
                    <x-panel.button type="submit" variant="secondary" size="sm">ارسال فرم و بازگشت</x-panel.button>
                </form>
            </x-panel.section>

            {{-- ── Modal & Confirm ───────────────────────────────────────────────── --}}
            <x-panel.section title="مودال و تأیید" description="focus داخل مودال می‌ماند، Esc و کلیک بیرون می‌بندد، اسکرول صفحه قفل می‌شود.">
                <div class="flex flex-wrap gap-2" data-demo="modal">
                    <x-panel.button @click="modal = true" icon="plus">باز کردن مودال</x-panel.button>
                    <x-panel.button variant="danger" @click="remove()" icon="trash">حذف با تأیید</x-panel.button>
                    <x-panel.button variant="secondary" @click="publish()">انتشار با تأیید</x-panel.button>
                </div>

                <p class="mt-4 text-sm text-muted">
                    نتیجه‌ی آخرین تأیید:
                    <span class="font-medium" data-confirm-result
                          :class="confirmed === true ? 'text-ok' : confirmed === false ? 'text-danger' : 'text-muted-2'"
                          x-text="confirmed === null ? 'هنوز پرسیده نشده' : confirmed ? 'تأیید شد' : 'لغو شد'"></span>
                </p>

                <x-panel.modal x-model="modal" title="افزودن منبع قیمت" description="کلید، نام و اولویت را وارد کنید؛ نگاشت نمادها بعد از ساخت انجام می‌شود." size="md">
                    <div class="grid gap-4">
                        <x-form.field label="نام منبع" name="source_name" required hint="همان نامی که در جدول منابع دیده می‌شود.">
                            <x-form.input name="source_name" placeholder="مثلاً tgju" />
                        </x-form.field>
                        <x-form.field label="کلید" name="source_key" hint="لاتین، بدون فاصله.">
                            <x-form.input name="source_key" ltr placeholder="tgju" />
                        </x-form.field>
                        <x-form.field label="اولویت" name="source_priority">
                            <x-form.number name="source_priority" value="1" ltr />
                        </x-form.field>
                    </div>
                    <x-slot:footer>
                        <x-panel.button variant="secondary" @click="modal = false">انصراف</x-panel.button>
                        <x-panel.button @click="modal = false; $store.toasts.success('منبع ساخته شد')">ذخیره</x-panel.button>
                    </x-slot:footer>
                </x-panel.modal>
            </x-panel.section>

            {{-- ── Tabs ─────────────────────────────────────────────────────────── --}}
            <x-panel.section title="زبانه‌ها" description="جهت‌دهی راست‌به‌چپ: فلش چپ زبانه‌ی بعدی، فلش راست قبلی؛ Home/End اول و آخر. کلید زبانه در hash آدرس می‌نشیند." :padded="false">
                <div class="px-5 pb-5">
                    <x-panel.tabs :tabs="['general' => 'مشخصات', 'builder' => 'صفحه‌ساز', 'seo' => 'سئو']" sync label="زبانه‌های ویرایشگر">
                        <x-panel.tab-panel key="general">
                            <p class="text-sm text-muted">عنوان، شناسه، وضعیت و تاریخ انتشار این‌جا می‌آیند.</p>
                        </x-panel.tab-panel>
                        <x-panel.tab-panel key="builder">
                            <div class="rounded-xl border border-warn/20 bg-warn-soft px-4 py-3 text-sm text-warn">اول صفحه را ذخیره کنید؛ بعد صفحه‌ساز روی همین صفحه باز می‌شود.</div>
                        </x-panel.tab-panel>
                        <x-panel.tab-panel key="seo">
                            <p class="text-sm text-muted">کلیدواژه‌ی کانونی، عنوان و توضیحات متا با شمارنده‌ی زنده.</p>
                        </x-panel.tab-panel>
                    </x-panel.tabs>
                </div>
            </x-panel.section>

            {{-- ── Buttons ──────────────────────────────────────────────────────── --}}
            <x-panel.section title="دکمه‌ها" description="چهار گونه، سه اندازه، حالت غیرفعال.">
                <div class="flex flex-wrap items-center gap-2">
                    <x-panel.button>اصلی</x-panel.button>
                    <x-panel.button variant="secondary">ثانویه</x-panel.button>
                    <x-panel.button variant="danger" icon="trash">حذف</x-panel.button>
                    <x-panel.button variant="ghost">بی‌قاب</x-panel.button>
                    <x-panel.button disabled>غیرفعال</x-panel.button>
                </div>
                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <x-panel.button size="sm" icon="plus">کوچک</x-panel.button>
                    <x-panel.button size="md" icon="plus">متوسط</x-panel.button>
                    <x-panel.button size="lg" icon="plus">بزرگ</x-panel.button>
                </div>
            </x-panel.section>

            {{-- ── Forms ────────────────────────────────────────────────────────── --}}
            <x-panel.section title="فرم" description="label، hint و خطا از x-form.field؛ خطا از object «errors» در Alpine یا از $errors سرور." class="xl:col-span-2">
                <x-slot:actions>
                    <x-panel.button variant="secondary" size="sm" @click="showErrors()">نمایش خطاها</x-panel.button>
                    <x-panel.button variant="ghost" size="sm" @click="errors = {}">پاک کردن خطاها</x-panel.button>
                </x-slot:actions>

                <form @submit.prevent="request('invalid')" class="grid gap-5 md:grid-cols-2" data-demo="form" novalidate>
                    <x-form.field label="عنوان" name="title" required hint="نام فارسی که در تابلو دیده می‌شود.">
                        <x-form.input name="title" x-model="form.title" placeholder="مثلاً طلای ۱۸ عیار" />
                    </x-form.field>

                    <x-form.field label="شناسه (کد)" name="slug" hint="لاتین و بدون فاصله؛ پس از انتشار تغییر نمی‌کند.">
                        <x-form.input name="slug" x-model="form.slug" ltr placeholder="GOLD_18K_G" />
                    </x-form.field>

                    <x-form.field label="وزن (گرم)" name="weight" hint="اعداد فارسی هم پذیرفته می‌شود.">
                        <x-form.number name="weight" x-model="form.weight" placeholder="۴٫۶۰۸۳" />
                    </x-form.field>

                    <x-form.field label="نوع" name="kind">
                        <x-form.select name="kind" x-model="form.kind" :options="['metal' => 'فلز', 'coin' => 'سکه', 'currency' => 'ارز']" />
                    </x-form.field>

                    <x-form.field label="یادداشت" name="note" class="md:col-span-2">
                        <x-form.textarea name="note" x-model="form.note" rows="3" placeholder="اختیاری" />
                    </x-form.field>

                    <x-form.field label="عیار" name="purity">
                        <x-form.radio-group name="purity" x-model="form.purity" inline :options="['750' => '۱۸ عیار (۷۵۰)', '740' => '۷۴۰', '999' => '۲۴ عیار (۹۹۹)']" />
                    </x-form.field>

                    <div class="grid gap-4">
                        <x-form.field name="active">
                            <x-form.toggle name="active" x-model="form.active" label="فعال" description="ابزار غیرفعال از تابلو و API حذف می‌شود." />
                        </x-form.field>
                        <x-form.toggle name="sitemap" x-model="form.sitemap" label="نمایش در نقشه‌ی سایت" />
                        <x-form.checkbox name="sms" x-model="form.sms" label="اطلاع با پیامک" description="هنگام کهنه شدن قیمت." />
                        <x-form.checkbox name="locked" label="غیرفعال" disabled checked />
                    </div>

                    <x-form.field label="اندازه‌ها و حالت‌ها" class="md:col-span-2">
                        <div class="grid gap-3 md:grid-cols-4">
                            <x-form.input size="sm" placeholder="کوچک" aria-label="کوچک" />
                            <x-form.input size="md" placeholder="متوسط" aria-label="متوسط" />
                            <x-form.input size="lg" placeholder="بزرگ" aria-label="بزرگ" />
                            <x-form.input value="فقط‌خواندنی" readonly aria-label="فقط‌خواندنی" />
                            <x-form.input value="غیرفعال" disabled aria-label="غیرفعال" />
                            <x-form.number value="42850000" ltr readonly aria-label="عدد فقط‌خواندنی" />
                            <x-form.select placeholder="انتخاب کنید" :options="['a' => 'گزینه‌ی الف']" aria-label="انتخاب" />
                            <x-form.select disabled :options="['a' => 'غیرفعال']" aria-label="انتخاب غیرفعال" />
                        </div>
                    </x-form.field>

                    <div class="flex items-center gap-2 md:col-span-2">
                        <x-panel.button type="submit">ذخیره (۴۲۲ از سرور)</x-panel.button>
                        <span class="text-xs text-muted-2">مقدار فرم: <span class="ltr num text-ink" x-text="JSON.stringify(form)"></span></span>
                    </div>
                </form>
            </x-panel.section>
        </div>
    </div>
</x-layouts.panel>
