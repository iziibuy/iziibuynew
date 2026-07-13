<?php

namespace App\Console\Commands;

use App\Mail\ElavonOnboardingMail;
use App\Models\PaymentMethodAccess;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

#[Signature('elavon:onboarding-mail:payment-method-accesses {--dry-run : Show recipients without sending mail} {--limit= : Maximum number of mails to send}')]
#[Description('Send Elavon onboarding update mail to payment method access users')]
class SendPaymentMethodAccessElavonOnboardingMail extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $sent = 0;
        $dryRun = (bool) $this->option('dry-run');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;

        PaymentMethodAccess::with('user')
            ->where(function ($query): void {
                $query->where('subscriptionMethod', '!=', PaymentMethodAccess::SUBSCRIPTION_METHOD_ELAVON)
                    ->orWhereNull('subscriptionMethod');
            })
            ->whereHas('user', function ($query): void {
                $query->whereNotNull('email');
            })
            ->chunkById(100, function ($paymentMethodAccesses) use (&$sent, $dryRun, $limit): bool {
                foreach ($paymentMethodAccesses as $paymentMethodAccess) {
                    if ($limit !== null && $sent >= $limit) {
                        return false;
                    }

                    $user = $paymentMethodAccess->user;
                    $name = trim((string) ($user->full_name ?? $user->name ?? ''));

                    if ($dryRun) {
                        $this->line(sprintf('[dry-run] %s <%s>', $name, $user->email));
                    } else {
                        Mail::to($user->email)->send(new ElavonOnboardingMail(
                            $name,
                            route('external.subscription.payment')
                        ));
                    }

                    $sent++;
                }

                return true;
            });

        $this->info(sprintf('%s %d payment method access onboarding mail(s).', $dryRun ? 'Found' : 'Sent', $sent));

        return self::SUCCESS;
    }
}
