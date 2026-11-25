<?php

namespace App\Modules\GestionHorarios\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/** Ciclo de vida LOCAL. No habilita la publicación institucional ni altera Core. */
final class CicloVidaHorarioDemo
{
    public const TABLA = 'gestion_horarios.qa_publicacion_evento';
    public const CALENDARIO = 'gestion_horarios.qa_calendario_revision';
    public const DIAS = ['LUNES','MARTES','MIERCOLES','JUEVES','VIERNES'];

    public static function disponible(): bool
    {
        return DB::connection()->getSchemaBuilder()->hasTable(self::TABLA) && DB::connection()->getSchemaBuilder()->hasTable(self::CALENDARIO);
    }

    public static function normalizarCalendario(array $dias, array $sabados): array
    {
        if (!$dias || array_diff($dias,self::DIAS) || count($dias)!==count(array_unique($dias))) throw new InvalidArgumentException('Seleccione días laborables válidos.');
        $ciclos=[];
        foreach ($sabados as $ciclo) {
            if (!is_scalar($ciclo) || !preg_match('/^(?:[1-9]|10)$/D',(string)$ciclo)) throw new InvalidArgumentException('Ciclo de excepción inválido.');
            $ciclos[]=(int)$ciclo;
        }
        $ciclos=array_values(array_unique($ciclos)); sort($ciclos);
        return ['dias'=>array_values(array_intersect(self::DIAS,$dias)), 'sabados_ciclos'=>$ciclos];
    }

    public static function calendario(): array
    {
        $r=DB::table(self::CALENDARIO)->orderByDesc('version')->first();
        return $r ? ['version'=>(int)$r->version]+json_decode($r->datos,true,512,JSON_THROW_ON_ERROR) : ['version'=>0,'dias'=>self::DIAS,'sabados_ciclos'=>[]];
    }

    public static function sinCalculo(): void
    {
        DB::statement('LOCK TABLE '.ColaGeneracionDemo::TABLA.' IN SHARE ROW EXCLUSIVE MODE');
        if (DB::table(ColaGeneracionDemo::TABLA)->whereIn('estado',ColaGeneracionDemo::ACTIVOS)->exists()) throw new \RuntimeException('Hay un cálculo activo.');
    }

    public static function guardarCalendario(User $usuario,int $esperada,array $dias,array $sabados): void
    {
        abort_unless(ConsultaLocalDemostracion::permitida($usuario) && self::disponible(),403);
        try { $nuevo=self::normalizarCalendario($dias,$sabados); }
        catch (InvalidArgumentException $e) { throw ValidationException::withMessages(['calendario'=>$e->getMessage()]); }
        DB::transaction(function () use ($usuario,$esperada,$nuevo) {
            DB::statement('LOCK TABLE '.self::CALENDARIO.' IN SHARE ROW EXCLUSIVE MODE');
            self::sinCalculo();
            $actual=self::calendario();
            if ($actual['version']!==$esperada) throw ValidationException::withMessages(['calendario'=>'El calendario cambió. Recargue antes de guardar.']);
            if ($nuevo===array_diff_key($actual,['version'=>true])) return;
            DB::table(self::CALENDARIO)->insert(['version'=>$esperada+1,'datos'=>json_encode($nuevo,JSON_THROW_ON_ERROR),'actor'=>$usuario->email]);
        },3);
    }

    public static function eventos(string $id)
    {
        return DB::table(self::TABLA)->where('propuesta',$id)->orderByDesc('id')->get(['id','version','evento','actor','motivo','creado_en']);
    }

    public static function publicacion(string $id): ?object
    {
        $r=DB::table(self::TABLA)->where('propuesta',$id)->whereIn('evento',['PUBLICADO','RETIRADO'])->orderByDesc('id')->first();
        return $r && $r->evento==='PUBLICADO' ? $r : null;
    }

    public static function decidir(string $accion,int $version,?int $aprobada,?int $publicada): ?string
    {
        return match($accion) {
            'aprobar'=>$aprobada===$version ? null : 'APROBADO',
            'publicar'=>$aprobada!==$version ? throw new InvalidArgumentException('Apruebe primero esta versión.') : ($publicada===$version ? null : 'PUBLICADO'),
            'retirar'=>$publicada!==$version ? throw new InvalidArgumentException('La publicación cambió o ya fue retirada.') : 'RETIRADO',
            default=>throw new InvalidArgumentException('Operación desconocida.'),
        };
    }

    /** Invocar exclusivamente bajo transacción y bloqueo de revisión, cola y eventos. */
    public static function registrar(string $id,int $version,string $accion,string $motivo,?array $instantanea=null): ?string
    {
        DB::statement('LOCK TABLE '.self::TABLA.' IN SHARE ROW EXCLUSIVE MODE');
        $aprobada=DB::table(self::TABLA)->where('propuesta',$id)->where('evento','APROBADO')->orderByDesc('id')->value('version');
        $publicada=self::publicacion($id);
        $evento=self::decidir($accion,$version,$aprobada===null?null:(int)$aprobada,$publicada?(int)$publicada->version:null);
        if ($evento===null) return null;
        if ($evento==='PUBLICADO' && !$instantanea) throw new InvalidArgumentException('Falta la instantánea publicada.');
        DB::table(self::TABLA)->insert(['propuesta'=>$id,'version'=>$version,'evento'=>$evento,'actor'=>'director.qa@horarios.test','motivo'=>$motivo,'instantanea'=>$evento==='PUBLICADO'?json_encode($instantanea,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE):null]);
        return $evento;
    }
}
