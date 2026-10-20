import { Container } from "@cloudflare/containers";

interface Env {
  KONTENER: DurableObjectNamespace<MsygnalistaKontener>;
  KOD_DOSTEPU: string;
}

// Kontener z Apache + PHP + MariaDB (deploy/Dockerfile).
// Po 30 min bez ruchu usypia - baza wraca wtedy do danych z database.sql.
export class MsygnalistaKontener extends Container<Env> {
  defaultPort = 80;
  sleepAfter = "30m";

  override onStop(params: unknown) {
    console.log("kontener zatrzymany", JSON.stringify(params));
  }

  override onError(error: unknown) {
    console.log("blad kontenera", String(error));
    throw error;
  }
}

const CIASTKO = "msyg_dostep";

async function sha256(tekst: string): Promise<string> {
  const hash = await crypto.subtle.digest("SHA-256", new TextEncoder().encode(tekst));
  return [...new Uint8Array(hash)].map((b) => b.toString(16).padStart(2, "0")).join("");
}

function ciasteczko(request: Request, nazwa: string): string | null {
  const naglowek = request.headers.get("Cookie") ?? "";
  for (const czesc of naglowek.split(";")) {
    const [k, ...v] = czesc.trim().split("=");
    if (k === nazwa) return v.join("=");
  }
  return null;
}

function stronaKodu(blad: boolean, status: number): Response {
  const html = `<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title>Dostęp - mSygnalista</title>
<style>
  body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#F3F4F6;font-family:system-ui,-apple-system,sans-serif;color:#1F2937;padding:16px;box-sizing:border-box}
  form{background:#fff;border:1px solid #e5e7eb;border-radius:24px;padding:32px;max-width:360px;width:100%;box-shadow:0 1px 2px rgba(0,0,0,.05)}
  .logo{width:44px;height:44px;border-radius:12px;background:#D32F2F;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;margin-bottom:16px}
  h1{font-size:22px;margin:0 0 6px} p{color:#6B7280;font-size:14px;margin:0 0 20px}
  label{display:block;font-size:14px;font-weight:600;margin-bottom:8px}
  input{width:100%;box-sizing:border-box;border:1px solid #d1d5db;border-radius:12px;padding:12px 14px;font-size:16px}
  input:focus{outline:none;border-color:#D32F2F;box-shadow:0 0 0 2px rgba(211,47,47,.35)}
  button{margin-top:16px;width:100%;border:0;border-radius:12px;padding:12px;background:#D32F2F;color:#fff;font-weight:600;font-size:15px;cursor:pointer}
  button:hover{background:#B71C1C}
  .blad{background:#fef2f2;border:1px solid #fecaca;color:#991b1b;border-radius:12px;padding:10px 12px;font-size:14px;margin-bottom:16px}
</style>
</head>
<body>
<form method="post" action="/__dostep">
  <div class="logo">mS</div>
  <h1>mSygnalista</h1>
  <p>Projekt szkolny. Wpisz kod dostępu, aby przejść dalej.</p>
  ${blad ? '<div class="blad" role="alert">Nieprawidłowy kod dostępu.</div>' : ""}
  <label for="kod">Kod dostępu</label>
  <input id="kod" name="kod" type="password" required autofocus autocomplete="off">
  <button type="submit">Wejdź</button>
</form>
</body>
</html>`;
  return new Response(html, {
    status,
    headers: { "Content-Type": "text/html; charset=utf-8", "Cache-Control": "no-store" },
  });
}

export default {
  async fetch(request: Request, env: Env): Promise<Response> {
    const url = new URL(request.url);
    const poprawneCiastko = await sha256("msygnalista:" + env.KOD_DOSTEPU);

    // sprawdzenie, czy aplikacja w kontenerze odpowiada (bez treści strony)
    if (url.pathname === "/__status") {
      try {
        const odp = await env.KONTENER.getByName("glowny").fetch(new Request(url.origin + "/index.php"));
        return new Response(odp.status === 200 ? "ok" : "blad " + odp.status, {
          status: odp.status === 200 ? 200 : 503,
          headers: { "Cache-Control": "no-store" },
        });
      } catch {
        return new Response("kontener nie odpowiada", { status: 503, headers: { "Cache-Control": "no-store" } });
      }
    }

    if (url.pathname === "/__dostep" && request.method === "POST") {
      const dane = await request.formData();
      const kod = String(dane.get("kod") ?? "").trim();

      if (kod !== env.KOD_DOSTEPU) {
        return stronaKodu(true, 401);
      }
      return new Response(null, {
        status: 303,
        headers: {
          Location: "/",
          "Set-Cookie": `${CIASTKO}=${poprawneCiastko}; Path=/; Max-Age=2592000; HttpOnly; Secure; SameSite=Lax`,
        },
      });
    }

    if (ciasteczko(request, CIASTKO) !== poprawneCiastko) {
      return stronaKodu(false, 401);
    }

    // dalej do kontenera - jedna instancja, żeby wszyscy widzieli tę samą bazę
    const naglowki = new Headers(request.headers);
    naglowki.set("X-Forwarded-Proto", "https");
    const doKontenera = new Request(request, { headers: naglowki });

    return env.KONTENER.getByName("glowny").fetch(doKontenera);
  },
};
