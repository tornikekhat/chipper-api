<?php

namespace App\Console\Commands;

use App\Models\User;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;

class ImportUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:import {url : The URL of the JSON file} {limit : The maximum number of users to import}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import users from a JSON file URL up to a specified limit';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $url = $this->argument('url');
        $limit = (int) $this->argument('limit');

        if ($limit <= 0) {
            $this->error('Limit must be a positive integer.');
            return Command::FAILURE;
        }

        $this->info("Fetching users from: {$url}");
        $this->info("Limit: {$limit} users");

        try {
            $response = Http::timeout(30)->get($url);

            if (!$response->successful()) {
                $this->error("Failed to fetch JSON from URL. HTTP Status: {$response->status()}");
                return Command::FAILURE;
            }

            $users = $response->json();

            if (!is_array($users)) {
                $this->error('Invalid JSON format. Expected an array of users.');
                return Command::FAILURE;
            }

            if (empty($users)) {
                $this->warn('No users found in the JSON file.');
                return Command::SUCCESS;
            }

            $usersToImport = array_slice($users, 0, $limit);
            $totalUsers = count($usersToImport);

            $this->info("Processing {$totalUsers} user(s)...");

            $imported = 0;
            $skipped = 0;
            
            $showProgress = !app()->environment('testing');
            if ($showProgress) {
                $bar = $this->output->createProgressBar($totalUsers);
                $bar->start();
            }

            foreach ($usersToImport as $userData) {
                $name = isset($userData['name']) ? trim($userData['name']) : '';
                $email = isset($userData['email']) ? trim($userData['email']) : '';
                
                if ($name === '' || $email === '') {
                    $skipped++;
                    if ($showProgress) {
                        $bar->advance();
                    }
                    continue;
                }

                if (User::where('email', $email)->exists()) {
                    $skipped++;
                    if ($showProgress) {
                        $bar->advance();
                    }
                    continue;
                }

                try {
                    User::create([
                        'name' => $name,
                        'email' => $email,
                        'password' => Hash::make('password'),
                    ]);
                    $imported++;
                } catch (QueryException $e) {
                    $skipped++;
                } catch (Exception $e) {
                    $skipped++;
                }

                if ($showProgress) {
                    $bar->advance();
                }
            }

            if ($showProgress) {
                $bar->finish();
                $this->newLine(2);
            }

            $this->info("Import completed!");
            $this->table(
                ['Status', 'Count'],
                [
                    ['Imported', $imported],
                    ['Skipped', $skipped],
                    ['Total', $totalUsers],
                ]
            );

            return Command::SUCCESS;
        } catch (Exception $e) {
            $this->error("An error occurred: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}
