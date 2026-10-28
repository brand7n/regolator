<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kennels', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('kennel_id')->nullable()->constrained()->nullOnDelete();
        });

        $now = now();

        foreach (DB::table('events')->select('kennel')->distinct()->pluck('kennel') as $raw) {
            $name = trim((string) preg_replace('/\s+/u', ' ', (string) $raw));

            if ($name === '') {
                continue;
            }

            $kennelId = DB::table('kennels')
                ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
                ->value('id');

            $kennelId ??= DB::table('kennels')->insertGetId([
                'name' => $name,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('events')
                ->where('kennel', $raw)
                ->update(['kennel_id' => $kennelId]);
        }

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('kennel');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('kennel')->nullable()->after('name');
        });

        foreach (DB::table('kennels')->pluck('name', 'id') as $id => $name) {
            DB::table('events')->where('kennel_id', $id)->update(['kennel' => $name]);
        }

        Schema::table('events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('kennel_id');
        });

        Schema::dropIfExists('kennels');
    }
};
