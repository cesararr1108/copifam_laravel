<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class ReporteLaboratorioDestinatario extends Model
{
    protected $table = 'reportes_laboratorios_destinatarios';

    protected $fillable = [
        'nit',
        'laboratorio',
        'correos_para',
        'correos_cc',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Scope para obtener únicamente los laboratorios activos
     */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', 1);
    }

    /**
     * Retorna un arreglo de correos principales limpios y válidos
     */
    public function getCorreosParaArray(): array
    {
        return $this->parseEmails($this->correos_para);
    }

    /**
     * Retorna un arreglo de correos en copia limpios y válidos
     */
    public function getCorreosCcArray(): array
    {
        return $this->parseEmails($this->correos_cc);
    }

    /**
     * Separa correos delimitados por coma (,), punto y coma (;) o saltos de línea
     */
    protected function parseEmails(?string $raw): array
    {
        if (empty($raw)) {
            return [];
        }

        $parts = preg_split('/[;,\\r\\n]+/', $raw);
        $emails = [];

        foreach ($parts as $part) {
            $email = trim($part);
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $email;
            }
        }

        return array_values(array_unique($emails));
    }
}
