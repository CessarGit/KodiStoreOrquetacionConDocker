import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  // Genera .next/standalone: servidor mínimo para la imagen Docker de producción
  output: "standalone",
  poweredByHeader: false, // no anunciar "X-Powered-By: Next.js"
};

export default nextConfig;
