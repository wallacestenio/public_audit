<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExecutionPlan extends Model
{
    protected $table = 'execution_plans';

    protected $fillable = [
        'name',
        'version',
        'audit_type',
        'status',
        'normative_summary',
        'hash_fingerprint',
        'created_by',
        'activated_at',
    ];

    protected $casts = [
        'activated_at' => 'datetime',
    ];

    /*
     |--------------------------------------------------------------------------
     | Escopos úteis
     |--------------------------------------------------------------------------
     */

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForAuditType($query, string $auditType)
    {
        return $query->whereIn('audit_type', [$auditType, 'ambos']);
    }

    /*
     |--------------------------------------------------------------------------
     | Regras de governança
     |--------------------------------------------------------------------------
     */

    /**
     * Ativa este plano e arquiva os demais do mesmo tipo.
     */
    public function activate(): void
    {
        \DB::transaction(function () {
            // Arquiva planos ativos anteriores
            self::where('audit_type', $this->audit_type)
                ->where('status', 'active')
                ->where('id', '!=', $this->id)
                ->update(['status' => 'archived']);

            // Gera hash do conteúdo normativo
            $this->hash_fingerprint = hash('sha256', $this->normative_summary);
            $this->status = 'active';
            $this->activated_at = now();

            $this->save();
        });
    }

    /**
     * Indica se o plano pode ser editado.
     */
    public function isEditable(): bool
    {
        return $this->status !== 'active';
    }
}