<?php

namespace App\Transformer\Api;

use App\Models\CustomEmoji;
use App\Models\Status;
use App\Services\AccountService;
use App\Services\HashidService;
use App\Services\LikeService;
use App\Services\MediaService;
use App\Services\MediaTagService;
use App\Services\PollService;
use App\Services\StatusHashtagService;
use App\Services\StatusMentionService;
use App\Services\StatusService;
use App\Util\Lexer\Autolink;
use League\Fractal;

class StatusStatelessTransformer extends Fractal\TransformerAbstract
{
    public function transform(Status $status): array
    {
        $taggedPeople = MediaTagService::get($status->id);
        $poll = $status->type === 'poll' ? PollService::get($status->id) : null;
        $reblog = $status->reblog_of_id ? StatusService::get($status->reblog_of_id, false) : null;

        // A boost carries no caption of its own - the text belongs to the
        // status it shares, exactly as its images do (see mediaAttachments()).
        // Fall back to the shared status' caption so clients that render the
        // top level - the official app draws `post.content` when there is no
        // media - show the post rather than an empty card. Clients that
        // resolve `reblog` ignore this on a boost.
        $caption = $status->caption ?: ($reblog['content_text'] ?? null);
        $rendered = $caption ? nl2br(Autolink::create()->autolink($caption)) : '';

        return [
            '_v' => 1,
            'id' => (string) $status->id,
            // 'gid'						=> $status->group_id ? (string) $status->group_id : null,
            'shortcode' => HashidService::encode($status->id),
            'uri' => $status->url(),
            'url' => $status->url(),
            'in_reply_to_id' => $status->in_reply_to_id ? (string) $status->in_reply_to_id : null,
            'in_reply_to_account_id' => $status->in_reply_to_profile_id ? (string) $status->in_reply_to_profile_id : null,
            'reblog' => $reblog,
            'content' => $rendered,
            'content_text' => $caption,
            'created_at' => str_replace('+00:00', 'Z', $status->created_at->format(DATE_RFC3339_EXTENDED)),
            'emojis' => CustomEmoji::scan($caption),
            'reblogs_count' => $status->reblogs_count ?? 0,
            'favourites_count' => $status->likes_count ?? 0,
            'reblogged' => null,
            'favourited' => null,
            'muted' => null,
            'sensitive' => (bool) $status->is_nsfw,
            'spoiler_text' => $status->cw_summary ?? '',
            'visibility' => $status->scope ?? $status->visibility,
            'application' => [
                'name' => 'web',
                'website' => null,
            ],
            'language' => null,
            'mentions' => StatusMentionService::get($status->id),
            'pf_type' => $status->type ?? $status->setType(),
            'reply_count' => (int) $status->reply_count,
            'comments_disabled' => (bool) $status->comments_disabled,
            'thread' => false,
            'replies' => [],
            'parent' => [],
            'place' => $status->place,
            'local' => (bool) $status->local,
            'taggedPeople' => $taggedPeople,
            'liked_by' => LikeService::likedBy($status),
            'media_attachments' => self::mediaAttachments($status),
            'account' => AccountService::get($status->profile_id, true),
            'tags' => StatusHashtagService::statusTags($status->id),
            'poll' => $poll,
            'edited_at' => $status->edited_at ? str_replace('+00:00', 'Z', $status->edited_at->format(DATE_RFC3339_EXTENDED)) : null,
            'pinned' => (bool) $status->pinned_order,
        ];
    }

    /**
     * Media for the `media_attachments` field, falling back to the status
     * this one boosts.
     *
     * A boost (`share`) owns no media rows of its own - its images belong to
     * the status it shares - so `media_attachments` is empty and the images
     * are only reachable through the nested `reblog` object. Mastodon-shaped
     * clients resolve that nesting themselves (the web UI does exactly this:
     * `this.post.reblog ? this.post.reblog : this.post`), but some only ever
     * read the top-level field - the official Pixelfed app renders a boost
     * from `post.media_attachments` alone, and draws a bare header line with
     * no image for every boost.
     *
     * Returning the shared status' media here lets those clients render the
     * boost. Clients that unwrap `reblog` ignore this field on a boost, so
     * they are unaffected - see pixelfed/pixelfed-rn#473 and #490.
     */
    protected static function mediaAttachments(Status $status): array
    {
        $media = MediaService::get($status->id);

        if (! empty($media) || ! $status->reblog_of_id) {
            return $media;
        }

        return MediaService::get($status->reblog_of_id);
    }
}
