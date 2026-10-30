<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @return array<string, array<string, string>>
     */
    protected function translations(): array
    {
        return [
            'trial_ended_please_pay' => [
                'norwegian' => 'Prøveperioden din er avsluttet. Abonner for å fortsette.',
                'english' => 'Your free trial has ended. Please subscribe to continue.',
                'danish' => 'Din prøveperiode er slut. Abonner for at fortsætte.',
                'swedish' => 'Din provperiod har avslutats. Prenumerera för att fortsätta.',
                'spanish' => 'Tu prueba gratuita ha terminado. Suscríbete para continuar.',
            ],
            'trial_days_left' => [
                'norwegian' => 'Gratis prøveperiode: :days dager igjen.',
                'english' => 'Free trial: :days days left.',
                'danish' => 'Gratis prøveperiode: :days dage tilbage.',
                'swedish' => 'Gratis provperiod: :days dagar kvar.',
                'spanish' => 'Prueba gratuita: quedan :days días.',
            ],
            'subscribe_now' => [
                'norwegian' => 'Abonner nå',
                'english' => 'Subscribe now',
                'danish' => 'Abonner nu',
                'swedish' => 'Prenumerera nu',
                'spanish' => 'Suscribirse ahora',
            ],
        ];
    }

    public function up(): void
    {
        $columns = array_flip(Schema::getColumnListing('languages'));

        foreach ($this->translations() as $key => $values) {
            $values = array_intersect_key($values, $columns);
            $existing = DB::table('languages')->where('key', $key)->exists();

            if ($existing) {
                DB::table('languages')->where('key', $key)->update($values + ['updated_at' => now()]);
            } else {
                DB::table('languages')->insert($values + ['key' => $key, 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        Cache::forget('lang');
    }

    public function down(): void
    {
        DB::table('languages')->whereIn('key', array_keys($this->translations()))->delete();

        Cache::forget('lang');
    }
};
