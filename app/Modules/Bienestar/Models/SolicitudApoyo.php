<?php

namespace App\Modules\Bienestar\Models;

use App\Modules\Bienestar\Services\NotificacionService;
use App\Modules\Bienestar\Support\ActorBienestarResolver;
use App\Shared\Models\Core\Estudiante;
use App\Shared\Models\ModuleModel;
use App\Shared\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SolicitudApoyo extends ModuleModel
{
    protected $table = 'bienestar.solicitud_apoyo';

    protected $primaryKey = 'id_solicitud';

    public $timestamps = false;

    protected $fillable = [
        'id_estudiante',
        'id_beneficio',
        'id_tipo_solicitud',
        'motivo',
        'estado',
        'folio_externo_confirmado',
        'revisado_por_secretaria',
        'fecha_revision_secretaria',
        'fecha_remision_bienestar',
        'observacion_general_secretaria',
        'observacion_dictamen',
        'evaluado_por',
        'solicitada_en',
        'resuelta_en',
    ];

    protected function casts(): array
    {
        return [
            'folio_externo_confirmado' => 'boolean',
            'fecha_revision_secretaria' => 'datetime',
            'fecha_remision_bienestar' => 'datetime',
            'solicitada_en' => 'datetime',
            'resuelta_en' => 'datetime',
        ];
    }

    /** Todo cambio de estado, venga de donde venga, deja rastro y avisa al estudiante. */
    protected static function booted(): void
    {
        $registrar = function (self $solicitud, ?string $anterior) {
            SolicitudHistorial::create([
                'id_solicitud' => $solicitud->id_solicitud,
                'estado_anterior' => $anterior,
                'estado_nuevo' => $solicitud->estado,
                'cambiado_por' => ActorBienestarResolver::resolve()->idUsuario,
                'cambiado_en' => now(),
            ]);
            app(NotificacionService::class)->solicitudCambioEstado($solicitud, $anterior);
        };
        self::created(fn (self $solicitud) => $registrar($solicitud, null));
        self::updated(fn (self $solicitud) => $solicitud->wasChanged('estado') && $registrar($solicitud, $solicitud->getPrevious()['estado'] ?? null));
    }

    public function historial(): HasMany
    {
        return $this->hasMany(SolicitudHistorial::class, 'id_solicitud', 'id_solicitud')->orderByDesc('id_historial');
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class, 'id_estudiante', 'id_estudiante');
    }

    public function beneficio(): BelongsTo
    {
        return $this->belongsTo(Beneficio::class, 'id_beneficio', 'id_beneficio');
    }

    public function tipoSolicitud(): BelongsTo
    {
        return $this->belongsTo(TipoSolicitud::class, 'id_tipo_solicitud', 'id_tipo_solicitud');
    }

    public function revisorSecretaria(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'revisado_por_secretaria', 'id_usuario');
    }

    public function evaluador(): BelongsTo
    {
        return $this->belongsTo(Especialista::class, 'evaluado_por', 'id_especialista');
    }

    public function caso(): BelongsTo
    {
        return $this->belongsTo(CasoBienestar::class, 'id_solicitud', 'id_solicitud_origen');
    }

    public function documentosRequisitos(): HasMany
    {
        return $this->hasMany(SolicitudRequisitoDocumento::class, 'id_solicitud', 'id_solicitud');
    }

    public function scopeEnviadas(Builder $query): Builder
    {
        return $query->where('estado', 'enviada');
    }

    public function scopeRecibidas(Builder $query): Builder
    {
        return $query->where('estado', 'recibida');
    }

    public function scopeObservadas(Builder $query): Builder
    {
        return $query->where('estado', 'observada');
    }

    public function scopeRemitidas(Builder $query): Builder
    {
        return $query->where('estado', 'remitida');
    }

    public function scopeDictaminadas(Builder $query): Builder
    {
        return $query->whereIn('estado', ['aprobada', 'rechazada']);
    }

    /** Secretaría trabaja lo que está en su bandeja y conserva acceso a lo que ella revisó. */
    public function correspondeASecretaria(): bool
    {
        return in_array($this->estado, ['enviada', 'recibida', 'observada', 'remitida'], true)
            || $this->revisado_por_secretaria !== null;
    }

    public function puedeRevisarse(): bool
    {
        return in_array($this->estado, ['enviada', 'recibida'], true);
    }

    public function puedeDictaminarse(): bool
    {
        return $this->estado === 'remitida' && $this->folio_externo_confirmado;
    }
}
