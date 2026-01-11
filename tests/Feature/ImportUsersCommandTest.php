<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class ImportUsersCommandTest extends TestCase
{
    use DatabaseMigrations;

    public function test_command_requires_url_and_limit_arguments()
    {
        $this->expectException(RuntimeException::class);
        $this->artisan('users:import');
    }

    public function test_command_rejects_invalid_limit()
    {
        $this->artisan('users:import', [
            'url' => 'https://jsonplaceholder.typicode.com/users',
            'limit' => '0',
        ])
            ->expectsOutput('Limit must be a positive integer.')
            ->assertExitCode(1);

        $this->artisan('users:import', [
            'url' => 'https://jsonplaceholder.typicode.com/users',
            'limit' => '-5',
        ])
            ->expectsOutput('Limit must be a positive integer.')
            ->assertExitCode(1);
    }

    public function test_command_handles_failed_http_request()
    {
        Http::fake([
            '*' => Http::response([], 404),
        ]);

        $this->artisan('users:import', [
            'url' => 'https://invalid-url.com/users',
            'limit' => '5',
        ])
            ->expectsOutput('Failed to fetch JSON from URL. HTTP Status: 404')
            ->assertExitCode(1);
    }

    public function test_command_handles_invalid_json()
    {
        Http::fake([
            '*' => Http::response('invalid json', 200),
        ]);

        $this->artisan('users:import', [
            'url' => 'https://example.com/invalid.json',
            'limit' => '5',
        ])
            ->expectsOutput('Invalid JSON format. Expected an array of users.')
            ->assertExitCode(1);
    }

    public function test_command_handles_empty_json_array()
    {
        Http::fake([
            '*' => Http::response([], 200),
        ]);

        $this->artisan('users:import', [
            'url' => 'https://example.com/empty.json',
            'limit' => '5',
        ])
            ->expectsOutput('No users found in the JSON file.')
            ->assertExitCode(0);
    }

    public function test_command_imports_users_successfully()
    {
        $mockUsers = [
            [
                'id' => 1,
                'name' => 'John Doe',
                'email' => 'john.doe@example.com',
                'username' => 'johndoe',
            ],
            [
                'id' => 2,
                'name' => 'Jane Smith',
                'email' => 'jane.smith@example.com',
                'username' => 'janesmith',
            ],
        ];

        Http::fake([
            '*' => Http::response($mockUsers, 200, ['Content-Type' => 'application/json']),
        ]);

        $this->artisan('users:import', [
            'url' => 'https://jsonplaceholder.typicode.com/users',
            'limit' => '2',
        ])
            ->assertExitCode(0);

        $this->assertEquals(2, User::count());
        $this->assertDatabaseHas('users', [
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
        ]);

        $this->assertDatabaseHas('users', [
            'name' => 'Jane Smith',
            'email' => 'jane.smith@example.com',
        ]);
    }

    public function test_command_respects_limit_parameter()
    {
        $mockUsers = [
            ['id' => 1, 'name' => 'User 1', 'email' => 'user1@example.com', 'username' => 'user1'],
            ['id' => 2, 'name' => 'User 2', 'email' => 'user2@example.com', 'username' => 'user2'],
            ['id' => 3, 'name' => 'User 3', 'email' => 'user3@example.com', 'username' => 'user3'],
            ['id' => 4, 'name' => 'User 4', 'email' => 'user4@example.com', 'username' => 'user4'],
            ['id' => 5, 'name' => 'User 5', 'email' => 'user5@example.com', 'username' => 'user5'],
        ];

        Http::fake([
            '*' => Http::response($mockUsers, 200, ['Content-Type' => 'application/json']),
        ]);

        $this->artisan('users:import', [
            'url' => 'https://jsonplaceholder.typicode.com/users',
            'limit' => '3',
        ])
            ->assertExitCode(0);

        $this->assertEquals(3, User::count());
        $this->assertDatabaseHas('users', ['email' => 'user1@example.com']);
        $this->assertDatabaseHas('users', ['email' => 'user2@example.com']);
        $this->assertDatabaseHas('users', ['email' => 'user3@example.com']);
        $this->assertDatabaseMissing('users', ['email' => 'user4@example.com']);
        $this->assertDatabaseMissing('users', ['email' => 'user5@example.com']);
    }

    public function test_command_skips_users_with_missing_name_or_email()
    {
        $mockUsers = [
            [
                'id' => 1,
                'name' => 'Valid User',
                'email' => 'validuser@example.com',
                'username' => 'validuser',
            ],
            [
                'id' => 2,
                'name' => '',
                'email' => 'noname@example.com',
                'username' => 'noname',
            ],
            [
                'id' => 3,
                'name' => 'No Email',
                'email' => '',
                'username' => 'noemail',
            ],
            [
                'id' => 4,
                'name' => 'Missing Name',
                'email' => 'missingname@example.com',
                'username' => 'missingname',
            ],
        ];

        Http::fake([
            '*' => Http::response($mockUsers, 200, ['Content-Type' => 'application/json']),
        ]);

        $this->artisan('users:import', [
            'url' => 'https://jsonplaceholder.typicode.com/users',
            'limit' => '10',
        ])
            ->assertExitCode(0);

        $this->assertEquals(2, User::count());
        $this->assertDatabaseHas('users', ['email' => 'validuser@example.com']);
        $this->assertDatabaseHas('users', ['email' => 'missingname@example.com']);
        $this->assertDatabaseMissing('users', ['email' => 'noname@example.com']);
    }

    public function test_command_skips_existing_users()
    {
        User::factory()->create([
            'name' => 'Existing User',
            'email' => 'existing@example.com',
        ]);

        $mockUsers = [
            [
                'id' => 1,
                'name' => 'Existing User',
                'email' => 'existing@example.com',
                'username' => 'existing',
            ],
            [
                'id' => 2,
                'name' => 'New User',
                'email' => 'newuser@example.com',
                'username' => 'newuser',
            ],
        ];

        Http::fake([
            '*' => Http::response($mockUsers, 200, ['Content-Type' => 'application/json']),
        ]);

        $this->artisan('users:import', [
            'url' => 'https://jsonplaceholder.typicode.com/users',
            'limit' => '10',
        ])
            ->assertExitCode(0);

        $this->assertEquals(2, User::count());
        $this->assertDatabaseHas('users', ['email' => 'existing@example.com']);
        $this->assertDatabaseHas('users', ['email' => 'newuser@example.com']);
    }

    public function test_command_imports_users_with_correct_password()
    {
        $mockUsers = [
            [
                'id' => 1,
                'name' => 'Test User',
                'email' => 'testuser@example.com',
                'username' => 'testuser',
            ],
        ];

        Http::fake([
            '*' => Http::response($mockUsers, 200),
        ]);

        $this->artisan('users:import', [
            'url' => 'https://jsonplaceholder.typicode.com/users',
            'limit' => '1',
        ])
            ->assertExitCode(0);

        $user = User::where('email', 'testuser@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('password', $user->password));
    }

    public function test_command_handles_network_timeout()
    {
        Http::fake(function () {
            throw new ConnectionException('Connection timeout');
        });

        $this->artisan('users:import', [
            'url' => 'https://slow-api.com/users',
            'limit' => '5',
        ])
            ->assertExitCode(1);
    }
}
