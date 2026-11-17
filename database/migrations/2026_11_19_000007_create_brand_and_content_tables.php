<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $t) {
            $t->id();
            $t->string('filename')->unique();      // generated, never the uploaded name
            $t->string('original_name');
            $t->string('mime', 40);
            $t->unsignedInteger('size');
            $t->unsignedInteger('width')->nullable();
            $t->unsignedInteger('height')->nullable();
            $t->string('alt')->nullable();
            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('brand_settings', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('short_name');
            $t->string('descriptor')->nullable();   // small line under the wordmark
            $t->string('tagline')->nullable();
            $t->foreignId('logo_light_id')->nullable()->constrained('media')->nullOnDelete();
            $t->foreignId('logo_dark_id')->nullable()->constrained('media')->nullOnDelete();
            $t->foreignId('favicon_id')->nullable()->constrained('media')->nullOnDelete();
            $t->string('brand_color', 7);
            $t->string('highlight_color', 7);
            $t->string('support_email');
            $t->string('support_phone');
            $t->string('support_hours');
            $t->string('sender_name');
            $t->string('footer_text', 400)->nullable();
            $t->json('social_links')->nullable();
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        // One row per editable page: the published copy, the working draft, and a version counter for legal pages.
        Schema::create('page_contents', function (Blueprint $t) {
            $t->id();
            $t->string('page')->unique();
            $t->json('published')->nullable();
            $t->json('draft')->nullable();
            $t->unsignedInteger('version')->default(0);
            $t->timestamp('published_at')->nullable();
            $t->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('draft_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('draft_at')->nullable();
            $t->timestamps();
        });

        // Append-only: every publish writes the full published copy so any version can be restored.
        Schema::create('page_revisions', function (Blueprint $t) {
            $t->id();
            $t->string('page')->index();
            $t->unsignedInteger('version');
            $t->json('content');
            $t->string('note')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_revisions');
        Schema::dropIfExists('page_contents');
        Schema::dropIfExists('brand_settings');
        Schema::dropIfExists('media');
    }
};
