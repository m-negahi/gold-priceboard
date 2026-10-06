<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ╔═══════════════════════════════════════════════════════════════════╗
 * ║ seo_meta — override e SEO e har safhe/mohtava (P5-05)               ║
 * ╚═══════════════════════════════════════════════════════════════════╝
 *
 * Polymorphic: `seoable_type` + `seoable_id`. `seoable_id` REShTE ast (na
 * bigint) chon hadaf ha hamishe Eloquent nistan: safhe ye sabet
 * (`page`/`home`) ya symbol (`instrument`/`xau-18k-g-irr`) kelid e
 * reshte-i daran. Model e Eloquent ham ba `(string) $id` ja mishe.
 *
 * Har sotun NULL = pish-farz e code (`SeoResolver`). Jadval e khali =
 * raftar e ghabli، bedun e taghir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_meta', function (Blueprint $table): void {
            $table->id();
            $table->string('seoable_type', 64);
            $table->string('seoable_id', 191);
            $table->string('title', 255)->nullable();
            $table->string('description', 500)->nullable();
            $table->string('canonical_url', 2048)->nullable();
            // Masalan `noindex, follow` — safhe az sitemap ham hazf mishe.
            $table->string('robots', 64)->nullable();
            $table->string('og_image', 2048)->nullable();
            $table->timestamps();

            $table->unique(['seoable_type', 'seoable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_meta');
    }
};
