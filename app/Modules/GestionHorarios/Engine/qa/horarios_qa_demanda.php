<?php
/** Local-only browser QA. Do not expose outside the loopback QA server. */

declare(strict_types=1);

use App\Models\User;
use App\Modules\GestionHorarios\Contracts\ProveedorEscuelasAutorizadas;
use App\Modules\GestionHorarios\Contracts\ProveedorPermisoGestionAcademica;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

$cli = PHP_SAPI === 'cli';
$http = PHP_SAPI === 'cli-server';
if ((! $cli && ! $http) || ($cli && ($argv[1] ?? '') !== 'init')
    || ($http && (($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1'
        || ($_SERVER['HTTP_HOST'] ?? '') !== '127.0.0.1:8001'
        || (string) ($_SERVER['SERVER_PORT'] ?? '') !== '8001'))
    || getenv('APP_ENV') !== 'testing'
    || getenv('DB_CONNECTION') !== 'pgsql'
    || getenv('DB_DATABASE') !== 'horarios_demo_2026_ii_demanda'
    || getenv('HORARIOS_DEMO_PLAN') !== 'QA-HORARIOS' || getenv('HORARIOS_QA_CONSULTAS') !== '1') {
    if ($http) { http_response_code(403); }
    exit("QA local: entorno no permitido.\n");
}

$root = dirname(__DIR__, 5);
if (! is_file($root.'/vendor/autoload.php') || ! is_file($root.'/bootstrap/app.php')) {
    exit("Ejecute desde un repositorio del portal con Composer instalado.\n");
}
chdir($root);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$kernel = $app->make($cli ? ConsoleKernel::class : HttpKernel::class);
$kernel->bootstrap();
$db = DB::connection();
if (! $app->environment('testing') || $db->getDriverName() !== 'pgsql'
    || $db->getConfig('host') !== '127.0.0.1'
    || $db->selectOne('select current_database() as nombre')->nombre !== 'horarios_demo_2026_ii_demanda') {
    if ($http) { http_response_code(403); }
    exit("QA local: conexión rechazada; no se escribieron datos.\n");
}
$planes = $db->table('core.plan_estudios')->get(['id_plan','id_escuela','codigo']);
if ($planes->count() !== 1 || $planes->first()->codigo !== 'QA-HORARIOS'
    || (int) $planes->first()->id_escuela <= 0
    || $db->table('core.periodo_academico')->pluck('codigo')->all() !== ['2026-II']
    || $db->table('core.docente as d')->leftJoin('core.persona as p','p.id_persona','=','d.id_persona')
        ->where(fn ($q) => $q->whereNull('p.id_persona')->orWhereNull('p.tipo_documento')
            ->orWhere('p.tipo_documento','!=','QA')->orWhere('d.codigo_docente','not like','QA-%'))->exists()) {
    if ($http) { http_response_code(403); }
    exit("QA local: catálogo de prueba no encontrado o ambiguo.\n");
}
$plan = $planes->first();
$email = 'director.qa@horarios.test';
if ($cli) {
    $password = bin2hex(random_bytes(12));
    $db->transaction(function () use ($email, $password) {
        $permission = Permission::findOrCreate('gestion-horarios.view', 'web');
        $role = Role::findOrCreate('director_escuela', 'web');
        $role->givePermissionTo($permission);
        $user = User::firstOrNew(['email' => $email]);
        $user->forceFill(['name' => 'Director QA Horarios', 'password' => Hash::make($password), 'email_verified_at' => now()]);
        $user->save();
        $user->syncRoles([$role]);
    });
    $app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    echo "Usuario de demostración (solo base aislada):\nCorreo: $email\nContraseña: $password\n";
    exit;
}

$user = User::where('email', $email)->first();
if (! $user || ! $user->hasRole('director_escuela', 'web') || ! $user->can('gestion-horarios.view')) {
    http_response_code(403);
    exit("Ejecute primero el script con el argumento init.\n");
}

// Proveedor de demostración: solo en este proceso y tras verificar el catálogo aislado.
$app->instance(ProveedorEscuelasAutorizadas::class, new class((int) $user->getKey(), (int) $plan->id_escuela) implements ProveedorEscuelasAutorizadas {
    public function __construct(private readonly int $id, private readonly int $escuela) {}
    public function escuelasVigentes(User $usuario): array
    {
        return (int) $usuario->getKey() === $this->id && $usuario->hasRole('director_escuela', 'web')
            && $usuario->can('gestion-horarios.view') ? [$this->escuela] : [];
    }
});
$app->instance(ProveedorPermisoGestionAcademica::class, new class((int) $user->getKey()) implements ProveedorPermisoGestionAcademica {
    public function __construct(private readonly int $id) {}
    public function puedeGestionar(User $usuario): bool {
        return (int) $usuario->getKey() === $this->id
            && $usuario->hasRole('director_escuela', 'web')
            && $usuario->can('gestion-horarios.view');
    }
});
$app['config']->set('app.url', 'http://127.0.0.1:8001');
$app['config']->set('session.cookie', 'horarios_qa_demanda_session');
$app['config']->set('session.domain', null);
$app['config']->set('session.secure', false);

$public = realpath($root.'/public');
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$asset = realpath($public.$path);
if ($asset !== false && str_starts_with($asset, $public.DIRECTORY_SEPARATOR)
    && is_file($asset) && strtolower(pathinfo($asset, PATHINFO_EXTENSION)) !== 'php') {
    return false;
}

$_SERVER['SCRIPT_FILENAME'] = $root.'/public/index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';
$app->handleRequest(Request::capture());
