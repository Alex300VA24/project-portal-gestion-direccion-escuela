<?php

namespace App\Modules\Bienestar\Exceptions;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class FaltanDatosDemoException extends Exception
{
    public const MENSAJE_BASE = 'Faltan datos de demostración: ejecute php artisan db:seed --class="App\\Modules\\Bienestar\\Database\\Seeders\\BienestarDemoSeeder"';

    public function __construct(string $detalle = '', int $code = 0, ?\Throwable $previous = null)
    {
        $mensaje = self::MENSAJE_BASE;
        if ($detalle !== '') {
            $mensaje .= " ({$detalle})";
        }

        parent::__construct($mensaje, $code, $previous);
    }

    public function render(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'error' => self::MENSAJE_BASE,
                'detalle' => $this->getMessage(),
            ], 200);
        }

        return response()->view('modules.bienestar.errors.faltan_datos_demo', [
            'mensaje' => self::MENSAJE_BASE,
            'detalle' => $this->getMessage(),
        ], 200);
    }
}
