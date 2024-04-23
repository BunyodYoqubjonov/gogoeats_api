<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnsToShopDeliveryZone extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('shop_delivery_zone', function (Blueprint $table) {
            $table->float('business_delivery_fee')->nullable();
            $table->float('customer_delivery_fee')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('shop_delivery_zone', function (Blueprint $table) {
            $table->dropColumn('business_delivery_fee');
            $table->dropColumn('customer_delivery_fee');
        });
    }
}
