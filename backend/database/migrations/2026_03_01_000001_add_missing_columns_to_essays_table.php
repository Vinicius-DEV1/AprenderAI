<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('essays', function (Blueprint $table) {
            $table->string('input_type')->default('text')->after('type');
            $table->string('image_path')->nullable()->after('content');
            $table->string('ocr_status')->nullable()->after('image_path');
            $table->text('ocr_error')->nullable()->after('ocr_status');
            $table->longText('extracted_text')->nullable()->after('ocr_error');
        });
    }

    public function down(): void
    {
        Schema::table('essays', function (Blueprint $table) {
            $table->dropColumn(['input_type', 'image_path', 'ocr_status', 'ocr_error', 'extracted_text']);
        });
    }
};
