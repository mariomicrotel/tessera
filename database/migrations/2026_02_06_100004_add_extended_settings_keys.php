<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $defaults = [
            'nome_associazione' => 'La mia Associazione ETS',
            'indirizzo_associazione' => '',
            'codice_fiscale_associazione' => '',
            'partita_iva_associazione' => '',
            'causale_default_donazione' => 'Erogazione liberale',
            'causale_default_quota' => 'Quota associativa',
            'causale_default_rimborso' => 'Rimborso spese',
            'conto_prima_nota_quote' => '2.1',
            'conto_prima_nota_donazioni' => '2.2',
            'conto_prima_nota_rimborsi' => '3.1',
        ];

        foreach ($defaults as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value]
            );
        }
    }

    public function down(): void
    {
        $keys = [
            'nome_associazione',
            'indirizzo_associazione',
            'codice_fiscale_associazione',
            'partita_iva_associazione',
            'causale_default_donazione',
            'causale_default_quota',
            'causale_default_rimborso',
            'conto_prima_nota_quote',
            'conto_prima_nota_donazioni',
            'conto_prima_nota_rimborsi',
        ];
        DB::table('settings')->whereIn('key', $keys)->delete();
    }
};
