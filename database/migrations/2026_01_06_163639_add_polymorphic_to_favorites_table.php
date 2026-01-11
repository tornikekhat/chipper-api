<?php

use App\Models\Post;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('favorites', function (Blueprint $table) {
            $table->unsignedBigInteger('favoritable_id')->nullable();
            $table->string('favoritable_type')->nullable();

            $table->unique(['user_id', 'favoritable_id', 'favoritable_type'], 'favorites_user_favoritable_unique');
        });

        DB::table('favorites')->whereNotNull('post_id')->whereNull('favoritable_id')->update([
            'favoritable_id' => DB::raw('post_id'),
            'favoritable_type' => Post::class,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('favorites', function (Blueprint $table) {
            $table->dropUnique('favorites_user_favoritable_unique');

            $table->dropColumn('favoritable_id');
            $table->dropColumn('favoritable_type');
        });
    }
};
