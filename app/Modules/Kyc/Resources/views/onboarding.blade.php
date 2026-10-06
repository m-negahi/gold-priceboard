{{-- Onboarding (P7-03): soal e «فاکتور رسمی» + form e hoghoghi. Alpine faghat
     baraye namayesh e form ba'd az «بله» (UI-only)؛ validate kamel samt e server. --}}

<x-layouts.site title="تکمیل حساب">
    <div class="container py-8">
        <div class="mx-auto max-w-3xl">
            <h1 class="text-2xl font-extrabold text-ink">{{ $firstTime ? 'خوش آمدید' : 'ارتقای سطح حساب' }}</h1>

            @if ($verified)
                <p class="mt-6 rounded-2xl bg-ok-soft px-4 py-6 text-sm text-ok" data-onboarding-verified>
                    حساب شما برای دریافت فاکتور رسمی تأیید شده است.
                </p>
            @elseif ($pendingTicketId !== null)
                <div class="mt-6 rounded-2xl bg-surface card-p shadow-xs" data-onboarding-pending>
                    <p class="text-sm text-ink">درخواست ارتقای سطح شما ثبت شده و در حال بررسی است.</p>
                    <a href="{{ route('account.tickets.show', $pendingTicketId) }}"
                       class="mt-4 inline-flex h-11 items-center gap-2 rounded-xl bg-brand px-4 text-sm font-semibold text-on-brand hover:bg-brand-2">
                        <x-icon name="ticket" class="size-4" />
                        مشاهده درخواست
                    </a>
                </div>
            @else
                <form method="POST" action="{{ route('onboarding.store') }}" enctype="multipart/form-data"
                      x-data="{ answer: @js(old('answer', '')) }" class="mt-6 space-y-4" data-onboarding-form>
                    @csrf

                    <section class="rounded-2xl bg-surface card-p-lg shadow-xs">
                        <x-form.field name="answer">
                            <x-form.radio-group name="answer" label="مایل به دریافت فاکتور رسمی هستید؟" inline x-model="answer" :options="[
                                'yes' => ['label' => 'بله', 'description' => 'اطلاعات حقوقی و مدارک را ثبت می‌کنم.'],
                                'no' => ['label' => 'خیر', 'description' => 'فعلاً نیازی ندارم؛ بعداً هم می‌توانم درخواست بدهم.'],
                            ]" />
                        </x-form.field>
                    </section>

                    <section x-show="answer === 'yes'" x-cloak class="rounded-2xl bg-surface card-p-lg shadow-xs" data-onboarding-legal aria-labelledby="legal-title">
                        <h2 id="legal-title" class="text-base font-bold text-ink">اطلاعات حقوقی</h2>
                        <p class="mt-1 text-xs text-muted">این اطلاعات با سامانه‌های استعلام بررسی و سپس توسط پشتیبانی تأیید می‌شود.</p>

                        <div class="mt-5 grid gap-4 sm:grid-cols-2">
                            <x-form.field name="registered_name" label="نام ثبتی شرکت" required class="sm:col-span-2">
                                <x-form.input name="registered_name" :value="old('registered_name')" maxlength="200" />
                            </x-form.field>
                            <x-form.field name="company_national_id" label="شناسه ملی شرکت" required>
                                <x-form.input name="company_national_id" ltr inputmode="numeric" :value="old('company_national_id')" maxlength="20" placeholder="10380284790" />
                            </x-form.field>
                            <x-form.field name="economic_code" label="کد اقتصادی" required>
                                <x-form.input name="economic_code" ltr inputmode="numeric" :value="old('economic_code')" maxlength="20" />
                            </x-form.field>
                            <x-form.field name="postal_code" label="کد پستی" required>
                                <x-form.input name="postal_code" ltr inputmode="numeric" :value="old('postal_code')" maxlength="20" placeholder="1234567890" />
                            </x-form.field>
                            <x-form.field name="phone" label="تلفن ثابت" required>
                                <x-form.input name="phone" ltr inputmode="tel" :value="old('phone')" maxlength="20" placeholder="02112345678" />
                            </x-form.field>
                            <x-form.field name="address" label="نشانی" required class="sm:col-span-2">
                                <x-form.textarea name="address" rows="2" maxlength="500">{{ old('address') }}</x-form.textarea>
                            </x-form.field>
                            <x-form.field name="representative_national_code" label="کد ملی نماینده" required>
                                <x-form.input name="representative_national_code" ltr inputmode="numeric" :value="old('representative_national_code')" maxlength="20" />
                            </x-form.field>
                            <x-form.field name="representative_birth_date" label="تاریخ تولد نماینده" required hint="شمسی، مثلاً 1370/01/15">
                                <x-form.input name="representative_birth_date" ltr :value="old('representative_birth_date')" maxlength="10" placeholder="1370/01/15" />
                            </x-form.field>
                            <x-form.field name="files" :error="$errors->first('files') ?: ($errors->first('files.*') ?: null)" label="مدارک (جواز کسب / مدارک ثبتی)" required class="sm:col-span-2"
                                          hint="حداکثر ۵ فایل PDF/JPG/PNG/WEBP، هر کدام تا ۵ مگابایت.">
                                <x-form.file />
                            </x-form.field>
                        </div>
                    </section>

                    <div class="flex justify-end">
                        <button type="submit" data-onboarding-submit
                                class="inline-flex h-11 items-center gap-2 rounded-xl bg-brand px-5 text-sm font-semibold text-on-brand shadow-(--bp-elev-brand) hover:bg-brand-2">
                            <x-icon name="check" class="size-4" />
                            ادامه
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</x-layouts.site>
