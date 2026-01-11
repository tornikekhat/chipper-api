<?php

namespace App\Notifications;

use App\Models\Post;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewPostNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Post $post
    ) {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        if (!$this->post->relationLoaded('user')) {
            $this->post->load('user');
        }

        return (new MailMessage)
                    ->subject("{$this->post->user->name} has created a new post")
                    ->line("{$this->post->user->name} has created a new post: \"{$this->post->title}\"")
                    ->line($this->post->body)
                    ->action('View Post', url("/posts/{$this->post->id}"))
                    ->line('Thank you for using Chipper!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        if (!$this->post->relationLoaded('user')) {
            $this->post->load('user');
        }

        return [
            'post_id' => $this->post->id,
            'post_title' => $this->post->title,
            'author_id' => $this->post->user->id,
            'author_name' => $this->post->user->name,
        ];
    }
}
