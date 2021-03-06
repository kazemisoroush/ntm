<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAddressesTable extends Migration {

    /**
     * Get table name.
     *
     * @return string
     */
    function getTable()
    {
        return table_name('addresses');
    }

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $hostsTable = table_name('hosts');

        Schema::create($this->getTable(), function (Blueprint $table) use ($hostsTable) {
            $table->unsignedBigInteger('id', true);
            $table->string('address');
            $table->string('type');
            $table->string('vendor')->nullable();

            $table->unsignedBigInteger('host_id')->nullable();
            $table->foreign('host_id')
                  ->references('id')
                  ->on($hostsTable)
                  ->onDelete('set null')
                  ->onUpdate('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table($this->getTable(), function (Blueprint $table) {
            $table->dropForeign(['host_id']);
        });

        Schema::drop($this->getTable());
    }
}
