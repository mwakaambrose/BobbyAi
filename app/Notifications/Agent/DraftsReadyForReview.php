<?php

namespace App\Notifications\Agent;

use App\Models\Post;
use App\Models\Team;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the team that the autopilot has drafted, and that the drafts are
 * waiting on a person. Nothing publishes until someone approves.
 */
class DraftsReadyForReview extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  Collection<int, Post>  $posts
     */
    public function __construct(public Team $team, public Collection $posts) {}

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
        $first = $this->posts->first();

        $message = (new MailMessage)
            ->subject($this->subject())
            ->line($this->posts->count() === 1
                ? __('Bobby wrote a draft and it is waiting for your review.')
                : __('Bobby wrote :count drafts and they are waiting for your review.', ['count' => $this->posts->count()]));

        foreach ($this->posts as $post) {
            $message->line('— '.$post->brief);
        }

        return $message
            ->action(__('Review and approve'), route('posts.show', [$this->team->slug, $first]))
            ->line(__('Nothing is published until you approve it.'));
    }

    /**
     * Build the subject from the brief when there is only one draft.
     */
    private function subject(): string
    {
        return $this->posts->count() === 1
            ? __('Bobby drafted ":brief"', ['brief' => $this->posts->first()->brief])
            : __('Bobby drafted :count posts for you', ['count' => $this->posts->count()]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'team_id' => $this->team->id,
            'post_ids' => $this->posts->pluck('id')->all(),
        ];
    }
}
