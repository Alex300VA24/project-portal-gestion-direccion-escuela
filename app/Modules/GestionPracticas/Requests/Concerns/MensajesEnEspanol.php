<?php

namespace App\Modules\GestionPracticas\Requests\Concerns;

/**
 * Mensajes de validación en español para los formularios del módulo.
 *
 * El portal usa el idioma «es» pero no incluye traducciones de validación, por lo que sin esto el usuario vería
 * claves como «validation.digits». Cada Request puede completar o afinar los textos con mensajesPropios().
 */
trait MensajesEnEspanol
{
    /** @return array<string, string> */
    public function messages(): array
    {
        return [...$this->mensajesGenerales(), ...$this->mensajesPropios()];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'ruc' => 'RUC', 'razon_social' => 'razón social', 'nombre_comercial' => 'nombre comercial',
            'direccion' => 'dirección', 'telefono' => 'teléfono', 'pagina_web' => 'página web',
            'contacto_dni' => 'DNI del representante', 'contacto_nombres' => 'nombres',
            'contacto_apellido_paterno' => 'apellido paterno', 'correo_acceso' => 'correo de acceso',
            'correo' => 'correo de contacto', 'password' => 'contraseña', 'current_password' => 'contraseña actual',
            'password_confirmation' => 'confirmación de la contraseña',
            'titulo' => 'título', 'descripcion' => 'descripción', 'requisitos' => 'requisitos',
            'enlace_postulacion' => 'enlace de postulación', 'tipo_practica' => 'tipo de práctica',
            'modalidad' => 'modalidad', 'vacantes' => 'vacantes', 'fecha_cierre' => 'fecha de cierre',
            'area' => 'área', 'funciones' => 'funciones', 'fecha_inicio' => 'fecha de inicio', 'fecha_fin' => 'fecha de fin',
            'horas_totales' => 'horas planificadas', 'id_periodo' => 'periodo académico', 'carta' => 'carta de aceptación',
            'tipo_documento' => 'tipo de documento', 'archivo' => 'archivo', 'id_docente' => 'docente supervisor',
            'fecha' => 'fecha', 'tipo' => 'tipo', 'observaciones' => 'observaciones', 'comentario' => 'observación final',
            'notas' => 'notas', 'notas.*' => 'nota', 'resultado' => 'resultado', 'estado' => 'estado',
            ...$this->atributosPropios(),
        ];
    }

    /** Textos específicos de un formulario; sobrescriben a los generales. @return array<string, string> */
    protected function mensajesPropios(): array
    {
        return [];
    }

    /** @return array<string, string> */
    protected function atributosPropios(): array
    {
        return [];
    }

    /** @return array<string, string> */
    private function mensajesGenerales(): array
    {
        return [
            'required' => 'Complete el campo «:attribute».',
            'string' => 'El campo «:attribute» debe ser un texto.',
            'max.string' => 'El campo «:attribute» no puede tener más de :max caracteres.',
            'min.string' => 'El campo «:attribute» debe tener al menos :min caracteres.',
            'max.numeric' => 'El campo «:attribute» no puede ser mayor que :max.',
            'min.numeric' => 'El campo «:attribute» no puede ser menor que :min.',
            'max.file' => 'El archivo de «:attribute» no puede pesar más de :max KB.',
            'email' => 'Escriba en «:attribute» un correo electrónico válido, por ejemplo nombre@empresa.com.',
            'url' => 'El campo «:attribute» debe ser un enlace válido que comience con http:// o https://.',
            'date' => 'Escriba en «:attribute» una fecha válida.',
            'digits' => 'El campo «:attribute» debe tener exactamente :digits dígitos numéricos.',
            'integer' => 'El campo «:attribute» debe ser un número entero.',
            'numeric' => 'El campo «:attribute» debe ser un número.',
            'in' => 'La opción elegida en «:attribute» no es válida. Seleccione una de la lista.',
            'exists' => 'La opción elegida en «:attribute» no existe.',
            'file' => 'Seleccione un archivo válido en «:attribute».',
            'mimes' => 'El archivo de «:attribute» debe ser de tipo: :values.',
            'uploaded' => 'No se pudo subir el archivo de «:attribute». Verifique que no supere el tamaño permitido.',
            'confirmed' => 'La confirmación de «:attribute» no coincide.',
            'current_password' => 'La contraseña actual no es correcta.',
            'different' => 'El campo «:attribute» debe ser distinto de :other.',
            'array' => 'El campo «:attribute» no es válido.',
            'starts_with' => 'El campo «:attribute» debe comenzar con: :values.',
            'after' => 'La fecha de «:attribute» debe ser posterior a :date.',
            'after_or_equal' => 'La fecha de «:attribute» no puede ser anterior a :date.',
            'before_or_equal' => 'La fecha de «:attribute» no puede ser posterior a :date.',
        ];
    }
}
