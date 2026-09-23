<?php

namespace App\Payment\Elavon;

use App\Models\PaymentApi;
use App\Support\Voyager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final class CheckoutJsTheme
{
    public const DEFAULT_PRIMARY = '#14352C';

    public const DEFAULT_SECONDARY = '#D7B56A';

    public const DEFAULT_BACKGROUND = '#F3F1EB';

    public const CUSTOM_CSS_MAX_LENGTH = 20000;

    /**
     * @param  array<string, mixed>  $appearance
     */
    public function __construct(private array $appearance = []) {}

    public static function fromApi(?PaymentApi $api): self
    {
        $stored = $api?->checkoutjs_appearance;

        return new self(is_array($stored) ? $stored : []);
    }

    public static function disk(): string
    {
        return (string) (config('iziibuy.storage_disk')
            ?? config('voyager.storage.disk')
            ?? config('filesystems.default', 'public'));
    }

    public function companyName(string $fallback): string
    {
        return $this->text('company_name', $fallback, 120);
    }

    public function heading(string $fallback): string
    {
        return $this->text('heading', $fallback, 120);
    }

    public function lead(string $fallback): string
    {
        return $this->text('lead', $fallback, 400);
    }

    public function payButtonLabel(string $fallback): string
    {
        return $this->text('pay_button_label', $fallback, 80);
    }

    public function cancelLabel(string $fallback): string
    {
        return $this->text('cancel_label', $fallback, 80);
    }

    public function summaryNote(string $fallback): string
    {
        return $this->text('summary_note', $fallback, 200);
    }

    public function footer(): ?string
    {
        $footer = $this->text('footer', '', 300);

        return $footer !== '' ? $footer : null;
    }

    public function customCss(): string
    {
        return self::sanitizeCss($this->appearance['custom_css'] ?? null) ?? '';
    }

    public function primary(): string
    {
        return self::normalizeHex($this->appearance['primary_color'] ?? null, self::DEFAULT_PRIMARY);
    }

    public function secondary(): string
    {
        return self::normalizeHex($this->appearance['secondary_color'] ?? null, self::DEFAULT_SECONDARY);
    }

    public function background(): string
    {
        return self::normalizeHex($this->appearance['background_color'] ?? null, self::DEFAULT_BACKGROUND);
    }

    public function primaryDeep(): string
    {
        return self::mix($this->primary(), '#000000', 0.28);
    }

    public function primarySoft(): string
    {
        return self::mix($this->primary(), '#FFFFFF', 0.18);
    }

    public function onPrimary(): string
    {
        [$red, $green, $blue] = self::rgb($this->primary());
        $luminance = ((0.299 * $red) + (0.587 * $green) + (0.114 * $blue)) / 255;

        return $luminance > 0.62 ? '#14211C' : '#FFFFFF';
    }

    public function hasCustomLogo(): bool
    {
        return is_string($this->appearance['logo'] ?? null) && $this->appearance['logo'] !== '';
    }

    public function logoUrl(?string $pluginLogo = null): ?string
    {
        $path = $this->hasCustomLogo() ? (string) $this->appearance['logo'] : $pluginLogo;

        if (! is_string($path) || $path === '') {
            return null;
        }

        $url = Voyager::image($path);

        return $url !== '' ? $url : null;
    }

    /**
     * Useful CSS selectors shown in the dashboard for merchants.
     *
     * @return array<string, string>
     */
    public static function cssSelectorHints(): array
    {
        return [
            'body / .shell' => 'Page background and outer layout',
            '.checkout' => 'Main checkout card',
            '.summary' => 'Left order summary panel',
            '.pay' => 'Right payment form panel',
            '.amount strong' => 'Large amount text',
            '.btn-primary' => 'Pay button',
            '.btn-ghost' => 'Cancel link',
            '.stripe-field / .stripe-shell' => 'Card number, expiry, and CVV fields',
            '.footnote' => 'Footer text under the form',
            '.trust' => 'Security notes under the form',
            '.brands' => 'Accepted card logos above the form',
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function persist(array $input, ?UploadedFile $logo, bool $removeLogo): array
    {
        $next = $this->appearance;
        $next['company_name'] = self::blankToNull(strip_tags((string) ($input['checkout_company_name'] ?? '')), 120);
        $next['heading'] = self::blankToNull(strip_tags((string) ($input['checkout_heading'] ?? '')), 120);
        $next['lead'] = self::blankToNull(strip_tags((string) ($input['checkout_lead'] ?? '')), 400);
        $next['pay_button_label'] = self::blankToNull(strip_tags((string) ($input['checkout_pay_button_label'] ?? '')), 80);
        $next['cancel_label'] = self::blankToNull(strip_tags((string) ($input['checkout_cancel_label'] ?? '')), 80);
        $next['summary_note'] = self::blankToNull(strip_tags((string) ($input['checkout_summary_note'] ?? '')), 200);
        $next['footer'] = self::blankToNull(strip_tags((string) ($input['checkout_footer'] ?? '')), 300);
        $next['custom_css'] = self::sanitizeCss(isset($input['checkout_custom_css']) ? (string) $input['checkout_custom_css'] : null);
        $next['primary_color'] = self::normalizeHex($input['checkout_primary_color'] ?? null, self::DEFAULT_PRIMARY);
        $next['secondary_color'] = self::normalizeHex($input['checkout_secondary_color'] ?? null, self::DEFAULT_SECONDARY);
        $next['background_color'] = self::normalizeHex($input['checkout_background_color'] ?? null, self::DEFAULT_BACKGROUND);

        $currentLogo = is_string($next['logo'] ?? null) ? $next['logo'] : null;

        if ($removeLogo || $logo instanceof UploadedFile) {
            self::deleteStoredLogo($currentLogo);
            $next['logo'] = null;
        }

        if ($logo instanceof UploadedFile) {
            $next['logo'] = $logo->store('checkoutjs-logos', self::disk());
        }

        return $next;
    }

    public static function sanitizeCss(mixed $css): ?string
    {
        if (! is_string($css)) {
            return null;
        }

        $css = str_replace(["\0", '<', '>'], '', $css);
        $css = preg_replace('/expression\s*\(/i', '', $css) ?? '';
        $css = preg_replace('/javascript\s*:/i', '', $css) ?? '';
        $css = preg_replace('/@import\b/i', '', $css) ?? '';
        $css = trim($css);

        if ($css === '') {
            return null;
        }

        return mb_substr($css, 0, self::CUSTOM_CSS_MAX_LENGTH);
    }

    private function text(string $key, string $fallback, int $limit): string
    {
        $value = trim(strip_tags((string) ($this->appearance[$key] ?? '')));

        if ($value === '') {
            return $fallback;
        }

        return mb_substr($value, 0, $limit);
    }

    private static function blankToNull(string $value, int $limit): ?string
    {
        $value = trim(mb_substr($value, 0, $limit));

        return $value === '' ? null : $value;
    }

    private static function normalizeHex(mixed $color, string $fallback): string
    {
        $color = strtoupper(trim((string) $color));

        if (preg_match('/^#[0-9A-F]{6}$/', $color) !== 1) {
            return strtoupper($fallback);
        }

        return $color;
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    private static function mix(string $hex, string $with, float $amount): string
    {
        [$red, $green, $blue] = self::rgb($hex);
        [$mixRed, $mixGreen, $mixBlue] = self::rgb($with);

        return sprintf(
            '#%02X%02X%02X',
            (int) round(($red * (1 - $amount)) + ($mixRed * $amount)),
            (int) round(($green * (1 - $amount)) + ($mixGreen * $amount)),
            (int) round(($blue * (1 - $amount)) + ($mixBlue * $amount)),
        );
    }

    private static function deleteStoredLogo(?string $path): void
    {
        if (! is_string($path) || ! str_starts_with($path, 'checkoutjs-logos/')) {
            return;
        }

        Storage::disk(self::disk())->delete($path);
    }
}
