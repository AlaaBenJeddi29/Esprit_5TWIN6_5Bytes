<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email d'envoi automatique du rapport global Gestion 1 (PDF en pièce jointe).
 */
class RapportGestionMailable extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param string $pdf Contenu binaire du PDF généré par DomPDF
     * @param string $nomFichier Nom du fichier affiché dans la pièce jointe
     */
    public function __construct(
        public readonly string $pdf,
        public readonly string $nomFichier,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Rapport global Gestion 1 — AquaSecure',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.rapport-gestion',
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdf, $this->nomFichier)
                ->withMime('application/pdf'),
        ];
    }
}
