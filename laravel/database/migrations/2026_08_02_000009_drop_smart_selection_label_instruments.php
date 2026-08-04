<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('smart_selection_label_instruments');
    }

    public function down(): void
    {
        // The former snapshot assignment is intentionally not recreated.
        // Smart Selection labels are evaluated dynamically from their rules.
    }
};
