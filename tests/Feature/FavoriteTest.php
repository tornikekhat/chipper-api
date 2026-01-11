<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Post;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class FavoriteTest extends TestCase
{
    use DatabaseMigrations;

    public function test_a_guest_can_not_favorite_a_post()
    {
        $post = Post::factory()->create();

        $this->postJson(route('favorites.store', ['post' => $post]))
            ->assertStatus(401);
    }

    public function test_a_user_can_favorite_a_post()
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        $this->actingAs($user)
            ->postJson(route('favorites.store', ['post' => $post]))
            ->assertCreated();

        $this->assertDatabaseHas('favorites', [
            'favoritable_id' => $post->id,
            'favoritable_type' => Post::class,
            'user_id' => $user->id,
        ]);
    }

    public function test_a_user_can_remove_a_post_from_his_favorites()
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        $this->actingAs($user)
            ->postJson(route('favorites.store', ['post' => $post]))
            ->assertCreated();

        $this->assertDatabaseHas('favorites', [
            'favoritable_id' => $post->id,
            'favoritable_type' => Post::class,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->deleteJson(route('favorites.destroy', ['post' => $post]))
            ->assertNoContent();

        $this->assertDatabaseMissing('favorites', [
            'favoritable_id' => $post->id,
            'favoritable_type' => Post::class,
            'user_id' => $user->id,
        ]);
    }

    public function test_a_user_can_not_remove_a_non_favorited_item()
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        $this->actingAs($user)
            ->deleteJson(route('favorites.destroy', ['post' => $post]))
            ->assertNotFound();
    }

    public function test_a_guest_can_not_favorite_a_user()
    {
        $user = User::factory()->create();

        $this->postJson(route('users.favorites.store', ['user' => $user]))
            ->assertStatus(401);
    }

    public function test_a_user_can_favorite_another_user()
    {
        $user = User::factory()->create();
        $favoritedUser = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('users.favorites.store', ['user' => $favoritedUser]))
            ->assertCreated();

        $this->assertDatabaseHas('favorites', [
            'favoritable_id' => $favoritedUser->id,
            'favoritable_type' => User::class,
            'user_id' => $user->id,
        ]);
    }

    public function test_a_user_can_remove_a_user_from_his_favorites()
    {
        $user = User::factory()->create();
        $favoritedUser = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('users.favorites.store', ['user' => $favoritedUser]))
            ->assertCreated();

        $this->assertDatabaseHas('favorites', [
            'favoritable_id' => $favoritedUser->id,
            'favoritable_type' => User::class,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->deleteJson(route('users.favorites.destroy', ['user' => $favoritedUser]))
            ->assertNoContent();

        $this->assertDatabaseMissing('favorites', [
            'favoritable_id' => $favoritedUser->id,
            'favoritable_type' => User::class,
            'user_id' => $user->id,
        ]);
    }

    public function test_a_user_can_not_favorite_himself()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('users.favorites.store', ['user' => $user]))
            ->assertStatus(422);
    }

    public function test_a_user_can_still_favorite_posts()
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        $this->actingAs($user)
            ->postJson(route('favorites.store', ['post' => $post]))
            ->assertCreated();

        $this->assertDatabaseHas('favorites', [
            'favoritable_id' => $post->id,
            'favoritable_type' => Post::class,
            'user_id' => $user->id,
        ]);
    }

    public function test_a_user_can_not_remove_a_non_favorited_user()
    {
        $user = User::factory()->create();
        $favoritedUser = User::factory()->create();

        $this->actingAs($user)
            ->deleteJson(route('users.favorites.destroy', ['user' => $favoritedUser]))
            ->assertNotFound();
    }

    public function test_a_guest_can_not_view_favorites()
    {
        $this->getJson(route('favorites.index'))
            ->assertStatus(401);
    }

    public function test_a_user_can_view_their_favorites_with_correct_structure()
    {
        $user = User::factory()->create();
        $postAuthor = User::factory()->create();
        $favoritedUser = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $postAuthor->id]);

        $this->actingAs($user)
            ->postJson(route('favorites.store', ['post' => $post]))
            ->assertCreated();

        $this->actingAs($user)
            ->postJson(route('users.favorites.store', ['user' => $favoritedUser]))
            ->assertCreated();

        $response = $this->actingAs($user)
            ->getJson(route('favorites.index'))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'posts' => [
                        '*' => ['id', 'title', 'body', 'user' => ['id', 'name']],
                    ],
                    'users' => [
                        '*' => ['id', 'name'],
                    ],
                ],
            ]);

        $responseData = $response->json('data');

        $this->assertCount(1, $responseData['posts']);
        $this->assertEquals($post->id, $responseData['posts'][0]['id']);
        $this->assertEquals($post->title, $responseData['posts'][0]['title']);
        $this->assertEquals($post->body, $responseData['posts'][0]['body']);
        $this->assertEquals($postAuthor->id, $responseData['posts'][0]['user']['id']);
        $this->assertEquals($postAuthor->name, $responseData['posts'][0]['user']['name']);

        $this->assertCount(1, $responseData['users']);
        $this->assertEquals($favoritedUser->id, $responseData['users'][0]['id']);
        $this->assertEquals($favoritedUser->name, $responseData['users'][0]['name']);
    }

    public function test_favorites_endpoint_returns_empty_arrays_when_no_favorites()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson(route('favorites.index'))
            ->assertOk()
            ->assertJson([
                'data' => [
                    'posts' => [],
                    'users' => [],
                ],
            ]);
    }

    public function test_favorites_endpoint_groups_multiple_posts_and_users()
    {
        $user = User::factory()->create();
        $author1 = User::factory()->create();
        $author2 = User::factory()->create();
        $favoritedUser1 = User::factory()->create();
        $favoritedUser2 = User::factory()->create();

        $post1 = Post::factory()->create(['user_id' => $author1->id]);
        $post2 = Post::factory()->create(['user_id' => $author2->id]);

        $this->actingAs($user)
            ->postJson(route('favorites.store', ['post' => $post1]))
            ->assertCreated();

        $this->actingAs($user)
            ->postJson(route('favorites.store', ['post' => $post2]))
            ->assertCreated();

        $this->actingAs($user)
            ->postJson(route('users.favorites.store', ['user' => $favoritedUser1]))
            ->assertCreated();

        $this->actingAs($user)
            ->postJson(route('users.favorites.store', ['user' => $favoritedUser2]))
            ->assertCreated();

        $response = $this->actingAs($user)
            ->getJson(route('favorites.index'))
            ->assertOk();

        $responseData = $response->json('data');

        $this->assertCount(2, $responseData['posts']);
        $this->assertCount(2, $responseData['users']);

        $postIds = collect($responseData['posts'])->pluck('id')->toArray();
        $this->assertContains($post1->id, $postIds);
        $this->assertContains($post2->id, $postIds);

        $userIds = collect($responseData['users'])->pluck('id')->toArray();
        $this->assertContains($favoritedUser1->id, $userIds);
        $this->assertContains($favoritedUser2->id, $userIds);
    }
}
