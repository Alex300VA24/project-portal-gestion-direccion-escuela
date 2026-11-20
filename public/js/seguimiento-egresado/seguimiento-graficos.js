/*
 * Modales y gráficos de Seguimiento al Egresado.
 * Sin JavaScript, el contenido de los modales queda visible en la página.
 */
(() => {
  // El foco inicial va al modal: el borde de foco de un botón solo aparece al navegar con teclado.
  // <dialog> mantiene el foco dentro, cierra con Escape y lo devuelve al botón que lo abrió.
  window.sgAbrirModal = (modal) => {
    modal.tabIndex = -1;
    modal.showModal();
    modal.focus();
  };

  document.querySelectorAll('[data-sg-abrir-modal]').forEach((boton) => {
    const modal = document.getElementById(boton.dataset.sgAbrirModal);
    boton.hidden = false;
    boton.addEventListener('click', () => window.sgAbrirModal(modal));
  });
  document.querySelectorAll('.sg-modal').forEach((modal) => {
    const protegido = modal.hasAttribute('data-sg-protegido');
    if (!protegido) modal.querySelector('[data-sg-cerrar-modal]')?.addEventListener('click', () => modal.close());
    modal.addEventListener('click', (evento) => {
      if (evento.target === modal && !protegido) modal.close();
    });
    if (modal.hasAttribute('data-sg-abrir-al-cargar')) window.sgAbrirModal(modal);
  });

  if (typeof Chart === 'undefined') return;

  const estilos = getComputedStyle(document.body);
  const token = (nombre) => estilos.getPropertyValue(`--sg-${nombre}`).trim();
  const colores = {
    navy: token('navy'),
    verde: token('success'),
    indigo: token('accent'),
    ambar: token('warning'),
    rojo: token('danger'),
    gris: token('muted'),
    dorado: token('gold'),
  };

  // Rayas diagonales: la serie se distingue también por su patrón, no solo por el color.
  const conPatron = (color) => {
    const lienzo = document.createElement('canvas');
    lienzo.width = 8;
    lienzo.height = 8;
    const c = lienzo.getContext('2d');
    c.fillStyle = color;
    c.fillRect(0, 0, 8, 8);
    c.strokeStyle = 'rgba(255, 255, 255, .55)';
    c.lineWidth = 2;
    c.beginPath();
    c.moveTo(0, 8);
    c.lineTo(8, 0);
    c.moveTo(-2, 2);
    c.lineTo(2, -2);
    c.moveTo(6, 10);
    c.lineTo(10, 6);
    c.stroke();
    return c.createPattern(lienzo, 'repeat');
  };

  Chart.defaults.font.family = token('font');
  Chart.defaults.color = token('text');
  Chart.defaults.animation = false;

  const etiquetas = {
    id: 'sgEtiquetas',
    afterDatasetsDraw(chart) {
      const { ctx } = chart;
      ctx.save();
      ctx.font = `600 ${chart.width < 420 ? 10 : 12}px ${token('font')}`;
      ctx.textBaseline = 'middle';

      chart.data.datasets.forEach((serie, s) => {
        if (!serie.sgTextos || chart.getDatasetMeta(s).hidden) return;

        chart.getDatasetMeta(s).data.forEach((elemento, i) => {
          const texto = serie.sgTextos[i];
          if (!texto) return;

          const horizontal = chart.options.indexAxis === 'y';
          const apilado = chart.options.scales.x.stacked;

          if (apilado) {
            const ancho = Math.abs(elemento.x - elemento.base);
            if (ancho < ctx.measureText(texto).width + 8) return;
            ctx.fillStyle = token('on-dark');
            ctx.textAlign = 'center';
            ctx.lineWidth = 4;
            ctx.strokeStyle = serie.borderColor;
            ctx.strokeText(texto, (elemento.x + elemento.base) / 2, elemento.y);
            ctx.fillText(texto, (elemento.x + elemento.base) / 2, elemento.y);
          } else if (horizontal) {
            ctx.fillStyle = token('text');
            ctx.textAlign = 'left';
            ctx.fillText(texto, elemento.x + 6, elemento.y);
          } else {
            ctx.fillStyle = token('text');
            ctx.textAlign = 'center';
            ctx.fillText(texto, elemento.x, elemento.y - 10);
          }
        });
      });

      ctx.restore();
    },
  };

  // Las etiquetas largas se reparten en varias líneas para que no se corten en el eje.
  const repartir = (texto, ancho = 24) => {
    const lineas = [];
    let actual = '';
    String(texto).split(' ').forEach((palabra) => {
      if (actual && `${actual} ${palabra}`.length > ancho) {
        lineas.push(actual);
        actual = palabra;
      } else {
        actual = actual ? `${actual} ${palabra}` : palabra;
      }
    });
    if (actual) lineas.push(actual);
    return lineas;
  };

  const crear = (lienzo) => {
    const d = JSON.parse(lienzo.dataset.sgGrafico);
    const horizontal = Boolean(d.horizontal);
    const esLinea = d.tipo === 'line';
    const eje = {
      beginAtZero: true,
      max: d.maximo ?? undefined,
      stacked: Boolean(d.apilado),
      title: { display: Boolean(d.ejeValor), text: d.ejeValor ?? '' },
      ticks: { callback: (v) => (d.unidad === '%' ? `${v} %` : v), precision: 0, stepSize: d.maximo === 100 ? 25 : undefined },
      grid: { color: token('border') },
    };
    const categorias = { stacked: Boolean(d.apilado), grid: { display: false }, ticks: { autoSkip: false } };

    return new Chart(lienzo, {
      type: d.tipo,
      plugins: [etiquetas],
      data: {
        labels: horizontal ? d.etiquetas.map((e) => repartir(e, lienzo.parentElement.clientWidth < 360 ? 14 : 24)) : d.etiquetas,
        datasets: d.series.map((s) => ({
          label: s.nombre,
          data: s.valores,
          sgTextos: s.textos ?? null,
          sgDetalles: s.detalles ?? null,
          backgroundColor: !esLinea && s.patron ? conPatron(colores[s.color] ?? colores.navy) : (colores[s.color] ?? colores.navy),
          borderColor: colores[s.color] ?? colores.navy,
          borderDash: s.discontinua ? [6, 4] : [],
          pointStyle: s.punto ?? 'circle',
          pointRadius: esLinea ? 5 : 0,
          borderWidth: esLinea ? 2 : 0,
          maxBarThickness: 44,
          tension: 0,
          spanGaps: false,
        })),
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        indexAxis: horizontal ? 'y' : 'x',
        layout: { padding: { top: horizontal ? 0 : 18, right: horizontal && !d.apilado ? 72 : 8 } },
        scales: horizontal ? { x: eje, y: categorias } : { x: categorias, y: eje },
        plugins: {
          legend: { display: d.series.length > 1, position: 'bottom', labels: { usePointStyle: esLinea, boxHeight: 10 } },
          tooltip: {
            callbacks: {
              title: (elementos) => [].concat(elementos[0]?.label ?? '').join(' '),
              label: (c) => {
                const detalle = c.dataset.sgDetalles?.[c.dataIndex];
                if (detalle) return `${c.dataset.label}: ${detalle}`;
                const valor = c.formattedValue;
                return `${c.dataset.label}: ${valor}${d.unidad === '%' ? ' %' : ''}`;
              },
            },
          },
        },
      },
    });
  };

  document.querySelectorAll('canvas[data-sg-grafico]').forEach((lienzo) => {
    const panel = lienzo.closest('.sg-graf');
    const contenedor = panel.querySelector('.sg-graf-lienzo');

    // Se muestra antes de dibujar para conocer el ancho disponible.
    contenedor.hidden = false;

    try {
      crear(lienzo);
    } catch (error) {
      console.error('No se pudo dibujar el gráfico', error);
      contenedor.hidden = true;
      return;
    }

  });
})();
