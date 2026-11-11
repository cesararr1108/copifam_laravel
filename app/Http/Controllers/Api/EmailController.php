<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class EmailController extends Controller
{
    /**
     * Enviar correo con HTML personalizado
     * POST: /api/email/enviar
     * 
     * Body JSON: 
     * {
     *     "to": "email1@gmail.com;email2@gmail.com",
     *     "cc": "email3@gmail.com", // Opcional, pueden ser varios separados por punto y coma
     *     "subject": "Asunto del correo",
     *     "message": "<h1>HTML del correo</h1>"
     * }
     */
    public function enviarCorreo(Request $request)
    {
        try {
            // Validar los datos de entrada
            $validated = $request->validate([
                'to'      => 'required|string',
                'cc'      => 'nullable|string',
                'subject' => 'required|string',
                'message' => 'required|string',
            ]);

            // Procesar los correos: pueden venir separados por punto y coma
            $emailsString = $validated['to'];
            $emails = array_filter(array_map('trim', explode(';', $emailsString)));

            if (empty($emails)) {
                return response()->json([
                    'status'  => 400,
                    'message' => 'No hay correos válidos',
                    'success' => false,
                ], 400);
            }

            // Validar que sean emails válidos
            foreach ($emails as $email) {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    return response()->json([
                        'status'  => 400,
                        'message' => "Email inválido: {$email}",
                        'success' => false,
                    ], 400);
                }
            }
            
            // Procesar los correos CC
            $ccEmails = [];
            if (!empty($validated['cc'])) {
                $ccEmailsString = $validated['cc'];
                $ccEmails = array_filter(array_map('trim', explode(';', $ccEmailsString)));
                
                foreach ($ccEmails as $email) {
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        return response()->json([
                            'status'  => 400,
                            'message' => "Email CC inválido: {$email}",
                            'success' => false,
                        ], 400);
                    }
                }
            }

            // Enviar el correo a cada destinatario
            foreach ($emails as $email) {
                Mail::html($validated['message'], function ($message) use ($email, $ccEmails, $validated) {
                    $message->to($email)
                            ->subject($validated['subject']);
                            
                    if (!empty($ccEmails)) {
                        $message->cc($ccEmails);
                    }
                });
            }

            return response()->json([
                'status'  => 200,
                'message' => 'Correos enviados exitosamente',
                'success' => true,
                'emails_count' => count($emails),
                'emails' => $emails,
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status'  => 422,
                'message' => 'Error de validación',
                'errors'  => $e->errors(),
                'success' => false,
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 500,
                'message' => 'Error al enviar correo: ' . $e->getMessage(),
                'success' => false,
            ], 500);
        }
    }
}
