<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $incassoClass = 'App\Models\Incasso';
        $paymentClass = 'App\Models\Payment';
        $donationClass = 'App\Models\Donation';

        // Receipts: Payment → Incasso (ids unchanged)
        DB::table('receipts')
            ->where('receivable_type', $paymentClass)
            ->update(['receivable_type' => $incassoClass]);

        // Prima nota: Payment → Incasso
        DB::table('prima_nota_entries')
            ->where('entryable_type', $paymentClass)
            ->update(['entryable_type' => $incassoClass]);

        // Donations: copy to incassi and build donation_id → incasso_id map
        $donationIdsWithReceipt = DB::table('receipts')
            ->where('receivable_type', $donationClass)
            ->pluck('receivable_id')
            ->flip()
            ->all();

        $donations = DB::table('donations')->orderBy('id')->get();
        $donationToIncasso = [];

        foreach ($donations as $d) {
            $receiptIssuedAt = isset($donationIdsWithReceipt[$d->id])
                ? $d->donated_at
                : null;

            $id = DB::table('incassi')->insertGetId([
                'member_id' => $d->member_id,
                'subscription_id' => null,
                'amount' => $d->amount,
                'paid_at' => $d->donated_at,
                'payment_method_id' => $d->payment_method_id,
                'description' => $d->description,
                'receipt_issued_at' => $receiptIssuedAt,
                'genera_prima_nota' => false,
                'type' => 'donazione',
                'created_at' => $d->created_at,
                'updated_at' => $d->updated_at,
            ]);

            $donationToIncasso[$d->id] = $id;
        }

        // Receipts: Donation → Incasso (new ids)
        foreach ($donationToIncasso as $donationId => $incassoId) {
            DB::table('receipts')
                ->where('receivable_type', $donationClass)
                ->where('receivable_id', $donationId)
                ->update([
                    'receivable_type' => $incassoClass,
                    'receivable_id' => $incassoId,
                ]);
        }

        Schema::dropIfExists('donations');
    }

    public function down(): void
    {
        // Restoring donations would require storing old donation data; not implemented.
        // Only recreate empty donations table for schema consistency.
        Schema::create('donations', function ($table) {
            $table->id();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->dateTime('donated_at');
            $table->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        DB::table('receipts')
            ->where('receivable_type', 'App\Models\Incasso')
            ->update(['receivable_type' => 'App\Models\Payment']);

        DB::table('prima_nota_entries')
            ->where('entryable_type', 'App\Models\Incasso')
            ->update(['entryable_type' => 'App\Models\Payment']);
    }
};
