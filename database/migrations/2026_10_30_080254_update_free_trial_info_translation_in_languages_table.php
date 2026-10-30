<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $values = array_intersect_key([
            'norwegian' => 'Ingen kort nødvendig. Du får full tilgang i 7 dager.',
            'english' => 'No card needed. You get full access for 7 days.',
            'danish' => 'Intet kort nødvendigt. Du får fuld adgang i 7 dage.',
            'swedish' => 'Inget kort behövs. Du får full åtkomst i 7 dagar.',
            'spanish' => 'No se necesita tarjeta. Tienes acceso completo durante 7 días.',
        ], array_flip(Schema::getColumnListing('languages')));

        DB::table('languages')->where('key', 'free_trial_info')->update($values + ['updated_at' => now()]);

        Cache::forget('lang');
    }

    public function down(): void
    {
        Cache::forget('lang');
    }
};
