<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use phpseclib3\Crypt\RSA;

return new class extends Migration
{
    public function up(): void
    {
        if (blank(config('app.key'))) {
            config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        }

        Schema::create('credentials', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('os');
            $table->string('username')->nullable();
            $table->text('password')->nullable();
            $table->text('private_key')->nullable();
            $table->text('public_key')->nullable();
            $table->timestamps();

            $table->unique(['name', 'os']);
        });

        Schema::table('runner_templates', function (Blueprint $table): void {
            $table->foreignId('credential_id')->nullable()->after('os')->nullOnDelete();
        });

        Schema::table('image_builds', function (Blueprint $table): void {
            $table->foreignId('credential_id')->nullable()->after('runner_template_id')->nullOnDelete();
        });

        Schema::create('build_credentials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('image_build_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('credential_id')->nullable()->constrained()->nullOnDelete();
            $table->string('os');
            $table->string('username');
            $table->text('password')->nullable();
            $table->text('private_key')->nullable();
            $table->text('public_key')->nullable();
            $table->timestamps();
        });

        foreach (['linux' => 'linux_ssh', 'windows' => 'windows'] as $os => $prefix) {
            $usernameColumn = $prefix.'_username';
            $passwordColumn = $prefix.'_password';

            $accounts = DB::table('github_accounts')
                ->whereNotNull($usernameColumn)
                ->orWhereNotNull($passwordColumn)
                ->get([$usernameColumn, $passwordColumn]);

            foreach ($accounts as $account) {
                if (blank($account->{$usernameColumn}) && blank($account->{$passwordColumn})) {
                    continue;
                }

                $credentialId = DB::table('credentials')->insertGetId([
                    'name' => ucfirst($os).' legacy credential '.bin2hex(random_bytes(4)),
                    'os' => $os,
                    'username' => $account->{$usernameColumn},
                    'password' => $account->{$passwordColumn},
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('runner_templates as templates')
                    ->join('environments', 'environments.id', '=', 'templates.environment_id')
                    ->where('templates.os', $os)
                    ->whereNull('templates.credential_id')
                    ->whereExists(function ($query) use ($account, $usernameColumn, $passwordColumn): void {
                        $query->selectRaw('1')
                            ->from('github_accounts')
                            ->whereColumn('github_accounts.id', 'environments.github_account_id')
                            ->where($usernameColumn, $account->{$usernameColumn})
                            ->where($passwordColumn, $account->{$passwordColumn});
                    })
                    ->update(['templates.credential_id' => $credentialId]);
            }
        }

        DB::table('settings')->updateOrInsert(
            ['key' => 'default_runner_username'],
            ['value' => 'runner', 'updated_at' => now(), 'created_at' => now()],
        );

        $key = RSA::createKey(4096);
        DB::table('credentials')->insert([
            'name' => 'Default Linux SSH',
            'os' => 'linux',
            'username' => 'runner',
            'password' => encrypt(bin2hex(random_bytes(32))),
            'private_key' => encrypt($key->toString('PKCS8')),
            'public_key' => encrypt($key->getPublicKey()->toString('OpenSSH')),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('github_accounts', function (Blueprint $table): void {
            $table->dropColumn([
                'linux_ssh_username',
                'linux_ssh_password',
                'windows_username',
                'windows_password',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('github_accounts', function (Blueprint $table): void {
            $table->string('linux_ssh_username')->default('runner');
            $table->text('linux_ssh_password')->nullable();
            $table->string('windows_username')->nullable();
            $table->text('windows_password')->nullable();
        });

        Schema::table('image_builds', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('credential_id');
        });

        Schema::dropIfExists('build_credentials');

        Schema::table('runner_templates', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('credential_id');
        });

        Schema::dropIfExists('credentials');
    }
};
