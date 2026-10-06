<x-layouts.panel title="تأیید سایت‌ها" current="sites" :breadcrumbs="['پنل', 'تأیید سایت‌ها']">
    <h1 class="text-2xl font-extrabold text-ink">درخواست‌های سایت</h1>
    <p class="mt-1 text-sm text-muted">استعلام نماد تنها با دکمه مدیر اجرا می‌شود. تأیید، کلید API سایت را فعال می‌کند.</p>
    @if ($errors->any())<p role="alert" class="mt-4 rounded-xl bg-down-soft p-3 text-sm text-down">{{ $errors->first() }}</p>@endif
    <section class="mt-5 space-y-3">
        @forelse ($sites as $site)
            <article class="rounded-2xl bg-surface card-p shadow-xs">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="font-bold text-ink">{{ $site['name'] }}</h2>
                        <p class="ltr text-sm text-muted">{{ $site['websiteUrl'] }}</p>
                        <p class="num mt-1 text-xs text-muted">تیکت #{{ $site['ticketId'] }} · کاربر #{{ $site['userId'] }}</p>
                    </div>
                    <x-panel.pill :tone="$site['clientStatus'] === 'approved' ? 'ok' : ($site['clientStatus'] === 'rejected' ? 'down' : 'warn')">{{ $site['clientStatus'] }}</x-panel.pill>
                </div>
                @if ($site['enamad'])
                    <p class="mt-3 rounded-xl bg-surface-2 p-3 text-sm">نماد: {{ $site['enamad']['message'] }} <span class="num text-xs text-muted">{{ $site['enamad']['checked_at'] }}</span></p>
                @else
                    <p class="mt-3 text-sm text-muted">هنوز استعلامی ثبت نشده است.</p>
                @endif
                @can('sites.manage')
                    @if ($site['status'] === 'open' || $site['status'] === 'in_review')
                        <div class="mt-3 flex flex-wrap gap-2">
                            <form method="POST" action="{{ route('panel.sites.enamad', $site['ticketId']) }}">@csrf<button class="h-11 rounded-xl bg-surface-2 px-4 text-sm font-semibold text-ink ring-1 ring-line">استعلام نماد</button></form>
                            <form method="POST" action="{{ route('panel.sites.decision', $site['ticketId']) }}" class="flex flex-wrap gap-2">
                                @csrf<input type="hidden" name="decision" value="approve">
                                <input name="note" maxlength="255" placeholder="یادداشت تأیید" class="h-11 rounded-xl border border-line px-3 text-sm">
                                <button class="h-11 rounded-xl bg-brand px-4 text-sm font-semibold text-on-brand">تأیید سایت</button>
                            </form>
                            <form method="POST" action="{{ route('panel.sites.decision', $site['ticketId']) }}" class="flex flex-wrap gap-2">
                                @csrf<input type="hidden" name="decision" value="reject">
                                <input name="note" required maxlength="255" placeholder="دلیل رد" class="h-11 rounded-xl border border-line px-3 text-sm">
                                <button class="h-11 rounded-xl bg-down-soft px-4 text-sm font-semibold text-down">رد سایت</button>
                            </form>
                        </div>
                    @endif
                @endcan
            </article>
        @empty
            <p class="rounded-2xl bg-surface p-8 text-center text-muted">درخواست سایتی ثبت نشده است.</p>
        @endforelse
    </section>
</x-layouts.panel>
