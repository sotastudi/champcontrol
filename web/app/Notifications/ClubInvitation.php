<?php

namespace App\Notifications;

use App\Models\Event;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Aviso por correo a un club al que un organizador invita a participar en un evento. */
class ClubInvitation extends Notification
{
    public function __construct(private Event $event) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Invitación a «'.$this->event->name.'»')
            ->greeting('Hola, '.$notifiable->name)
            ->line('El organizador '.$this->event->user->name.' invita a tu club a participar en el evento «'.$this->event->name.'».')
            ->line($this->event->discipline.' · '.$this->event->startsAtLabel().' · '.$this->event->venue)
            ->action('Ver y confirmar la invitación', route('panel.invitations.index'))
            ->line('Si aceptas, tu club quedará incluido en el evento y podrá inscribir a sus competidores.');
    }
}
