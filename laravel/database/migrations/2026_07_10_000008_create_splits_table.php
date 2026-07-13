<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration{
public function up():void{
Schema::create('splits',function(Blueprint $table){
$table->id();
$table->foreignId('instrument_id')->constrained()->cascadeOnDelete();
$table->date('split_date')->index();
$table->decimal('ratio_from',12,4);
$table->decimal('ratio_to',12,4);
$table->timestampsTz();
});}
public function down():void{Schema::dropIfExists('splits');}
};