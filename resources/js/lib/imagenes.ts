/**
 * Reduce una foto en el navegador antes de subirla (lado mayor de 1600 px, JPEG).
 * Así pesa poco y no choca con el límite de subida de PHP. PDF, HEIC y otros formatos pasan tal cual.
 */
export async function reducirImagen(f: File, lado = 1600): Promise<File> {
    if (!/^image\/(jpeg|png|webp)$/.test(f.type)) return f;
    try {
        const img = await createImageBitmap(f);
        const escala = Math.min(1, lado / Math.max(img.width, img.height));
        if (escala === 1 && f.size < 1_500_000) return f;
        const lienzo = document.createElement('canvas');
        lienzo.width = Math.round(img.width * escala);
        lienzo.height = Math.round(img.height * escala);
        lienzo.getContext('2d')!.drawImage(img, 0, 0, lienzo.width, lienzo.height);
        const blob = await new Promise<Blob | null>((r) => lienzo.toBlob(r, 'image/jpeg', 0.82));
        return blob ? new File([blob], f.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' }) : f;
    } catch {
        return f;
    }
}
