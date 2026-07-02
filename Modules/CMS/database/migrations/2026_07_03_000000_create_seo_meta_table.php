<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel SEO Pro (nomanur/filament-seo-pro) — polimorfik `seoable`.
 *
 * ADITIF & per-tenant: konten publik (Article, satu-satunya model HasSeo) hidup
 * di DB tenant, jadi meta SEO-nya juga di DB tenant. Ditaruh di modul CMS
 * (di-load via CMSServiceProvider::loadMigrationsFrom + terdaftar di
 * config/tenancy.php migration_parameters) → ikut `tenants:migrate` ke tiap DB
 * tenant DAN `migrate` default (lingkungan test). Skema disalin dari stub bawaan
 * plugin (vendor/nomanur/filament-seo-pro/database/migrations/
 * create_seo_meta_table.php.stub). Guard hasTable → idempoten & anti-500 bila
 * deploy mendahului migrasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('seo_meta')) {
            return;
        }

        Schema::create('seo_meta', function (Blueprint $table) {
            $table->id();

            // Relasi polimorfik (Article, dan model ber-HasSeo lain di masa depan)
            $table->morphs('seoable');

            // Meta inti
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('keywords')->nullable();

            // Focus keyword untuk analisis
            $table->string('focus_keyword')->nullable();

            // SEO teknis
            $table->string('canonical_url')->nullable();
            $table->string('robots')->default('index, follow');

            // Open Graph
            $table->string('og_title')->nullable();
            $table->text('og_description')->nullable();
            $table->string('og_image')->nullable();

            // Twitter Card
            $table->string('twitter_title')->nullable();
            $table->text('twitter_description')->nullable();
            $table->string('twitter_image')->nullable();

            // Schema.org
            $table->string('schema_type')->nullable();

            // Skor
            $table->unsignedTinyInteger('seo_score')->default(0);

            $table->timestamps();

            // Satu baris SEO per model
            $table->unique(['seoable_type', 'seoable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_meta');
    }
};
