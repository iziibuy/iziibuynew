<?php

namespace App\Listeners;

use App\Models\Enterprise;
use App\Models\RetailerType;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Iziibuy;

class CreateOrUpdateAndChargeForShopIfNeeded
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @return void
     */
    public function handle($event)
    {

        switch ($event->user->role_id) {
            case 5:  // Retailer and Partner
            case 7:
            case 3:
                $this->create_shop_if_there_is_no_shop($event->user);
                break;

            default:
                // code...
                $this->if_there_is_shop_disable_it_on_downgrade($event->user);
                break;
        }
    }

    private function create_shop_if_there_is_no_shop(User $user)
    {

        if ($user->shop == null) {
            DB::beginTransaction();
            $shop = $user->shop()->create([
                'user_name' => uniqid(),
                'retailer_id' => $user->id,
                'terms' => setting('terms.no'),
                'contract_signed' => true,
                'establishment' => true,
                'can_provide_service' => false,
            ]);

            if (! $user->retailer) {
                $user->retailer()->create([
                    'parent_id' => $user->partner_id,
                    'type' => $user->role_id == 5 ? RetailerType::where('rank', 0)->first()->id : RetailerType::where('rank', 1)->first()->id,
                ]);
            }
            $enterprise = Enterprise::latest()->first();
            if (! $enterprise) {
                DB::rollBack();
                throw new Exception('Enterprise billing account is not configured.');
            }

            $chargeUrl = rtrim((string) config('app.url'), '/').'/api/enterprise/'.$enterprise->unqid.'/charge';
            $subscription = (object) Http::acceptJson()->post($chargeUrl, [
                'amount' => Iziibuy::needToCharge(123.75),
                'details' => [
                    'shop_id' => $shop->id,
                    'comment' => 'New shop',
                ],
            ])->json();

            if (($subscription->status ?? false) === true) {
                $shop->update([
                    'status' => 1,
                ]);
                DB::commit();
            } else {

                DB::rollBack();
                if ($user->wasChanged() == false) {
                    $user->delete();
                }
                throw new Exception('Card failure. Not possible to generate shop. 
                - No partner can be assigned to system.');
            }
        }
    }

    private function if_there_is_shop_disable_it_on_downgrade(User $user)
    {
        if ($user->shop) {
            $user->shop->update([
                'status' => 0,
            ]);
        }
    }
}
