<?php

namespace App\Notifications;

use App\Models\Review;

class ReviewReceivedNotification extends MerchantNotification
{
    public function __construct(public Review $review) {}

    public function getType(): string
    {
        return 'review_received';
    }

    public function getVariables(): array
    {
        return [
            'rating' => (int) $this->review->rating,
            'rating_stars' => str_repeat('★', (int) $this->review->rating) . str_repeat('☆', 5 - (int) $this->review->rating),
            'reviewer_role' => $this->review->reviewer_role,
            'comment' => trim((string) $this->review->comment) !== '' ? $this->review->comment : '(no comment)',
            'trade_hash' => substr((string) ($this->review->trade?->trade_hash ?? ''), 0, 10) . '...',
        ];
    }

    protected function getTradeId(): ?int
    {
        return $this->review->trade_id;
    }

    protected function getActionUrl(): string
    {
        return url('/reviews');
    }

    protected function getActionText(): string
    {
        return __('notifications.action.view_reviews');
    }
}
