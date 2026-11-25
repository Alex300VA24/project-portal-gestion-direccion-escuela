import { test } from 'node:test';
import assert from 'node:assert/strict';
import { crearBorradorSemanal } from '../../../../resources/js/gestion-horarios/borrador-semanal.mjs';
const propuesta = {id: 'p1', version: 1, actividades: [{id: 'a', duraciones: [2,1]}], sesiones: []};
test('colocar, pendientes y deshacer', () => {
    const b = crearBorradorSemanal(propuesta, () => []);
    assert.equal(b.pendientes().length, 2);
    assert.equal(b.colocar('a:1','LUNES',9,1).aplicada,true);
    assert.equal(b.datosGuardado().sesiones[0].fin,11);
    assert.equal(b.pendientes().length,1);
    b.deshacer(); assert.equal(b.pendientes().length,2);
});
test('rechaza conflictos sin perder borrador', () => {
    const b = crearBorradorSemanal(propuesta, () => ['Cruce']);
    assert.equal(b.colocar('a:1','LUNES',9,1).aplicada,false);
    assert.equal(b.estado().sesiones.length,0);
});
test('cambio remoto bloquea guardado y conserva edición', () => {
    const b = crearBorradorSemanal(propuesta, () => []);
    b.colocar('a:1','LUNES',9,1); b.recibir({...propuesta,version:2});
    assert.equal(b.estado().sesiones.length,1); assert.throws(() => b.datosGuardado());
    b.descartar(); assert.equal(b.datosGuardado().version,2);
});
test('no retira ni mueve sesiones fijadas', () => {
    const b = crearBorradorSemanal({...propuesta,sesiones:[{sesion_motor:'a:1',actividad_id:'a',fijada:true}]}, () => []);
    assert.throws(() => b.retirar('a:1')); assert.throws(() => b.colocar('a:1','LUNES',9,1));
});
