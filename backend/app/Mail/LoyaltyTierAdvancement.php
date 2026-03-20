<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LoyaltyTierAdvancement extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public User $user,
        public string $oldTier,
        public string $newTier
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Congratulations! You\'ve Advanced to ' . ucfirst($this->newTier) . ' Tier',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.loyalty.tier_advancement',
            with: [
                'user' => $this->user,
                'old_tier' => $this->oldTier,
                'new_tier' => $this->newTier,
                'benefits' => $this->getTierBenefits($this->newTier),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }

    /**
     * Get tier benefits for display.
     */
    private function getTierBenefits(string $tier): array
    {
        return match($tier) {
            'silver' => [
                'Earn 6% Diecast Credits on purchases',
                'Early access to new releases',
                'Priority customer support',
            ],
            'gold' => [
                'Earn 8% Diecast Credits on purchases',
                'Exclusive pre-order opportunities',
                'Free shipping on orders over ₱2,000',
                'Birthday bonus credits',
            ],
            'platinum' => [
                'Earn 10% Diecast Credits on purchases',
                'VIP access to limited editions',
                'Free shipping on all orders',
                'Dedicated account manager',
                'Exclusive events and previews',
            ],
            default => [
                'Earn 5% Diecast Credits on purchases',
                'Access to loyalty rewards program',
            ],
        };
    }
}
