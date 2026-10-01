<x-dynamic-component :component="$layout">
    <style>
        .checkout-designer { display: grid; grid-template-columns: minmax(320px, 420px) 1fr; gap: 1.25rem; align-items: start; }
        .checkout-designer-tools { max-height: calc(100vh - 140px); overflow-y: auto; }
        .checkout-designer-stage { position: sticky; top: 1rem; }
        .checkout-designer-frame-wrap { display: flex; justify-content: center; padding: 1rem; border-radius: 12px; background: #eef0ec; }
        .checkout-designer-frame { width: 100%; height: calc(100vh - 210px); min-height: 560px; border: 0; border-radius: 10px; background: #fff; box-shadow: 0 12px 40px rgba(20, 33, 28, 0.12); transition: width 0.25s ease; }
        .checkout-designer-frame.is-mobile { width: 390px; }
        .checkout-designer-frame.is-tablet { width: 768px; }
        @media (max-width: 1199.98px) {
            .checkout-designer { grid-template-columns: 1fr; }
            .checkout-designer-tools { max-height: none; }
            .checkout-designer-stage { position: static; }
        }
    </style>

    <div class="d-flex flex-wrap align-items-center justify-content-between mb-3" style="gap:0.75rem;">
        <div>
            <h3 class="mb-1">{{ __('Elavon checkout page designer') }}</h3>
            <p class="text-muted mb-0">{{ __('Changes appear in the preview as you edit. Save to publish them to your payment page.') }}</p>
        </div>
        <div class="d-flex" style="gap:0.5rem;">
            <a href="{{ $backUrl }}" class="btn btn-outline-secondary">{{ __('Back') }}</a>
            <button type="submit" form="checkout-design-form" class="btn btn-primary">{{ __('Save changes') }}</button>
        </div>
    </div>

    @unless ($usesCheckoutJs)
        <div class="alert alert-warning">
            {{ __('Your Elavon payment page is set to the hosted page. Switch to “Own CheckoutJS page” for customers to see this design.') }}
        </div>
    @endunless

    <div class="checkout-designer">
        <div class="card checkout-designer-tools">
            <div class="card-body">
                <form id="checkout-design-form" action="{{ $saveUrl }}" method="post" enctype="multipart/form-data">
                    @csrf
                    @include('dashboard.partials.checkoutjs-appearance-fields', [
                        'checkoutTheme' => $checkoutTheme,
                        'appearance' => $appearance,
                        'companyNamePlaceholder' => $companyNamePlaceholder,
                    ])
                    <button type="submit" class="btn btn-primary w-100 mt-3">{{ __('Save changes') }}</button>
                </form>
            </div>
        </div>

        <div class="checkout-designer-stage">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <strong>{{ __('Live preview') }}</strong>
                <div class="btn-group btn-group-sm" role="group" aria-label="{{ __('Preview size') }}">
                    <button type="button" class="btn btn-outline-secondary active" data-preview-size="">{{ __('Desktop') }}</button>
                    <button type="button" class="btn btn-outline-secondary" data-preview-size="is-tablet">{{ __('Tablet') }}</button>
                    <button type="button" class="btn btn-outline-secondary" data-preview-size="is-mobile">{{ __('Mobile') }}</button>
                </div>
            </div>
            <div class="checkout-designer-frame-wrap">
                <iframe id="checkout-preview-frame" class="checkout-designer-frame" src="{{ $previewUrl }}"
                    title="{{ __('Checkout page preview') }}"></iframe>
            </div>
            <small class="text-muted d-block mt-2">{{ __('Sample order and card details. No payment is made from the preview.') }}</small>
        </div>
    </div>

    <script>
        (function () {
            var form = document.getElementById('checkout-design-form');
            var frame = document.getElementById('checkout-preview-frame');
            var logoInput = document.getElementById('checkout_logo');
            var removeLogo = document.getElementById('checkout_remove_logo');
            var savedLogoUrl = @json($logoUrl);
            var uploadedLogoUrl = null;
            var pending = false;

            function value(name) {
                var field = form.querySelector('[name="' + name + '"]');
                return field ? field.value : '';
            }

            function currentLogo() {
                if (uploadedLogoUrl) {
                    return uploadedLogoUrl;
                }
                if (removeLogo && removeLogo.checked) {
                    return null;
                }
                return savedLogoUrl;
            }

            function state() {
                return {
                    company_name: value('checkout_company_name'),
                    heading: value('checkout_heading'),
                    lead: value('checkout_lead'),
                    pay_button_label: value('checkout_pay_button_label'),
                    cancel_label: value('checkout_cancel_label'),
                    summary_note: value('checkout_summary_note'),
                    footer: value('checkout_footer'),
                    primary_color: value('checkout_primary_color'),
                    secondary_color: value('checkout_secondary_color'),
                    background_color: value('checkout_background_color'),
                    custom_css: value('checkout_custom_css'),
                    logo_url: currentLogo(),
                };
            }

            function push() {
                if (pending) {
                    return;
                }
                pending = true;
                window.requestAnimationFrame(function () {
                    pending = false;
                    if (frame.contentWindow) {
                        frame.contentWindow.postMessage(
                            { type: 'checkoutjs-preview:update', state: state() },
                            window.location.origin
                        );
                    }
                });
            }

            form.addEventListener('input', push);
            form.addEventListener('change', push);

            if (logoInput) {
                logoInput.addEventListener('change', function () {
                    var file = logoInput.files && logoInput.files[0];
                    if (!file) {
                        uploadedLogoUrl = null;
                        push();
                        return;
                    }
                    var reader = new FileReader();
                    reader.onload = function () {
                        uploadedLogoUrl = reader.result;
                        push();
                    };
                    reader.readAsDataURL(file);
                });
            }

            window.addEventListener('message', function (event) {
                if (event.origin === window.location.origin
                    && event.data && event.data.type === 'checkoutjs-preview:ready') {
                    push();
                }
            });
            frame.addEventListener('load', push);

            document.querySelectorAll('[data-preview-size]').forEach(function (button) {
                button.addEventListener('click', function () {
                    document.querySelectorAll('[data-preview-size]').forEach(function (other) {
                        other.classList.toggle('active', other === button);
                    });
                    frame.classList.remove('is-mobile', 'is-tablet');
                    var size = button.getAttribute('data-preview-size');
                    if (size) {
                        frame.classList.add(size);
                    }
                });
            });
        })();
    </script>
</x-dynamic-component>
