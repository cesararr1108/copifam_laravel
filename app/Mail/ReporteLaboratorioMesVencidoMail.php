<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReporteLaboratorioMesVencidoMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $laboratorio;
    public string $nit;
    public int $mes;
    public int $ano;
    public string $nombreMes;
    public string $rutaArchivo;
    public string $nombreArchivo;

    /**
     * Create a new message instance.
     */
    public function __construct(
        string $laboratorio,
        string $nit,
        int $mes,
        int $ano,
        string $rutaArchivo
    ) {
        $this->laboratorio = $laboratorio;
        $this->nit = $nit;
        $this->mes = $mes;
        $this->ano = $ano;
        $this->rutaArchivo = $rutaArchivo;
        $this->nombreArchivo = basename($rutaArchivo);

        $meses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ];
        $this->nombreMes = $meses[$mes] ?? (string)$mes;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "COPIFAM: Reporte de Stock y Compras Mes Vencido ({$this->nombreMes} {$this->ano}) - {$this->laboratorio}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.reporte_laboratorio',
            with: [
                'laboratorio' => $this->laboratorio,
                'nit' => $this->nit,
                'mes' => $this->mes,
                'ano' => $this->ano,
                'nombreMes' => $this->nombreMes,
                'nombreArchivo' => $this->nombreArchivo,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        if (file_exists($this->rutaArchivo)) {
            return [
                Attachment::fromPath($this->rutaArchivo)
                    ->as($this->nombreArchivo)
                    ->withMime('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
            ];
        }

        return [];
    }
}
