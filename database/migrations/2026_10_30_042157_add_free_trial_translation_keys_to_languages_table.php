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
            'start_free_trial_btn' => [
                'norwegian' => 'Start 7 dagers gratis prøveperiode',
                'english' => 'Start 7-day free trial',
                'danish' => 'Start 7 dages gratis prøveperiode',
                'swedish' => 'Starta 7 dagars gratis provperiod',
                'spanish' => 'Iniciar prueba gratuita de 7 días',
            ],
            'free_trial_info' => [
                'norwegian' => 'Kortet ditt lagres nå, men belastes først etter 7 dager.',
                'english' => 'Your card is saved now but will only be charged after 7 days.',
                'danish' => 'Dit kort gemmes nu, men debiteres først efter 7 dage.',
                'swedish' => 'Ditt kort sparas nu men debiteras först efter 7 dagar.',
                'spanish' => 'Tu tarjeta se guarda ahora, pero solo se cobrará después de 7 días.',
            ],
            'trial_ends_on' => [
                'norwegian' => 'Prøveperioden din avsluttes :date',
                'english' => 'Your free trial ends on :date',
                'danish' => 'Din prøveperiode slutter :date',
                'swedish' => 'Din provperiod avslutas :date',
                'spanish' => 'Tu prueba gratuita termina el :date',
            ],
            'trial_already_used' => [
                'norwegian' => 'Du har allerede brukt gratis prøveperiode.',
                'english' => 'You have already used your free trial.',
                'danish' => 'Du har allerede brugt din gratis prøveperiode.',
                'swedish' => 'Du har redan använt din gratis provperiod.',
                'spanish' => 'Ya has utilizado tu prueba gratuita.',
            ],
            'trial_started_success' => [
                'norwegian' => 'Gratis prøveperiode startet.',
                'english' => 'Free trial started.',
                'danish' => 'Gratis prøveperiode startet.',
                'swedish' => 'Gratis provperiod startad.',
                'spanish' => 'Prueba gratuita iniciada.',
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
