@props([
    // Esm-e field-e makhfi ke pass-e yekbar-masraf toosh mishine va form submit-esh mikone.
    'name' => 'captcha_pass',
    'label' => 'تأیید انسان بودن',
])

{{--
    Pazel-e captcha (ADR-0041): tasvir az server miad (GD, offline), tekke ro ba dastgire mikeshi
    ta tu ja-ye khali beshine. Hich javabi tu markup nist — x-e dorost faghat tu cache-e server-e.
    Ba'd az hal, `POST /captcha/verify` yek pass mide ke tu input-e makhfi mishine va event-e
    `captcha-passed` (ba `{ pass }`) rooye element bala mire; `captcha-reset` (window) pazel-e taze migire.

    Mokhtasat: sahne va navar HAR DO `dir="ltr"`-and — tasvir yek shey-e fiziki-e, pas «rast»
    hamishe yani rast-e safhe, che safhe RTL bashe che na; ArrowRight ham tekke ro be rast mibare.
    Client faghat scale mikone (arz-e render / arz-e tasvir) va x ro be pixel-e tasvir mifreste.

    Dastresi: dastgire `role="slider"` ba keyboard (flesh ha, Shift = gam-e bozorg, Home/End,
    Enter = bararsi) va yek halat-e jaygozin-e matni (soal-e hesabi) baraye screen reader —
    hamun endpoint, hamun pass.
--}}
@php $uid = 'cap-'.uniqid(); @endphp

<div x-data="captchaPuzzle({
        urls: { challenge: @js(route('captcha.challenge')), verify: @js(route('captcha.verify')) },
        csrf: @js(csrf_token()),
     })"
     x-init="load()"
     @captcha-reset.window="reset()"
     @resize.window.debounce.150ms="measure()"
     :data-state="state"
     data-captcha
     {{ $attributes->merge(['class' => 'rounded-xl border border-line bg-surface p-3']) }}>

    <input type="hidden" name="{{ $name }}" :value="pass" data-captcha-pass>

    <div class="mb-2 flex items-center justify-between gap-2">
        <span class="text-sm font-medium text-ink" id="{{ $uid }}-label">{{ $label }}</span>
        <div class="flex items-center gap-1">
            <button type="button" @click="mode = mode === 'slide' ? 'text' : 'slide'"
                    class="grid size-8 place-items-center rounded-lg text-muted hover:bg-surface-3 hover:text-ink"
                    :aria-pressed="mode === 'text'" :title="mode === 'slide' ? 'پاسخ متنی به‌جای کشیدن' : 'بازگشت به پازل'"
                    :aria-label="mode === 'slide' ? 'پاسخ متنی به‌جای کشیدن' : 'بازگشت به پازل'" data-captcha-mode>
                <x-icon name="help" class="size-4" />
            </button>
            <button type="button" @click="load()" :disabled="state === 'loading' || state === 'checking'"
                    class="grid size-8 place-items-center rounded-lg text-muted hover:bg-surface-3 hover:text-ink disabled:opacity-50"
                    title="پازل تازه" aria-label="پازل تازه" data-captcha-refresh>
                <x-icon name="refresh" class="size-4" />
            </button>
        </div>
    </div>

    {{-- ── Halat-e pazel ── --}}
    <div x-show="mode === 'slide'">
        <div class="relative w-full overflow-hidden rounded-lg bg-surface-3" dir="ltr" x-ref="stage"
             :style="{ aspectRatio: `${challenge.width || 2} / ${challenge.height || 1}` }" data-captcha-stage
             :data-width="challenge.width" :data-piece-width="challenge.piece_width">
            <img x-show="challenge.background" :src="challenge.background" alt="" draggable="false"
                 class="block h-auto w-full select-none" @load="measure()">
            <img x-show="challenge.piece && state !== 'passed'" :src="challenge.piece" alt="" draggable="false"
                 class="pointer-events-none absolute left-0 top-0 select-none drop-shadow-md" :style="pieceStyle" data-captcha-piece>
            <div x-show="state === 'loading' || state === 'error'" class="absolute inset-0 grid place-items-center text-muted">
                <x-icon name="refresh" class="size-6" ::class="state === 'loading' ? 'animate-spin' : ''" />
            </div>
        </div>

        <div class="relative mt-2 h-11 w-full rounded-full bg-surface-3 ring-1 ring-inset ring-line" dir="ltr" x-ref="track"
             :class="{ 'ring-ok': state === 'passed', 'ring-danger': state === 'failed' }">
            <span class="absolute inset-y-0 left-0 rounded-full" aria-hidden="true"
                  :class="state === 'passed' ? 'bg-ok-soft' : 'bg-brand-soft'" :style="{ width: `${handleLeft + handleWidth}px` }"></span>
            <button type="button" role="slider" x-ref="handle"
                    aria-labelledby="{{ $uid }}-label" aria-orientation="horizontal"
                    aria-valuemin="0" :aria-valuemax="maxX" :aria-valuenow="Math.round(pieceX)"
                    :aria-valuetext="`${fa(Math.round(pieceX))} از ${fa(maxX)}`"
                    :aria-disabled="!interactive"
                    class="absolute top-0 grid h-11 min-w-11 cursor-grab touch-none select-none place-items-center rounded-full text-on-brand shadow-(--bp-elev-sm) transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 active:cursor-grabbing"
                    :class="state === 'passed' ? 'bg-ok' : (state === 'failed' ? 'bg-danger' : 'bg-brand')"
                    :style="{ left: `${handleLeft}px`, width: `${handleWidth}px` }"
                    @pointerdown="start($event)" @pointermove="move($event)" @pointerup="end($event)" @pointercancel="cancel($event)"
                    @keydown="key($event)" data-captcha-handle>
                <x-icon name="check" class="size-5" x-show="state === 'passed'" x-cloak />
                <x-icon name="arrow" class="size-5" x-show="state !== 'passed'" />
            </button>
        </div>
    </div>

    {{-- ── Halat-e matni (screen reader / bedoone mouse) ── --}}
    <div x-show="mode === 'text'" x-cloak class="space-y-2">
        <label :for="'{{ $uid }}-answer'" class="block text-sm text-ink" x-text="challenge.question || 'در حال آماده‌سازی…'"></label>
        <div class="flex gap-2">
            <input type="text" inputmode="numeric" autocomplete="off" :id="'{{ $uid }}-answer'" x-model="answer"
                   :disabled="!interactive" @keydown.enter.prevent="verifyText()"
                   class="num h-11 w-24 rounded-lg border border-line bg-surface px-3 text-sm text-ink focus:border-brand focus:outline-none disabled:bg-surface-2"
                   data-captcha-answer>
            <button type="button" @click="verifyText()" :disabled="!interactive || answer === ''"
                    class="h-11 rounded-lg bg-brand px-4 text-sm font-medium text-on-brand hover:bg-brand-2 disabled:bg-brand/60" data-captcha-answer-submit>
                بررسی
            </button>
        </div>
    </div>

    <p class="mt-2 text-xs" role="status" aria-live="polite" data-captcha-status
       :class="{ 'text-ok': state === 'passed', 'text-danger': state === 'failed' || state === 'error' || state === 'blocked', 'text-muted': !['passed', 'failed', 'error', 'blocked'].includes(state) }"
       x-text="statusText"></p>
</div>
