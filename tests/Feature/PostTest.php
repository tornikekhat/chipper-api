<?php

namespace Tests\Feature;

use Illuminate\Support\Arr;
use App\Models\User;
use App\Models\Post;
use App\Notifications\NewPostNotification;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PostTest extends TestCase
{
    use DatabaseMigrations;

    public function test_a_guest_can_not_create_a_post()
    {
        $response = $this->postJson(route('posts.store'), [
            'title' => 'Test Post',
            'body' => 'This is a test post.',
        ]);

        $response->assertStatus(401);
    }

    public function test_a_user_can_create_a_post()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('posts.store'), [
            'title' => 'Test Post',
            'body' => 'This is a test post.',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'id', 'title', 'body',
                ]
            ])
            ->assertJson([
                'data' => [
                    'title' => 'Test Post',
                    'body' => 'This is a test post.',
                ]
            ]);

        $this->assertDatabaseHas('posts', [
            'title' => 'Test Post',
            'body' => 'This is a test post.',
        ]);
    }

    public function test_a_user_can_update_a_post()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('posts.store'), [
            'title' => 'Original title',
            'body' => 'Original body.',
        ]);

        $id = Arr::get($response->json(), 'data.id');

        $response = $this->actingAs($user)->putJson(route('posts.update', ['post' => $id]), [
            'title' => 'Updated title',
            'body' => 'Updated body.',
        ]);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'title' => 'Updated title',
                    'body' => 'Updated body.',
                ]
            ]);

        $this->assertDatabaseHas('posts', [
            'title' => 'Updated title',
            'body' => 'Updated body.',
            'id' => $id,
        ]);
    }

    public function test_a_user_can_not_update_a_post_by_other_user()
    {
        $john = User::factory()->create(['name' => 'John']);
        $jack = User::factory()->create(['name' => 'Jack']);

        $response = $this->actingAs($john)->postJson(route('posts.store'), [
            'title' => 'Original title',
            'body' => 'Original body.',
        ]);

        $id = Arr::get($response->json(), 'data.id');

        $response = $this->actingAs($jack)->putJson(route('posts.update', ['post' => $id]), [
            'title' => 'Updated title',
            'body' => 'Updated body.',
        ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('posts', [
            'title' => 'Original title',
            'body' => 'Original body.',
            'id' => $id,
        ]);
    }

    public function test_a_user_can_destroy_one_of_his_posts()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('posts.store'), [
            'title' => 'My title',
            'body' => 'My body.',
        ]);

        $id = Arr::get($response->json(), 'data.id');

        $response = $this->actingAs($user)->deleteJson(route('posts.destroy', ['post' => $id]));

        $response->assertNoContent();

        $this->assertDatabaseMissing('posts', [
            'id' => $id,
        ]);
    }

    public function test_users_who_favorited_author_receive_notification_when_post_is_created()
    {
        Notification::fake();

        $author = User::factory()->create(['name' => 'John Doe']);
        $follower1 = User::factory()->create(['name' => 'Follower One']);
        $follower2 = User::factory()->create(['name' => 'Follower Two']);
        $nonFollower = User::factory()->create(['name' => 'Non Follower']);

        $this->actingAs($follower1)
            ->postJson(route('users.favorites.store', ['user' => $author]))
            ->assertCreated();

        $this->actingAs($follower2)
            ->postJson(route('users.favorites.store', ['user' => $author]))
            ->assertCreated();

        $response = $this->actingAs($author)->postJson(route('posts.store'), [
            'title' => 'New Post Title',
            'body' => 'New post body content.',
        ]);

        $response->assertCreated();

        Notification::assertSentTo(
            [$follower1, $follower2],
            NewPostNotification::class,
            function ($notification, $channels, $notifiable) use ($author) {
                return $notification->post->user_id === $author->id
                    && in_array('mail', $channels);
            }
        );

        Notification::assertNotSentTo(
            [$nonFollower],
            NewPostNotification::class
        );
    }

    public function test_author_does_not_receive_notification_for_own_post()
    {
        Notification::fake();

        $author = User::factory()->create(['name' => 'John Doe']);

        $response = $this->actingAs($author)->postJson(route('posts.store'), [
            'title' => 'My Own Post',
            'body' => 'This is my own post.',
        ]);

        $response->assertCreated();

        Notification::assertNotSentTo(
            [$author],
            NewPostNotification::class
        );
    }

    public function test_no_notifications_sent_when_author_has_no_followers()
    {
        Notification::fake();

        $author = User::factory()->create(['name' => 'John Doe']);

        $response = $this->actingAs($author)->postJson(route('posts.store'), [
            'title' => 'Post Without Followers',
            'body' => 'This post has no followers.',
        ]);

        $response->assertCreated();

        Notification::assertNothingSent();
    }

    public function test_notification_email_contains_correct_content()
    {
        Notification::fake();

        $author = User::factory()->create(['name' => 'Jane Smith']);
        $follower = User::factory()->create(['name' => 'Follower']);

        $this->actingAs($follower)
            ->postJson(route('users.favorites.store', ['user' => $author]))
            ->assertCreated();

        $response = $this->actingAs($author)->postJson(route('posts.store'), [
            'title' => 'Amazing Post Title',
            'body' => 'This is the post body content.',
        ]);

        $postId = Arr::get($response->json(), 'data.id');

        $response->assertCreated();

        Notification::assertSentTo(
            $follower,
            NewPostNotification::class,
            function ($notification) use ($author, $postId, $follower) {
                $mailData = $notification->toMail($follower);

                return $notification->post->title === 'Amazing Post Title'
                    && $notification->post->body === 'This is the post body content.'
                    && $notification->post->user->name === 'Jane Smith'
                    && str_contains($mailData->subject, 'Jane Smith')
                    && str_contains($mailData->subject, 'created a new post');
            }
        );
    }

    public function test_post_creation_response_is_not_delayed_by_notifications()
    {
        Notification::fake();

        $author = User::factory()->create();
        $followers = User::factory()->count(10)->create();

        foreach ($followers as $follower) {
            $this->actingAs($follower)
                ->postJson(route('users.favorites.store', ['user' => $author]))
                ->assertCreated();
        }

        $startTime = microtime(true);
        
        $response = $this->actingAs($author)->postJson(route('posts.store'), [
            'title' => 'Quick Post',
            'body' => 'This should respond quickly.',
        ]);

        $endTime = microtime(true);
        $responseTime = ($endTime - $startTime) * 1000;

        $response->assertCreated();

        $this->assertLessThan(100, $responseTime, 'Post creation response was delayed by notifications');

        Notification::assertSentTo(
            $followers,
            NewPostNotification::class
        );
    }
}
