{{-- Una formación posterior. Variables: $i (índice o __i__), $f (valores), $cat --}}
<div class="sgs-item" data-formacion>
  <div class="sgs-item-head">
    <strong>Formación</strong>
    <button type="button" class="sgs-linkbtn sg-button-secondary" data-quitar-formacion>Quitar</button>
  </div>
  <div class="sg-form-grid">
    @include('modules.seguimiento-egresado.egresado._lista', ['id' => "f{$i}-tipo", 'nombre' => "formaciones[$i][tipo]", 'etiqueta' => 'Tipo', 'opciones' => $cat['tipos_formacion'], 'seleccion' => $f['tipo'] ?? '', 'requerido' => true])
    @include('modules.seguimiento-egresado.egresado._lista', ['id' => "f{$i}-estado", 'nombre' => "formaciones[$i][estado]", 'etiqueta' => 'Estado', 'opciones' => $cat['estados_formacion'], 'seleccion' => $f['estado'] ?? '', 'requerido' => true, 'atributos' => 'data-estado'])
    @include('modules.seguimiento-egresado.egresado._campo', ['id' => "f{$i}-area", 'nombre' => "formaciones[$i][area_formacion]", 'etiqueta' => 'Área o especialidad', 'valor' => $f['area_formacion'] ?? '', 'requerido' => true, 'attrs' => ['minlength' => 2, 'maxlength' => 140]])
    @include('modules.seguimiento-egresado.egresado._campo', ['id' => "f{$i}-inst", 'nombre' => "formaciones[$i][institucion]", 'etiqueta' => 'Institución', 'valor' => $f['institucion'] ?? '', 'requerido' => true, 'attrs' => ['minlength' => 2, 'maxlength' => 160]])
    @include('modules.seguimiento-egresado.egresado._campo', ['id' => "f{$i}-inicio", 'nombre' => "formaciones[$i][fecha_inicio]", 'etiqueta' => 'Fecha de inicio (opcional)', 'tipo' => 'date', 'valor' => $f['fecha_inicio'] ?? '', 'attrs' => ['min' => '1950-01-01', 'max' => now()->toDateString()]])
    <div data-fin>
      @include('modules.seguimiento-egresado.egresado._campo', ['id' => "f{$i}-fin", 'nombre' => "formaciones[$i][fecha_fin]", 'etiqueta' => 'Fecha de fin', 'tipo' => 'date', 'valor' => $f['fecha_fin'] ?? '', 'attrs' => ['min' => '1950-01-01', 'max' => now()->toDateString()]])
    </div>
  </div>
  @include('modules.seguimiento-egresado.egresado._campo', ['id' => "f{$i}-desc", 'nombre' => "formaciones[$i][descripcion]", 'etiqueta' => 'Descripción (opcional)', 'valor' => $f['descripcion'] ?? '', 'attrs' => ['maxlength' => 500]])
</div>
