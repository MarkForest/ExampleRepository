<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentReceivedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        private readonly int $paymentId,
        private readonly string $amount,
        private readonly string $currency
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Підтвердження платежу')
            ->line("Ваш платіж №{$this->paymentId} на суму {$this->amount} {$this->currency} успішно проведений.")
            ->line('Дякуємо, що користуєтеся нашими послугами.');
    }

    /**
     * @param object $notifiable
     * @return array
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'payment_id' => $this->paymentId,
            'amount' => $this->amount,
            'currency' => $this->currency,
        ];
    }
}
