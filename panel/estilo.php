<style>
  :root { --ink:#17181a; --ink2:#3a3b3f; --muted:#6f7075; --line:#e6e3dd; --bg2:#f5f3ef; --ok:#1f7a4d; --mal:#a3342a; }
  * { box-sizing: border-box; }
  body { margin:0; background:#fff; color:var(--ink); font:16px/1.6 'Inter',system-ui,-apple-system,'Segoe UI',sans-serif; }
  a { color: var(--ink); }
  .barra { position:sticky; top:0; z-index:5; display:flex; align-items:center; justify-content:space-between; gap:16px; padding:14px 24px; background:rgba(255,255,255,.92); backdrop-filter:blur(12px); border-bottom:1px solid var(--line); }
  .marca { display:flex; align-items:center; gap:10px; font-weight:600; }
  .marca img { width:32px; height:32px; border-radius:8px; }
  .barra nav { display:flex; gap:4px; flex-wrap:wrap; }
  .barra nav a { padding:8px 14px; border-radius:999px; font-size:14px; font-weight:500; text-decoration:none; color:var(--muted); }
  .barra nav a.on { background:var(--ink); color:#fff; }
  .salir { font-size:14px; color:var(--muted); text-decoration:underline; }
  main { max-width:900px; margin:0 auto; padding:32px 24px 96px; }
  h1 { font-size:30px; letter-spacing:-.03em; margin:0 0 6px; font-weight:600; }
  .guia { color:var(--muted); font-size:15px; margin:0 0 28px; }
  fieldset { border:1px solid var(--line); border-radius:16px; padding:20px 22px; margin:0 0 16px; }
  legend { font-weight:600; font-size:15px; padding:0 8px; }
  label { display:block; margin:0 0 16px; }
  label span { display:block; font-size:13px; color:var(--muted); margin-bottom:6px; }
  input[type=text], input[type=number], textarea, select { width:100%; font:inherit; font-size:15px; padding:10px 12px; border:1px solid var(--line); border-radius:10px; background:#fff; color:var(--ink); }
  textarea { min-height:96px; resize:vertical; }
  input:focus, textarea:focus, select:focus { outline:2px solid var(--ink); outline-offset:1px; }
  .btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; min-height:46px; padding:0 22px; border:1px solid var(--ink); border-radius:999px; background:var(--ink); color:#fff; font:inherit; font-size:15px; font-weight:500; cursor:pointer; text-decoration:none; }
  .btn:hover { background:#2b2c30; }
  .btn.claro { background:#fff; color:var(--ink); }
  .btn.claro:hover { background:var(--bg2); }
  .guardar { position:sticky; bottom:0; display:flex; gap:12px; align-items:center; padding:16px 0; background:linear-gradient(0deg,#fff 70%,transparent); }
  .aviso { padding:12px 16px; border-radius:12px; margin:0 0 20px; font-size:15px; }
  .aviso.ok { background:#eaf5ef; color:var(--ok); }
  .aviso.mal { background:#fbeeec; color:var(--mal); }
  .pub { display:flex; align-items:center; gap:12px; padding:12px 16px; border-radius:12px; margin:0 0 20px; font-size:15px; background:#f4f2ee; color:#3a3b3f; }
  .pub__luz { width:9px; height:9px; border-radius:50%; background:#b9b5ad; flex:none; }
  .pub.yendo { background:#fdf5e8; color:#7a5a1e; }
  .pub.yendo .pub__luz { background:#d99b28; animation:latido 1.1s ease-in-out infinite; }
  .pub.ok { background:#eaf5ef; color:var(--ok); }
  .pub.ok .pub__luz { background:var(--ok); }
  .pub.mal { background:#fbeeec; color:var(--mal); }
  .pub.mal .pub__luz { background:var(--mal); }
  .pub__txt { flex:1; }
  .pub__btn { border:0; border-radius:999px; padding:8px 16px; background:#17181a; color:#fff; font:inherit; font-size:14px; cursor:pointer; }
  .pub__btn:hover { background:#000; }
  @keyframes latido { 0%,100% { opacity:1; } 50% { opacity:.25; } }
  @media (prefers-reduced-motion: reduce) { .pub.yendo .pub__luz { animation:none; } }
  .fotos { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:16px; }
  .foto { border:1px solid var(--line); border-radius:14px; overflow:hidden; display:flex; flex-direction:column; }
  .foto img { width:100%; aspect-ratio:4/3; object-fit:cover; background:var(--bg2); }
  .foto .pie { padding:10px 12px; display:flex; flex-direction:column; gap:8px; }
  .foto code { font-size:12px; color:var(--muted); word-break:break-all; }
  .foto input[type=file] { font-size:12px; width:100%; }
  .entrada { max-width:380px; margin:14vh auto; padding:0 24px; }
  .entrada img { width:56px; height:56px; border-radius:14px; margin-bottom:20px; }
  .tabla { width:100%; border-collapse:collapse; font-size:15px; }
  .tabla td { padding:8px 0; border-bottom:1px solid var(--line); }
  @media (max-width:620px) { .barra { flex-wrap:wrap; } main { padding:24px 16px 96px; } }
</style>
