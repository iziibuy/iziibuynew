<?php

namespace App\Http\Controllers\Admin;

use App\Filament\Pages\RetailerWithdrawalsPage;
use App\Http\Controllers\Controller;
use App\Models\User;
use Error;
use Exception;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RetailerAdminController extends Controller
{
    public function withdrawalsBalance(Request $request, User $user): RedirectResponse
    {
        $request->validate([
            'amount' => 'required|integer|lt:'.$user->totalBalance(),
            'trnx_id' => 'nullable|string|max:50',
            'date' => 'required|date',
        ]);

        try {
            $withdrawal = $user->withdraw((int) $request->amount);
            $withdrawal->createMetas([
                'trnx_id' => $request->trnx_id,
                'date' => $request->date,
            ]);

            Notification::make()
                ->title(__('Withdrawal request placed'))
                ->success()
                ->send();

            return redirect()->to(RetailerWithdrawalsPage::getUrl().'?user='.$user->id);
        } catch (Exception|Error $e) {
            Notification::make()
                ->title($e->getMessage())
                ->danger()
                ->send();

            return redirect()->back();
        }
    }
}
