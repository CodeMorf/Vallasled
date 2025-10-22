<script>
(() => {
  const ASSET = "/_assistant/avatar.png"; // puedes cambiar por tu logo
  const TOKEN_URL = "/_assistant/token.php";
  const MODEL = "gpt-4o-realtime-preview-2024-12-17";

  // UI
  const fab = document.createElement("div");
  fab.id = "va-fab";
  fab.innerHTML = `<img src="${ASSET}" alt="Asistente">`;
  document.addEventListener("DOMContentLoaded", () => document.body.appendChild(fab));

  const card = document.createElement("div");
  card.id = "va-card";
  card.innerHTML = `
    <div class="va-head"><img src="${ASSET}" alt=""><b>Asistente VallasLed</b></div>
    <div class="va-body">Micrófono para hablar en tiempo real. Usa auriculares para evitar eco.</div>
    <div class="va-status" id="va-status">Listo</div>
    <div class="va-mic">
      <div id="va-vumeter"><span></span></div>
      <button class="va-btn primary" id="va-toggle">Conectar</button>
      <button class="va-btn ghost" id="va-mute" disabled>Mute</button>
    </div>`;
  document.addEventListener("DOMContentLoaded", () => document.body.appendChild(card));

  let pc, dc, localStream, remoteAudio, analyser, raf, muted = false;

  const qs = (sel) => card.querySelector(sel);
  const status = (t) => { qs("#va-status").textContent = t; };

  function setVu(value) {
    const v = Math.max(0, Math.min(1, value));
    qs("#va-vumeter > span").style.width = (v*100).toFixed(0) + "%";
  }

  async function getToken() {
    const r = await fetch(TOKEN_URL, {method:"POST", headers:{'Content-Type':'application/json'}});
    const j = await r.json();
    if (!j || !j.client_secret || !j.client_secret.value) throw new Error("Token inválido");
    return j.client_secret.value;
  }

  async function connect() {
    if (pc) return;
    status("Solicitando micrófono");
    localStream = await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation:true, noiseSuppression:true, autoGainControl:true }});

    // VU meter
    const ctx = new (window.AudioContext || window.webkitAudioContext)();
    const src = ctx.createMediaStreamSource(localStream);
    analyser = ctx.createAnalyser(); analyser.fftSize = 2048;
    src.connect(analyser);
    function loop(){
      const data = new Uint8Array(analyser.fftSize);
      analyser.getByteTimeDomainData(data);
      // RMS simple
      let sum=0; for (let i=0;i<data.length;i++){ const v=(data[i]-128)/128; sum+=v*v; }
      setVu(Math.sqrt(sum/data.length));
      raf = requestAnimationFrame(loop);
    }
    loop();

    // PeerConnection
    pc = new RTCPeerConnection();
    localStream.getTracks().forEach(t => pc.addTrack(t, localStream));
    pc.addTransceiver("audio", { direction: "sendrecv" });

    // Audio remoto
    remoteAudio = document.createElement("audio");
    remoteAudio.autoplay = true;
    pc.ontrack = (e) => { remoteAudio.srcObject = e.streams[0]; };

    // Canal de datos opcional
    dc = pc.createDataChannel("oai-events");
    dc.onmessage = (ev) => { /* eventos del modelo si los envía */ };

    status("Creando oferta");
    const offer = await pc.createOffer();
    await pc.setLocalDescription(offer);

    // Intercambio SDP con OpenAI
    status("Obteniendo sesión efímera");
    const ephemeral = await getToken();
    status("Conectando con OpenAI");

    const base = "https://api.openai.com/v1/realtime?model="+encodeURIComponent(MODEL);
    const sdpRes = await fetch(base, {
      method: "POST",
      headers: {
        "Authorization": "Bearer " + ephemeral,
        "Content-Type": "application/sdp"
      },
      body: offer.sdp
    });
    const answer = await sdpRes.text();
    await pc.setRemoteDescription({ type: "answer", sdp: answer });

    qs("#va-toggle").textContent = "Desconectar";
    qs("#va-mute").disabled = false;
    status("Conectado. Habla cuando quieras");
  }

  async function disconnect() {
    if (raf) cancelAnimationFrame(raf);
    setVu(0);
    if (dc) { try{dc.close();}catch{} dc=null; }
    if (pc) { try{pc.close();}catch{} pc=null; }
    if (localStream) { localStream.getTracks().forEach(t=>t.stop()); localStream=null; }
    qs("#va-toggle").textContent = "Conectar";
    qs("#va-mute").disabled = true;
    status("Desconectado");
  }

  function toggleMute() {
    muted = !muted;
    if (localStream) localStream.getAudioTracks().forEach(t => t.enabled = !muted);
    qs("#va-mute").textContent = muted ? "Unmute" : "Mute";
    status(muted ? "Micrófono en mute" : "Micrófono activo");
  }

  // UI events
  fab.addEventListener("click", () => { card.classList.toggle("active"); });
  document.addEventListener("click", (e) => {
    if (!card.contains(e.target) && e.target !== fab) card.classList.remove("active");
  });

  card.addEventListener("click", async (e) => {
    if (e.target.id === "va-toggle") {
      if (pc) await disconnect(); else await connect();
    } else if (e.target.id === "va-mute") {
      toggleMute();
    }
  });

})();
</script>
