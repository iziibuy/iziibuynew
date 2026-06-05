<x-filament-panels::page>
    @php
        /** @var \App\Models\Shop $shop */
        $shop = $this->getRecord();
        $user = $shop->user;
    @endphp

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/summernote/0.8.20/summernote-lite.min.css"
        integrity="sha512-ZbehZMIlGA8CTIOtdE+M81uj3mrcgyrh6ZFeG33A4FHECakGrOsTPlPQ8ijjLkxgImrdmSVUHn1j+ApjodYZow=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />

    @if (session()->has('success'))
        <div class="mb-4 rounded-lg border border-success-200 bg-success-50 px-4 py-3 text-sm text-success-700">
            {{ session('success') }}
        </div>
    @endif

    <x-filament::section>
        <div class="shop-advance-edit">
            <div class="mb-6 flex flex-wrap gap-2" id="nav-tab" role="tablist">
                <button class="shop-advance-edit-tab" id="company-tab" type="button"
                    onclick="storeLastActiveTab('company')">{{ __('words.company') }}</button>
                <button class="shop-advance-edit-tab is-active" id="store-tab" type="button"
                    onclick="storeLastActiveTab('store')">{{ __('words.store') }}</button>
                <button class="shop-advance-edit-tab" id="payment-tab" type="button"
                    onclick="storeLastActiveTab('payment')">{{ __('words.payment') }}</button>
                <button class="shop-advance-edit-tab" id="general-tab" type="button"
                    onclick="storeLastActiveTab('general')">{{ __('words.general') }}</button>
                <button class="shop-advance-edit-tab" id="menus-tab" type="button"
                    onclick="storeLastActiveTab('menus')">{{ __('words.menus') }}</button>
                <button class="shop-advance-edit-tab" id="settings-tab" type="button"
                    onclick="storeLastActiveTab('settings')">{{ __('words.settings') }}</button>
                <button class="shop-advance-edit-tab" id="links-tab" type="button"
                    onclick="storeLastActiveTab('links')">{{ __('words.links') }}</button>
                <button class="shop-advance-edit-tab" id="colors-tab" type="button"
                    onclick="storeLastActiveTab('colors')">{{ __('words.colors') }}</button>
            </div>

            <form id="form" action="{{ route('admin.profile.update', $shop) }}" method="POST" enctype="multipart/form-data">
                @csrf

                <div class="forms" id="nav-tabContent">
                    <div id="store" role="tabpanel">
                        @include('dashboard.shop.profile.tabs.store_info_forms', ['shop' => $shop])
                    </div>
                    <div id="payment" hidden>
                        @include('dashboard.shop.profile.tabs.payment_info_forms', ['shop' => $shop])
                    </div>
                    <div id="company" hidden>
                        @include('dashboard.shop.profile.tabs.company_info_forms', ['shop' => $shop])
                    </div>
                    <div id="general" role="tabpanel" hidden>
                        @include('dashboard.shop.profile.tabs.general_info_forms', ['shop' => $shop])
                    </div>
                    <div id="settings" role="tabpanel" hidden>
                        @include('dashboard.shop.profile.tabs.settings', ['shop' => $shop])
                    </div>
                    <div id="menus" role="tabpanel" hidden>
                        @include('dashboard.shop.profile.tabs.menus', ['shop' => $shop])
                    </div>
                    <div id="links" role="tabpanel" hidden>
                        <livewire:links :shop="$shop" />
                    </div>
                    <div id="colors" role="tabpanel" hidden>
                        @include('dashboard.shop.profile.tabs.colors', ['shop' => $shop])
                    </div>

                    <button class="shop-advance-edit-submit" type="submit">
                        {!! __('words.change_btn') !!}
                    </button>
                </div>
            </form>
        </div>
    </x-filament::section>

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/summernote/0.8.20/summernote-lite.min.js"
        integrity="sha512-lVkQNgKabKsM1DA/qbhJRFQU8TuwkLF2vSN3iU/c7+iayKs08Y8GXqfFxxTZr1IcpMovXnf2N/ZZoMgmZep1YQ=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script>
        function storeLastActiveTab(tabId) {
            const tabs = ['store', 'company', 'settings', 'links', 'menus', 'payment', 'general', 'colors'];

            for (const tab of tabs) {
                const panel = document.getElementById(tab);
                const button = document.getElementById(tab + '-tab');

                if (!panel || !button) {
                    continue;
                }

                if (tab === tabId) {
                    button.classList.add('is-active');
                    panel.hidden = false;
                } else {
                    button.classList.remove('is-active');
                    panel.hidden = true;
                }
            }

            localStorage.setItem('shopAdvanceEditTab', tabId);
        }

        function getLastActiveTab() {
            return localStorage.getItem('shopAdvanceEditTab');
        }

        function initSummernote() {
            if (typeof $ === 'undefined') {
                return;
            }

            if ($('#terms').length && !$('#terms').next('.note-editor').length) {
                $('#terms').summernote({ height: 300 });
            }

            if ($('#description').length && !$('#description').next('.note-editor').length) {
                $('#description').summernote({ height: 300 });
            }
        }

        function initLocationsSelect() {
            if (typeof $ === 'undefined' || !$('#locations').length) {
                return;
            }

            if ($('#locations').hasClass('select2-hidden-accessible')) {
                return;
            }

            $('#locations').select2({
                width: '100%',
                dropdownParent: document.querySelector('.shop-advance-edit'),
            });
        }

        function initLocationModeToggle() {
            if (typeof $ === 'undefined') {
                return;
            }

            if ($("select[name='selling_location_mode']").val() < 1) {
                $('#locationsdiv').hide();
            }

            $("select[name='selling_location_mode']").off('change.shopAdvanceEdit').on('change.shopAdvanceEdit', function(event) {
                $('#locationsdiv').hide();

                if (event.target.value > 0) {
                    $('#locationsdiv').show();
                }
            });
        }

        function initTabPlugins(tabId) {
            if (tabId === 'store') {
                initSummernote();
            }

            if (tabId === 'general') {
                initLocationsSelect();
                initLocationModeToggle();
            }
        }

        const originalStoreLastActiveTab = storeLastActiveTab;
        storeLastActiveTab = function(tabId) {
            originalStoreLastActiveTab(tabId);
            initTabPlugins(tabId);
        };

        document.addEventListener('DOMContentLoaded', () => {
            const lastActiveTab = getLastActiveTab() ?? 'store';
            storeLastActiveTab(lastActiveTab);
        });
    </script>
</x-filament-panels::page>
