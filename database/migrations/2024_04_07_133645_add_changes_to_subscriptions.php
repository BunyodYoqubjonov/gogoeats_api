<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddChangesToSubscriptions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('title')->nullable()->change();
            $table->integer('product_limit')->nullable()->change();
            $table->integer('order_limit')->nullable()->change();
            $table->boolean('with_report')->nullable()->change();
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
            $table->string('title')->change();
            $table->integer('product_limit')->change();
            $table->integer('order_limit')->change();
            $table->boolean('with_report')->change();
        });
    }
}
