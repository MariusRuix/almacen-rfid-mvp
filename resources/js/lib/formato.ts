const numero = new Intl.NumberFormat('es-MX');
const fechaCorta = new Intl.DateTimeFormat('es-MX', { day: 'numeric', month: 'short', year: 'numeric' });
const fechaHora = new Intl.DateTimeFormat('es-MX', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });

export const n = (v: number | null | undefined) => (v === null || v === undefined ? '—' : numero.format(v));

export const conSigno = (v: number | null | undefined) => (v === null || v === undefined ? '—' : v > 0 ? `+${numero.format(v)}` : numero.format(v));

function aFecha(iso: string): Date {
    return new Date(iso.length === 10 ? `${iso}T12:00:00` : iso.replace(' ', 'T'));
}

export const fecha = (iso: string | null | undefined) => (iso ? fechaCorta.format(aFecha(iso)) : '—');
export const fechaYHora = (iso: string | null | undefined) => (iso ? fechaHora.format(aFecha(iso)) : '—');
