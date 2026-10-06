<x-layouts.site :chrome="false" title="ورود" description="ورود به تابلو قیمت با شماره موبایل و کد یک‌بارمصرف.">
    @push('head')
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="robots" content="noindex, nofollow">
    @endpush

    <div x-data="login({ routes: { code: @js(route('login.code')), verify: @js(route('login.verify')), reset: @js(route('login.reset')) }, initial: @js($initial) })"
         class="grid min-h-dvh lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">

        {{-- ── Dar-e vorood ────────────────────────────────────────────────────────────── --}}
        <main class="flex flex-col px-4 py-6 sm:px-8">
            <a href="{{ route('home') }}" class="inline-flex w-fit items-center gap-2.5 rounded-lg text-ink" aria-label="تابلو قیمت — صفحه اصلی">
                <span class="grid size-9 place-items-center rounded-xl bg-brand text-on-brand shadow-(--bp-elev-brand)">
                    <x-icon name="coins" class="size-5" />
                </span>
                <span class="text-lg font-semibold">تابلو قیمت</span>
            </a>

            <div class="flex flex-1 items-center justify-center py-10">
                <div class="w-full max-w-md">

                    {{-- Ghadam-e aval: shomare --}}
                    <section x-show="step === 'phone'" aria-labelledby="login-title">
                        <h1 id="login-title" class="text-2xl font-bold leading-tight">ورود به حساب</h1>
                        <p class="mt-2 text-muted">
                            شماره موبایل خود را وارد کنید. کد ورود پیامک می‌شود؛ رمز عبوری وجود ندارد.
                        </p>

                        <form @submit.prevent="requestCode()" class="mt-8 space-y-5" novalidate>
                            <div>
                                <label for="phone" class="mb-2 block text-sm font-medium">شماره موبایل</label>
                                <div dir="ltr" class="flex h-14 items-stretch overflow-hidden rounded-xl border border-line-3 bg-surface transition-[border-color,box-shadow] duration-(--bp-dur-fast) focus-within:border-brand focus-within:shadow-(--bp-elev-brand)"
                                     :class="error && 'border-danger bg-danger-soft'">
                                    <span class="ltr num flex items-center gap-1.5 border-r border-line bg-surface-2 px-3.5 text-sm font-medium text-muted select-none" aria-hidden="true">
                                        <x-icon name="phone" class="size-4" />
                                        +98
                                    </span>
                                    <input id="phone" x-ref="phone" type="tel" inputmode="numeric" autocomplete="tel-national"
                                           :value="phone" @input="onPhoneInput($event)" placeholder="912 345 6789"
                                           :aria-invalid="!!error" aria-describedby="phone-hint"
                                           class="num min-w-0 flex-1 bg-transparent px-4 text-lg tracking-[0.08em] text-ink outline-none placeholder:text-muted-3 placeholder:tracking-normal">
                                </div>
                                <p id="phone-hint" class="mt-2 text-sm" :class="error ? 'text-danger' : 'text-muted-2'"
                                   x-text="error || 'اعداد فارسی و انگلیسی هر دو پذیرفته می‌شوند.'"></p>
                            </div>

                            <button type="submit" :disabled="busy || !phoneLooksComplete"
                                    class="flex h-13 w-full items-center justify-center gap-2 rounded-xl bg-brand font-semibold text-on-brand shadow-(--bp-elev-brand) transition-[background-color,transform] duration-(--bp-dur-fast) hover:bg-brand-2 active:scale-[0.99] disabled:cursor-not-allowed disabled:bg-brand/60 disabled:shadow-none">
                                <template x-if="!busy">
                                    <span class="inline-flex items-center gap-2">
                                        دریافت کد ورود
                                        <x-icon name="arrow" class="size-5 rotate-180" />
                                    </span>
                                </template>
                                <template x-if="busy">
                                    <span class="inline-flex items-center gap-2">
                                        <span class="size-4 animate-spin rounded-full border-2 border-on-brand-2/40 border-t-on-brand" aria-hidden="true"></span>
                                        در حال ارسال…
                                    </span>
                                </template>
                            </button>
                        </form>

                        <ul class="mt-8 space-y-3 text-sm text-muted">
                            <li class="flex items-start gap-3">
                                <x-icon name="sms" class="mt-0.5 size-5 shrink-0 text-brand" />
                                <span>کد فقط به همین شماره پیامک می‌شود و <span class="num">۲</span> دقیقه اعتبار دارد.</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <x-icon name="lock" class="mt-0.5 size-5 shrink-0 text-brand" />
                                <span>اتصال رمزنگاری‌شده است؛ جز شماره موبایل چیزی از شما نمی‌خواهیم.</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <x-icon name="shield" class="mt-0.5 size-5 shrink-0 text-brand" />
                                <span>ورود شما به معنی پذیرش <a href="{{ route('home') }}#terms" class="font-medium text-ink underline decoration-line-3 underline-offset-4 hover:decoration-ink">قوانین استفاده</a> و <a href="{{ route('home') }}#privacy" class="font-medium text-ink underline decoration-line-3 underline-offset-4 hover:decoration-ink">حریم خصوصی</a> است.</span>
                            </li>
                        </ul>
                    </section>

                    {{-- Ghadam-e dovom: code --}}
                    <section x-show="step === 'code'" x-cloak aria-labelledby="code-title">
                        <h1 id="code-title" class="text-2xl font-bold leading-tight">کد ورود</h1>
                        <p class="mt-2 text-muted">کد <span class="num">۶</span> رقمی به این شماره پیامک شد:</p>

                        {{-- Shomare + dokme-ye taghir kenar-e ham: yek chip-e vazeh, na yek link-e riz bala-ye safhe. --}}
                        <div class="mt-3 flex items-center justify-between gap-3 rounded-xl border border-line bg-surface-2 px-4 py-2.5">
                            <span class="ltr num text-base font-semibold tracking-[0.06em] text-ink" x-text="prettyPhone"></span>
                            <button type="button" @click="changeNumber()"
                                    class="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-lg bg-surface px-3 text-sm font-medium text-ink shadow-(--bp-elev-sm) ring-1 ring-line hover:bg-surface-3">
                                <x-icon name="pencil" class="size-4 text-brand" />
                                تغییر شماره
                            </button>
                        </div>

                        <div class="mt-8">
                            <x-form.otp-input x-model="code" x-ref="otp" @complete="verify()"
                                              ::disabled="verifying" ::data-invalid="!!error" />

                            <div class="mt-3 flex min-h-6 items-center justify-between gap-3 text-sm">
                                <p x-show="error" x-cloak x-text="error" class="text-danger" role="alert"></p>
                                <p x-show="!error && verifying" x-cloak class="inline-flex items-center gap-2 text-muted">
                                    <span class="size-3.5 animate-spin rounded-full border-2 border-line-3 border-t-brand" aria-hidden="true"></span>
                                    در حال بررسی…
                                </p>
                                <p x-show="!error && !verifying && expiresIn > 0" x-cloak class="inline-flex items-center gap-1.5 text-muted-2">
                                    <x-icon name="clock" class="size-4" />
                                    اعتبار کد: <span class="num" x-text="clock(expiresIn)"></span>
                                </p>
                            </div>
                        </div>

                        {{-- Bypass-e development: faghat vaghti neshun dade mishe ke server code ro bargardoonde (local/testing). --}}
                        <div x-show="revealedCode" x-cloak class="mt-6 flex items-center justify-between gap-4 rounded-xl border border-warn/20 bg-warn-soft px-4 py-3" data-otp-bypass>
                            <div class="min-w-0 text-sm">
                                <p class="font-medium text-warn">حالت آزمایشی</p>
                                <p class="text-ink-3">کد ورود: <span class="ltr num text-base font-semibold tracking-[0.2em] text-ink" x-text="revealedCode"></span></p>
                            </div>
                            <button type="button" @click="autoFill()" :disabled="verifying"
                                    class="inline-flex h-10 shrink-0 items-center gap-1.5 rounded-lg bg-surface px-3.5 text-sm font-medium text-ink shadow-(--bp-elev-sm) ring-1 ring-line hover:bg-surface-2 disabled:opacity-60">
                                <x-icon name="spark" class="size-4 text-warn" />
                                پر کردن خودکار
                            </button>
                        </div>

                        {{-- Bypass vali code tu session e DIGAR sakhte shode (store faghat
                             hash dare، pas inja reveal-shodani nist). Bedun e in، dev ru step
                             e code bedun e code va bedun e tozih gir mikard. --}}
                        <div x-show="bypassPending && !revealedCode" x-cloak class="mt-6 rounded-xl border border-warn/20 bg-warn-soft px-4 py-3 text-sm" data-otp-bypass-pending>
                            {{-- ⚠️ Onvan amdan «حالت آزمایشی» NIST: un matn kart e code ro
                                 yekta moshakhas mikone (e2e ba getByText peydash mikone). --}}
                            <p class="font-medium text-warn">کد در نشست دیگری است</p>
                            <p class="text-ink-3">کد فعلی این شماره در نشست دیگری صادر شده و این‌جا قابل نمایش نیست. بعد از پایان شمارش، «ارسال دوباره» را بزنید تا کد تازه همین‌جا نمایش داده شود.</p>
                        </div>

                        <div class="mt-8 flex items-center justify-between gap-4 border-t border-line pt-6 text-sm">
                            <span class="text-muted">کد را دریافت نکردید؟</span>

                            <button type="button" @click="requestCode()" :disabled="!canResend"
                                    class="inline-flex items-center gap-1.5 rounded-lg font-medium text-brand hover:text-brand-2 disabled:cursor-default disabled:text-muted-2">
                                <template x-if="canResend">
                                    <span class="inline-flex items-center gap-1.5">
                                        <x-icon name="refresh" class="size-4" />
                                        ارسال دوباره
                                    </span>
                                </template>
                                <template x-if="!canResend">
                                    <span>ارسال دوباره تا <span class="num" x-text="clock(resendIn)"></span></span>
                                </template>
                            </button>
                        </div>
                    </section>
                </div>
            </div>

            <footer class="flex flex-wrap items-center justify-between gap-2 text-xs text-muted-2">
                <span>© تابلو قیمت</span>
                <a href="{{ route('home') }}#support" class="hover:text-ink">پشتیبانی</a>
            </footer>
        </main>

        {{-- ── Chera az in dar rad shodan mi-arze ─────────────────────────────────────── --}}
        <aside class="relative hidden overflow-hidden bg-surface-dark text-ink-invert lg:flex lg:flex-col lg:justify-between lg:p-12" aria-label="درباره تابلو قیمت">
            <div class="pointer-events-none absolute -left-24 -top-24 size-96 rounded-full bg-brand/20 blur-3xl" aria-hidden="true"></div>
            <div class="pointer-events-none absolute -bottom-32 -right-16 size-96 rounded-full bg-gold/10 blur-3xl" aria-hidden="true"></div>

            <div class="relative">
                <span class="inline-flex items-center gap-2 rounded-full border border-line-invert bg-surface-dark-2 px-3 py-1 text-xs font-medium text-muted-invert">
                    <span class="size-1.5 rounded-full bg-ok" aria-hidden="true"></span>
                    سرویس قیمت لحظه‌ای برای کسب‌وکارها
                </span>

                <h2 class="mt-6 max-w-lg text-3xl font-bold leading-tight text-ink-invert">
                    قیمت طلا، سکه و نقره؛ از چند منبع، بدون حاشیه، با یک API.
                </h2>
                <p class="mt-4 max-w-md text-muted-invert">
                    منابع مختلف را هم‌زمان می‌خوانیم، حاشیه‌ی هر منبع را کنار می‌زنیم و اگر یکی از کار
                    افتاد، بعدی جای آن را می‌گیرد — بی‌آنکه شما بفهمید.
                </p>
            </div>

            <ul class="relative mt-12 space-y-6">
                <li class="flex gap-4">
                    <span class="grid size-11 shrink-0 place-items-center rounded-xl border border-line-invert bg-surface-dark-2 text-brand-on-dark">
                        <x-icon name="layers" class="size-5" />
                    </span>
                    <div>
                        <p class="font-semibold text-ink-invert">چند منبع، جایگزینی خودکار</p>
                        <p class="mt-1 text-sm text-muted-invert">اگر منبع اول قطع شد، منبع بعدی بی‌وقفه قیمت می‌دهد.</p>
                    </div>
                </li>
                <li class="flex gap-4">
                    <span class="grid size-11 shrink-0 place-items-center rounded-xl border border-line-invert bg-surface-dark-2 text-brand-on-dark">
                        <x-icon name="pulse" class="size-5" />
                    </span>
                    <div>
                        <p class="font-semibold text-ink-invert">قیمت خام، نه قیمت فروشنده</p>
                        <p class="mt-1 text-sm text-muted-invert">حاشیه‌ی هر منبع حذف می‌شود تا محصولات شما روی عدد درست ساخته شوند.</p>
                    </div>
                </li>
                <li class="flex gap-4">
                    <span class="grid size-11 shrink-0 place-items-center rounded-xl border border-line-invert bg-surface-dark-2 text-brand-on-dark">
                        <x-icon name="receipt" class="size-5" />
                    </span>
                    <div>
                        <p class="font-semibold text-ink-invert">فاکتور رسمی</p>
                        <p class="mt-1 text-sm text-muted-invert">هر پرداخت با فاکتور رسمی، قابل ارائه به امور مالی شرکت.</p>
                    </div>
                </li>
            </ul>

            <p class="relative mt-12 text-xs text-muted-invert">
                ورود تنها با شماره موبایل و کد یک‌بارمصرف انجام می‌شود. رمز عبوری ذخیره نمی‌کنیم.
            </p>
        </aside>
    </div>
</x-layouts.site>
