<div class="mb-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
    <h3 class="text-base font-semibold text-gray-950 dark:text-white">
        {{ __('Import shops') }}
    </h3>

    @if ($errors->any())
        <div class="mt-4 rounded-lg bg-danger-50 p-4 text-sm text-danger-600 dark:bg-danger-500/10 dark:text-danger-400">
            <ul class="list-disc space-y-1 ps-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form
        action="{{ route('admin.shops.import') }}"
        method="post"
        enctype="multipart/form-data"
        class="mt-4 space-y-4"
    >
        @csrf

        <div>
            <input
                type="file"
                name="sheet"
                required
                accept=".csv,.xls,.xlsx,text/csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                class="block w-full text-sm text-gray-950 file:me-4 file:rounded-lg file:border-0 file:bg-primary-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-primary-700 hover:file:bg-primary-100 dark:text-white dark:file:bg-primary-500/10 dark:file:text-primary-400"
            >
        </div>

        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ __('words.shop_import_help') }}
        </p>

        <div class="flex flex-wrap items-center gap-3">
            <button
                type="submit"
                class="inline-flex items-center justify-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-500"
            >
                {{ __('Import') }}
            </button>

            <a
                href="{{ asset('shop_import_demo.xlsx') }}"
                class="inline-flex items-center justify-center rounded-lg bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                target="_blank"
            >
                {{ __('Demo Excel') }}
            </a>
        </div>
    </form>
</div>
