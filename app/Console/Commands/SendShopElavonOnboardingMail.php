<?php

namespace App\Console\Commands;

use App\Mail\ElavonOnboardingMail;
use App\Models\Shop;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

#[Signature('elavon:onboarding-mail:shops {--dry-run : Show recipients without sending mail} {--limit= : Maximum number of mails to send}')]
#[Description('Send Elavon onboarding update mail to shop users')]
class SendShopElavonOnboardingMail extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $sent = 0;
        $dryRun = (bool) $this->option('dry-run');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;

        Shop::with('user')
            ->where(function ($query): void {
                $query->where('subscriptionMethod', '!=', Shop::SUBSCRIPTION_METHOD_ELAVON)
                    ->orWhereNull('subscriptionMethod');
            })
            ->whereHas('user', function ($query): void {
                $query->whereNotNull('email');
            })
            ->chunkById(100, function ($shops) use (&$sent, $dryRun, $limit): bool {
                foreach ($shops as $shop) {
                    if ($limit !== null && $sent >= $limit) {
                        return false;
                    }

                    $user = $shop->user;
                    $name = trim((string) ($user->full_name ?? $user->name ?? ''));

                    if ($dryRun) {
                        $this->line(sprintf('[dry-run] %s <%s>', $name, $user->email));
                    } else {
                        Mail::to($user->email)->send(new ElavonOnboardingMail(
                            $name,
                            route('shop.subscription.payment')
                        ));
                    }

                    $sent++;
                }

                return true;
            });

        $this->info(sprintf('%s %d shop onboarding mail(s).', $dryRun ? 'Found' : 'Sent', $sent));

        return self::SUCCESS;
    }
}
