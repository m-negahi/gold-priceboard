<x-layouts.panel title="تیکت جدید" current="tickets" :breadcrumbs="['پنل', 'تیکت‌ها', 'جدید']">
    <div class="mx-auto w-full max-w-3xl">
        <h1 class="text-2xl font-extrabold text-ink">تیکت جدید برای کاربر</h1>
        <form method="POST" action="{{ route('panel.tickets.store') }}" enctype="multipart/form-data" class="mt-5 space-y-4" x-data="{ type: @js(old('type', 'support')) }" data-ticket-create>
            @csrf
            <section class="rounded-2xl bg-surface card-p-lg shadow-xs space-y-4">
                <x-form.field name="user_id" label="کاربر" required><x-form.select name="user_id" :options="$ownerOptions" :value="old('user_id')" /></x-form.field>
                @include('ticket::account.request-fields')
            </section>
            @can('tickets.manage')<x-panel.button type="submit">ثبت تیکت</x-panel.button>@endcan
        </form>
    </div>
</x-layouts.panel>
