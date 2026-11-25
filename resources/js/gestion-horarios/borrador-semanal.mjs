// Núcleo de estado local, independiente de Vue/Inertia/Livewire.
// No autoriza ni persiste. El adaptador institucional debe validar todas las operaciones.
const copiar = valor => JSON.parse(JSON.stringify(valor));
const clave = propuesta => `${propuesta.id}:${propuesta.version}`;

export function crearBorradorSemanal(propuesta, validarUbicacion) {
    if (typeof validarUbicacion !== 'function') throw new TypeError('Se requiere un validador de ubicación.');
    let base = copiar(propuesta);
    let sesiones = copiar(base.sesiones);
    let historial = [];
    let remota = null;
    const exigirVigencia = () => {
        if (remota) throw new Error('La propuesta cambió. Revise la nueva versión antes de guardar.');
    };
    const recordar = () => historial.push(copiar(sesiones));
    const actividadDe = id => base.actividades.find(a => String(a.id) === String(id));
    function pendientes() {
        const ocupadas = new Set(sesiones.map(s => s.sesion_motor));
        return base.actividades.flatMap(a => a.duraciones.map((duracion, i) => ({
            sesion_motor: `${a.id}:${i + 1}`, actividad_id: a.id, duracion,
        }))).filter(s => !ocupadas.has(s.sesion_motor));
    }
    function recibir(propuestaNueva) {
        if (clave(base) === clave(propuestaNueva)) return;
        if (historial.length || remota) { remota = copiar(propuestaNueva); return; }
        base = copiar(propuestaNueva);
        sesiones = copiar(base.sesiones);
    }
    function colocar(id, dia, inicio, ambienteId) {
        exigirVigencia();
        const previa = sesiones.find(s => s.sesion_motor === id);
        const prevista = previa || pendientes().find(s => s.sesion_motor === id);
        if (!prevista || previa?.fijada) throw new Error('Sesión inexistente o fijada.');
        const actividad = actividadDe(prevista.actividad_id);
        const indice = Number(id.split(':').at(-1)) - 1;
        const duracion = actividad?.duraciones?.[indice];
        if (!Number.isInteger(inicio) || !Number.isInteger(duracion) || duracion < 1) throw new Error('Hora o duración inválida.');
        const nueva = { sesion_motor: id, actividad_id: prevista.actividad_id,
            dia, inicio, fin: inicio + duracion, ambiente_id: actividad.requiere_ambiente === false ? null : ambienteId,
            fijada: false, origen: 'MANUAL' };
        const conflictos = validarUbicacion(copiar(nueva), copiar(sesiones.filter(s => s.sesion_motor !== id)), copiar(base));
        if (!Array.isArray(conflictos)) throw new Error('Respuesta de validación inválida.');
        if (conflictos.length) return { aplicada: false, conflictos };
        recordar();
        sesiones = [...sesiones.filter(s => s.sesion_motor !== id), nueva];
        return { aplicada: true, conflictos: [] };
    }
    function retirar(id) {
        exigirVigencia();
        const sesion = sesiones.find(s => s.sesion_motor === id);
        if (!sesion || sesion.fijada) throw new Error('Sesión inexistente o fijada.');
        recordar(); sesiones = sesiones.filter(s => s.sesion_motor !== id);
    }
    function deshacer() { exigirVigencia(); if (historial.length) sesiones = historial.pop(); }
    function descartar() {
        if (remota) base = remota;
        remota = null; historial = []; sesiones = copiar(base.sesiones);
    }
    function datosGuardado() { exigirVigencia(); return { version: base.version, sesiones: copiar(sesiones) }; }
    return { pendientes, recibir, colocar, retirar, deshacer, descartar, datosGuardado,
        estado: () => ({ sesiones: copiar(sesiones), version: base.version, propuestaId: base.id,
            cambioRemoto: remota !== null, modificaciones: historial.length }) };
}
