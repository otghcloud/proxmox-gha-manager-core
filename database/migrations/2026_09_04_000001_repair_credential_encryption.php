<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['credentials', 'build_credentials'] as $table) {
            DB::table($table)->select(['id', 'password', 'private_key', 'public_key'])->orderBy('id')->each(function (object $row) use ($table): void {
                $updates = [];

                foreach (['password', 'private_key', 'public_key'] as $column) {
                    if ($row->{$column} === null) {
                        continue;
                    }

                    try {
                        $value = Crypt::decrypt($row->{$column}, false);
                    } catch (Throwable) {
                        continue;
                    }
                    if (is_string($value) && preg_match('/^s:\d+:"/', $value) === 1) {
                        $value = unserialize($value, ['allowed_classes' => false]);
                    }

                    if (is_string($value)) {
                        $updates[$column] = Crypt::encrypt($value, false);
                    }
                }

                if ($updates !== []) {
                    DB::table($table)->where('id', $row->id)->update($updates);
                }
            });
        }
    }

    public function down(): void {}
};
