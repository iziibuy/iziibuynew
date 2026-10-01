<script>
    (function () {
        var root = document.documentElement;
        var customCss = document.getElementById('checkoutjs-custom-css');
        var logo = document.querySelector('[data-preview-logo]');
        var initial = document.querySelector('[data-preview-initial]');
        var defaults = {
            primary: @json(\App\Payment\Elavon\CheckoutJsTheme::DEFAULT_PRIMARY),
            secondary: @json(\App\Payment\Elavon\CheckoutJsTheme::DEFAULT_SECONDARY),
            background: @json(\App\Payment\Elavon\CheckoutJsTheme::DEFAULT_BACKGROUND),
        };

        document.getElementById('checkout-form').addEventListener('submit', function (event) {
            event.preventDefault();
        });
        document.querySelectorAll('a[href="#"]').forEach(function (link) {
            link.addEventListener('click', function (event) {
                event.preventDefault();
            });
        });

        function hex(value, fallback) {
            value = String(value || '').trim().toUpperCase();
            return /^#[0-9A-F]{6}$/.test(value) ? value : fallback;
        }

        function rgb(value) {
            value = value.replace('#', '');
            return [0, 2, 4].map(function (offset) {
                return parseInt(value.substr(offset, 2), 16);
            });
        }

        function mix(value, withValue, amount) {
            var a = rgb(value);
            var b = rgb(withValue);
            return '#' + a.map(function (channel, index) {
                return Math.round((channel * (1 - amount)) + (b[index] * amount))
                    .toString(16).padStart(2, '0');
            }).join('').toUpperCase();
        }

        function onPrimary(value) {
            var c = rgb(value);
            var luminance = ((0.299 * c[0]) + (0.587 * c[1]) + (0.114 * c[2])) / 255;
            return luminance > 0.62 ? '#14211C' : '#FFFFFF';
        }

        function sanitizeCss(css) {
            return String(css || '')
                .replace(/[\0<>]/g, '')
                .replace(/expression\s*\(/gi, '')
                .replace(/javascript\s*:/gi, '')
                .replace(/@import\b/gi, '');
        }

        function apply(state) {
            var primary = hex(state.primary_color, defaults.primary);
            root.style.setProperty('--forest', primary);
            root.style.setProperty('--forest-deep', mix(primary, '#000000', 0.28));
            root.style.setProperty('--forest-soft', mix(primary, '#FFFFFF', 0.18));
            root.style.setProperty('--on-primary', onPrimary(primary));
            root.style.setProperty('--gold', hex(state.secondary_color, defaults.secondary));
            root.style.setProperty('--paper', hex(state.background_color, defaults.background));

            document.querySelectorAll('[data-preview-text]').forEach(function (element) {
                var key = element.getAttribute('data-preview-text');
                if (!(key in state)) {
                    return;
                }
                var value = String(state[key] || '').trim();
                element.textContent = value !== '' ? value : (element.getAttribute('data-default') || '');
            });

            if (initial && 'company_name' in state) {
                var name = String(state.company_name || '').trim()
                    || (document.querySelector('[data-preview-text="company_name"]').getAttribute('data-default') || '');
                initial.textContent = name.charAt(0).toUpperCase() || '•';
            }

            if (logo && 'logo_url' in state) {
                if (state.logo_url) {
                    logo.src = state.logo_url;
                    logo.style.display = '';
                } else {
                    logo.removeAttribute('src');
                    logo.style.display = 'none';
                }
            }

            if (customCss && 'custom_css' in state) {
                customCss.textContent = sanitizeCss(state.custom_css);
            }
        }

        window.addEventListener('message', function (event) {
            if (event.origin !== window.location.origin) {
                return;
            }
            var data = event.data || {};
            if (data.type === 'checkoutjs-preview:update' && data.state) {
                apply(data.state);
            }
        });

        if (window.parent && window.parent !== window) {
            window.parent.postMessage({ type: 'checkoutjs-preview:ready' }, window.location.origin);
        }
    })();
</script>
