<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSubscriptionIdToBanners extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->foreignId('subscription_id')
                ->nullable()
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->dropForeign('banners_subscription_id_foreign');
            $table->dropColumn('subscription_id');
        });
    }
}
