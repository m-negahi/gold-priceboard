<section class="rounded-2xl bg-surface card-p shadow-xs" data-verification-report>
    <h2 class="text-base font-bold text-ink">گزارش استعلام زحل</h2>
    @forelse ($verificationRuns as $run)
        <div class="mt-4 space-y-2 border-t border-line pt-3">
            <p class="num text-xs text-muted">#{{ $run['id'] }} · {{ $run['at'] }}</p>
            @if ($run['status'] !== 'completed')<p class="text-sm text-muted">استعلام در صف یا در حال اجرا است. برای مشاهده نتیجه صفحه را تازه کنید.</p>@endif
            @foreach ($run['checks'] as $result)
                <details class="rounded-xl bg-surface-2 p-3">
                    <summary class="cursor-pointer text-sm text-ink">
                        {{ $result['label'] }}
                        <x-icon :name="$result['icon']" class="inline-block size-4" />
                        {{ $result['message'] }}
                    </summary>
                    <pre class="ltr mt-3 overflow-x-auto whitespace-pre-wrap break-all text-xs">{{ $result['rawPreview'] }}</pre>
                </details>
            @endforeach
        </div>
    @empty <p class="mt-3 text-sm text-muted">هنوز استعلامی ثبت نشده است.</p> @endforelse
</section>
