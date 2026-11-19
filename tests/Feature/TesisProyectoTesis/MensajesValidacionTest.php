<?php

namespace Tests\Feature\TesisProyectoTesis;

use App\Modules\TesisProyectoTesis\Database\Seeders\EscenariosTesisSeeder;
use App\Modules\TesisProyectoTesis\Models\Proyecto;
use App\Modules\TesisProyectoTesis\Requests\TesisFormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Validator;
use ReflectionClass;

/**
 * Ningún mensaje de validación del módulo muestra una clave («validation.required») ni texto en inglés.
 */
final class MensajesValidacionTest extends TesisTestCase
{
    /** @return list<class-string<TesisFormRequest>> */
    private function requests(): array
    {
        $clases = [];
        foreach (glob(app_path('Modules/TesisProyectoTesis/Requests/*.php')) as $archivo) {
            $clase = 'App\\Modules\\TesisProyectoTesis\\Requests\\'.basename($archivo, '.php');
            if (! (new ReflectionClass($clase))->isAbstract()) {
                $clases[] = $clase;
            }
        }

        return $clases;
    }

    /** Payloads incorrectos que disparan cada tipo de regla (requeridos, tipos, rangos, anidados). */
    private function payloadsMalos(array $reglas): array
    {
        $tipos = [];
        $largos = [];
        $anidados = [];
        foreach (array_keys($reglas) as $clave) {
            if (str_contains($clave, '*')) {
                [$raiz] = explode('.', $clave);
                $resto = substr($clave, strlen($raiz) + 3); // tras «raiz.*.»
                $anidados[$raiz] ??= [[]];
                if ($resto === false || $resto === '') {
                    $anidados[$raiz] = ['1' => str_repeat('x', 5000)];
                } else {
                    $anidados[$raiz][0][$resto] = 'x';
                }

                continue;
            }
            $tipos[$clave] = 'x';
            $largos[$clave] = str_repeat('9', 5000);
        }

        return [[], $tipos, $largos, $anidados, array_map(fn () => ['no', 'es', 'texto'], $tipos), array_map(fn () => UploadedFile::fake()->create('a.txt', 20000, 'text/plain'), $tipos)];
    }

    public function test_ningun_formrequest_muestra_claves_ni_ingles(): void
    {
        $this->como('director@portal.local');
        $proyecto = $this->proyecto();
        $revisados = 0;

        foreach ($this->requests() as $clase) {
            /** @var TesisFormRequest $req */
            $req = $clase::create('/tesis-proyecto-tesis/prueba', 'POST');
            $req->setContainer(app())->setRedirector(app('redirect'));
            $req->setUserResolver(fn () => auth()->user());
            $ruta = (new Route('POST', 'prueba/{proyecto}', []))->name('tesis-proyecto-tesis.asesor.prueba');
            $ruta->bind($req);
            $ruta->setParameter('proyecto', $proyecto);
            $req->setRouteResolver(fn () => $ruta);

            $reglas = $req->rules();
            $this->assertNotEmpty($req->attributes(), "$clase define attributes()");
            foreach ($this->payloadsMalos($reglas) as $datos) {
                $mensajes = Validator::make($datos, $reglas, $req->messages(), $req->attributes())->errors()->all();
                foreach ($mensajes as $m) {
                    $this->assertStringNotContainsString('validation.', $m, "$clase: $m");
                    $this->assertDoesNotMatchRegularExpression('/\b(The|field|must be|is required|may not)\b/', $m, "$clase en inglés: $m");
                }
                $revisados += count($mensajes);
            }
        }

        $this->assertGreaterThan(100, $revisados, 'Se revisaron mensajes de todas las reglas.');
    }

    public function test_campo_oculto_faltante_dice_que_hacer(): void
    {
        $this->irASemanaNueveDelPeriodo();
        $this->seed(EscenariosTesisSeeder::class);
        $p = Proyecto::query()->where('codigo', 'PT-DEMO-05')->firstOrFail();

        $this->como('asesor@portal.local')->from(route('tesis-proyecto-tesis.asesor.verificar', $p))
            ->post(route('tesis-proyecto-tesis.asesor.verificar.guardar', $p), ['marcas' => [1 => 'cumple']])
            ->assertSessionHasErrors(['accion' => 'No se recibió qué acción elegiste. Vuelve a pulsar «Dar conformidad», «Guardar y enviar observaciones» o «Registrar revisión intermedia».']);

        // Lo que ve el usuario al volver al formulario.
        $this->como('asesor@portal.local')->from(route('tesis-proyecto-tesis.asesor.verificar', $p))->followingRedirects()
            ->post(route('tesis-proyecto-tesis.asesor.verificar.guardar', $p), ['marcas' => [1 => 'cumple']])
            ->assertOk()->assertDontSee('validation.')->assertSee('No se recibió qué acción elegiste');
    }

    public function test_busqueda_de_coautor_invalida_responde_en_espanol(): void
    {
        $this->como('alumno@portal.local')->getJson(route('tesis-proyecto-tesis.alumno.coautor', ['codigo' => 'código raro!']))
            ->assertStatus(422)->assertJson(['encontrado' => false, 'mensaje' => 'El código solo admite números, letras y guiones.']);
    }
}
