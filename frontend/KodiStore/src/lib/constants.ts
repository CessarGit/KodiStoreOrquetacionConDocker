// En el servidor (Server Components / Server Actions) dentro de Docker se usa
// la red interna (API_INTERNAL_URL=http://backend/api). En el navegador se usa
// NEXT_PUBLIC_API_BASE_URL, que se incrusta en el bundle durante el build.
const isServer = typeof window === 'undefined';

export const API_BASE_URL =
  (isServer && process.env.API_INTERNAL_URL) ||
  process.env.NEXT_PUBLIC_API_BASE_URL ||
  'http://127.0.0.1:8000/api';
