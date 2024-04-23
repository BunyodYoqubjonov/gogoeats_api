<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNewColumnsToSubscriptions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->double('business_delivery_fee')->nullable();
            $table->double('customer_delivery_fee')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('business_delivery_fee');
            $table->dropColumn('customer_delivery_fee');
        });
    }
}
