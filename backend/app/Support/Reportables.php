<?php

namespace App\Support;

use App\Models\AlumniProfile;
use App\Models\AlumniStory;
use App\Models\CommunityPost;
use App\Models\Mentorship;
use App\Models\Question;
use App\Models\User;

/**
 * What can be reported and moderated (SAF-REQ01/03): community posts and
 * comments, published questions, alumni profiles and stories,
 * mentorships and people.
 */
final class Reportables
{
    public const TYPES = ['post', 'question', 'alumni_profile', 'alumni_story', 'mentorship', 'user'];

    /**
     * @return array{exists: bool, owner: ?User, label: string, excerpt: ?string, url: ?string, hidden: bool, can_hide: bool}
     */
    public static function describe(string $type, string $id): array
    {
        $empty = ['exists' => false, 'owner' => null, 'label' => __('Removed item'), 'excerpt' => null, 'url' => null, 'hidden' => false, 'can_hide' => false];

        return match ($type) {
            'post' => ($post = CommunityPost::with(['author', 'space'])->find($id)) ? [
                'exists' => true,
                'owner' => $post->author,
                'label' => $post->parent_id ? __('Comment in :space', ['space' => $post->space?->name]) : __('Post in :space', ['space' => $post->space?->name]),
                'excerpt' => trim(($post->title ? $post->title."\n" : '').$post->body),
                'url' => route('member.community.post', $post->parent_id ?? $post->post_id),
                'hidden' => in_array($post->status, [CommunityPost::HIDDEN, CommunityPost::REMOVED, CommunityPost::HELD], true),
                'can_hide' => true,
            ] : $empty,
            'question' => ($question = Question::with('asker')->find($id)) ? [
                'exists' => true,
                'owner' => $question->asker,
                'label' => __('Question :reference', ['reference' => $question->reference]),
                'excerpt' => $question->public_question ?? $question->subject."\n".$question->body,
                'url' => $question->published_at ? route('member.ask.answer', $question->slug) : null,
                'hidden' => $question->published_at === null,
                'can_hide' => true,
            ] : $empty,
            'alumni_profile' => ($profile = AlumniProfile::with('user')->find($id)) ? [
                'exists' => true,
                'owner' => $profile->user,
                'label' => __('Alumni profile'),
                'excerpt' => trim($profile->occupation."\n".$profile->bio),
                'url' => null,
                'hidden' => ! $profile->show_in_directory,
                'can_hide' => true,
            ] : $empty,
            'alumni_story' => ($story = AlumniStory::with('author')->find($id)) ? [
                'exists' => true,
                'owner' => $story->author,
                'label' => __('Alumni story'),
                'excerpt' => $story->title."\n".$story->body,
                'url' => $story->published_at ? route('member.alumni.story', $story->slug) : null,
                'hidden' => $story->published_at === null,
                'can_hide' => true,
            ] : $empty,
            'mentorship' => ($mentorship = Mentorship::with(['mentor', 'mentee'])->find($id)) ? [
                'exists' => true,
                'owner' => $mentorship->mentor,
                'label' => __('Mentorship: :mentor with :mentee', ['mentor' => $mentorship->mentor?->full_name, 'mentee' => $mentorship->mentee?->publicName()]),
                'excerpt' => null,
                'url' => route('admin.mentorship.show', $mentorship),
                'hidden' => $mentorship->status !== Mentorship::ACTIVE,
                'can_hide' => false,
            ] : $empty,
            'user' => ($user = User::find($id)) ? [
                'exists' => true,
                'owner' => $user,
                'label' => __('Member: :name', ['name' => $user->full_name]),
                'excerpt' => null,
                'url' => null,
                'hidden' => false,
                'can_hide' => false,
            ] : $empty,
            default => $empty,
        };
    }

    /**
     * Show or hide the item. Returns false if this type cannot be hidden.
     */
    public static function setVisible(string $type, string $id, bool $visible, bool $remove = false): bool
    {
        switch ($type) {
            case 'post':
                $post = CommunityPost::find($id);
                if (! $post) {
                    return false;
                }
                $post->forceFill(['status' => $visible ? CommunityPost::PUBLISHED : ($remove ? CommunityPost::REMOVED : CommunityPost::HIDDEN), 'held_reason' => null])->save();
                Community::recount($post);

                return true;
            case 'question':
                $question = Question::find($id);
                if (! $question || ! $question->slug) {
                    return false;
                }
                $question->forceFill(['published_at' => $visible ? ($question->published_at ?? now()) : null])->save();

                return true;
            case 'alumni_profile':
                return (bool) AlumniProfile::whereKey($id)->update(['show_in_directory' => $visible]);
            case 'alumni_story':
                $story = AlumniStory::find($id);
                if (! $story) {
                    return false;
                }
                $story->forceFill(['published_at' => $visible ? ($story->published_at ?? now()) : null])->save();

                return true;
            default:
                return false;
        }
    }
}
