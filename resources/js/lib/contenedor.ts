/** Dígito verificador ISO 6346 (mismo cálculo que App\Support\Contenedor). */
export function digitoContenedor(base: string): number {
    const valores: Record<string, number> = {};
    let v = 10;
    for (const letra of 'ABCDEFGHIJKLMNOPQRSTUVWXYZ') {
        if (v % 11 === 0) v++;
        valores[letra] = v++;
    }
    let suma = 0;
    [...base.toUpperCase()].forEach((c, i) => {
        const n = /\d/.test(c) ? Number(c) : (valores[c] ?? 0);
        suma += n * 2 ** i;
    });
    return (suma % 11) % 10;
}

/** null = incompleto; true/false = el dígito final coincide o no. */
export function contenedorValido(valor: string): boolean | null {
    const v = valor.replace(/\s+/g, '').toUpperCase();
    if (!/^[A-Z]{4}\d{7}$/.test(v)) return null;
    return digitoContenedor(v.slice(0, 10)) === Number(v.slice(10));
}
