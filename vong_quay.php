<?php /* party_wheel.php - Vòng quay bàn nhậu (radial text) */ ?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>🎉 Vòng quay bàn nhậu</title>
<style>
  :root{
    --bg:#f6f9fc; --card:#fff; --ink:#0f172a; --muted:#64748b;
    --brand:#2563eb; --brand-dark:#1e40af; --border:#e2e8f0;
    --ring:rgba(37,99,235,.18); --danger:#ef4444;
  }
  *{box-sizing:border-box}
  body{margin:0;padding:20px;font-family:ui-sans-serif,system-ui,-apple-system,Segoe UI,Roboto,Arial;background:var(--bg);color:var(--ink)}
  .container{max-width:1000px;margin:auto}
  header{display:flex;gap:12px;align-items:center;justify-content:center;margin-bottom:14px}
  .logo{width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,#60a5fa,#22c55e);display:grid;place-items:center;font-weight:900;color:#0b1220;box-shadow:0 10px 20px rgba(37,99,235,.25)}
  h1{margin:0;font-size:22px}
  .grid{display:grid;grid-template-columns:1fr 360px;gap:16px}
  @media (max-width:960px){.grid{grid-template-columns:1fr}}
  .card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:16px;box-shadow:0 8px 24px rgba(2,6,23,.05)}
  .row{display:flex;flex-wrap:wrap;gap:10px;align-items:center}
  button{border:0;border-radius:12px;padding:10px 16px;font-weight:800;cursor:pointer;background:var(--brand);color:#fff;box-shadow:0 8px 18px rgba(37,99,235,.24)}
  button:hover{background:var(--brand-dark)}
  .btn-ghost{background:#fff;color:#0f172a;border:1px solid var(--border);box-shadow:none}
  .btn-danger{background:var(--danger);color:#fff}
  .muted{color:var(--muted);font-size:13px}
  .wheel-wrap{display:grid;place-items:center;padding:8px}
  .pointer{width:0;height:0;border-left:18px solid transparent;border-right:18px solid transparent;border-bottom:28px solid #ef4444;margin-bottom:8px;filter:drop-shadow(0 3px 6px rgba(0,0,0,.2))}
  canvas{max-width:100%;height:auto;border-radius:50%;border:1px solid var(--border);background:#fff}
  .hide{display:none !important}
  .badge{display:inline-block;padding:4px 10px;border-radius:999px;background:#eef2ff;border:1px solid #c7d2fe;color:#3730a3;font-weight:800;font-size:12px}
  .result-title{font-size:22px;font-weight:900;margin:6px 0}
  .explain{font-size:14px;background:#f8fafc;border:1px solid var(--border);padding:12px;border-radius:12px}
  details{border:1px solid var(--border);border-radius:12px;padding:10px;background:#fff}
  summary{cursor:pointer;font-weight:700}
  textarea{width:100%;min-height:170px;padding:10px;border-radius:12px;border:1px solid var(--border);resize:vertical}
  textarea:focus{outline:none;border-color:#bfdbfe;box-shadow:0 0 0 4px var(--ring)}
  .footer{margin-top:16px;text-align:center;color:var(--muted);font-size:12px}
</style>
</head>
<body>
<div class="container">
  <header>
    <div class="logo">W</div>
    <h1>Vòng quay bàn nhậu 🍻</h1>
  </header>

  <div class="grid">
    <!-- Vòng quay + Kết quả -->
    <div class="card">
      <div class="wheel-wrap" id="wheelZone">
        <div class="pointer"></div>
        <canvas id="wheel" width="520" height="520"></canvas>
      </div>

      <div id="resultZone" class="card hide" style="margin-top:10px">
        <span class="badge">KẾT QUẢ</span>
        <div id="resTitle" class="result-title">—</div>
        <div id="resExplain" class="explain">—</div>
        <div class="row" style="justify-content:center;margin-top:6px">
          <button id="btnSpinAgain">🔁 QUAY TIẾP</button>
          <button id="btnShowWheel" class="btn-ghost">👀 Hiển thị vòng quay</button>
        </div>
      </div>

      <div class="row" id="controls" style="justify-content:center;margin-top:10px">
        <button id="btnSpin" style="min-width:140px">🎯 QUAY</button>
        <button id="btnConfetti" class="btn-ghost">🎊 Thử pháo giấy</button>
        <span class="muted">* 18+: Uống có trách nhiệm, đừng lái xe.</span>
      </div>
    </div>

    <!-- Tùy biến -->
    <div class="card">
      <details>
        <summary>⚙️ Tùy biến tình huống</summary>
        <div class="muted" style="margin:6px 0 8px">
          Mỗi dòng một tình huống, dùng dấu “|” cho phần giải thích.<br>
          Ví dụ: <i>Uống thêm 1 ly | Người trúng tự thưởng thêm 1 ly.</i>
        </div>
        <textarea id="customData"></textarea>
        <div class="row" style="justify-content:center;margin-top:8px">
          <button id="btnLoadCustom" class="btn-ghost">📥 Nạp tình huống</button>
          <button id="btnReset" class="btn-danger">♻️ Khôi phục mặc định</button>
        </div>
      </details>
      <div class="muted" style="margin-top:12px">Mẹo: đọc to kết quả kèm giải thích để “không ai cãi luật”.</div>
    </div>
  </div>

  <div class="footer">Vui là chính – An toàn là trên hết. Chỉ dành cho người đủ 18+.</div>
</div>

<script>
/* ================== DỮ LIỆU MẶC ĐỊNH ================== */
const DEFAULT_ITEMS = [
  {t:"Bỏ qua một lượt",d:"Bạn may mắn! Lần này nghỉ uống, chuyển lượt cho người kế tiếp. 🍀"},
  {t:"Uống thêm 1 ly",d:"Tự thưởng cho mình 1 ly cho ấm người. 🥃"},
  {t:"Uống thêm 2 ly",d:"Hơi căng! Làm liền 2 ly (có thể chia làm 2 ngụm)."},
  {t:"Chỉ định người uống thay",d:"Bạn được chọn 1 người bất kỳ uống thay bạn lần này. 🤝"},
  {t:"Chỉ định người uống cùng",d:"Bạn kéo 1 người uống chung niềm vui! Cả hai cùng uống. 👯"},
  {t:"Người bên trái uống",d:"Người ngồi bên tay trái của bạn uống 1 ly. 👈"},
  {t:"Người bên phải uống",d:"Người ngồi bên tay phải của bạn uống 1 ly. 👉"},
  {t:"Tất cả cùng dzô!",d:"Cả bàn cùng cụng ly, mỗi người 1 ngụm. DZÔ!! 🥳"},
  {t:"Truth or Drink",d:"Trả lời 1 câu hỏi thật lòng. Từ chối? Uống 1 ly. 🧐"},
  {t:"Kể chuyện xấu hổ",d:"Kể 1 chuyện xấu hổ của bản thân. Không kể thì uống 1 ly. 🙈"},
  {t:"Oẳn tù tì với trái",d:"Chơi kéo-búa-bao với người bên trái. Thua uống 1 ly. ✊✌️✋"},
  {t:"Oẳn tù tì với phải",d:"Chơi kéo-búa-bao với người bên phải. Thua uống 1 ly."},
  {t:"Đoán số 1–5",d:"Bạn nghĩ một số 1–5. Ai đoán trúng thì bạn uống; sai thì họ uống. 🔢"},
  {t:"Tự đặt luật",d:"Bạn đặt ra 1 luật cho ván sau. Ai phạm luật thì uống."},
  {t:"Rót đầy ai đó",d:"Bạn được quyền rót đầy ly cho một người bạn chọn."},
  {t:"Sinh nhật gần nhất",d:"Ai sắp tới sinh nhật nhất uống mừng trước! 🎂"},
  {t:"Chụp ảnh kỷ niệm",d:"Cả bàn chụp ảnh. Ai nháy muộn uống 1 ngụm. 📸"},
];

/* ================== STATE & CANVAS ================== */
let items = DEFAULT_ITEMS.slice();
let angle = 0;            // góc hiện tại (rad)
let spinning = false;

const canvas = document.getElementById('wheel');
const ctx = canvas.getContext('2d');
const radius = canvas.width / 2;


// Vẽ nhãn theo TIẾP TUYẾN, đảm bảo chân chữ (baseline) hướng vào tâm
function drawLabelTangential(text, startAngle, endAngle){
  const mid   = (startAngle + endAngle) / 2;
  const rText = radius * 0.72;                         // vị trí đặt chữ
  const arcLen = (endAngle - startAngle) * rText * 0.90; // bề rộng hữu dụng theo cung

  // Auto-fit cỡ chữ
  let fs = 18, min = 10;
  ctx.font = `700 ${fs}px ui-sans-serif, Arial`;
  while (fs > min && ctx.measureText(text).width > arcLen){
    fs--; ctx.font = `700 ${fs}px ui-sans-serif, Arial`;
  }

  // Nếu vẫn dài, tách 2 dòng (tìm điểm tách “đẹp” nhất)
  function splitTwoLines(t, maxW){
    if (!t.includes(' ')) return null;
    let best = null, bestW = Infinity;
    const words = t.split(' ');
    for (let i=1;i<words.length;i++){
      const l1 = words.slice(0,i).join(' ');
      const l2 = words.slice(i).join(' ');
      const w = Math.max(ctx.measureText(l1).width, ctx.measureText(l2).width);
      if (w <= maxW) return [l1,l2];
      if (w < bestW){ bestW = w; best = [l1,l2]; }
    }
    return best;
  }
  let lines = null;
  if (ctx.measureText(text).width > arcLen) lines = splitTwoLines(text, arcLen);

  ctx.save();
  // Hệ trục hiện ở TÂM (đã translate ở drawWheel)
  ctx.rotate(mid);            // trục X trùng bán kính (ra ngoài)
  ctx.translate(rText, 0);    // đến vị trí đặt chữ trên bán kính
  ctx.rotate(Math.PI/2);      // X theo TIẾP TUYẾN; khi đó trục Y(+)=vào TÂM  ✅
  // KHÔNG flip thêm 180° nữa, để chân chữ luôn hướng vào tâm cho mọi lát

  ctx.fillStyle = '#0f172a';
  ctx.textAlign = 'center';
  ctx.textBaseline = 'middle';
  if (lines && lines.length === 2){
    const lh = fs*1.1;
    ctx.fillText(lines[0], 0, -lh/2, arcLen);
    ctx.fillText(lines[1], 0,  lh/2, arcLen);
  } else {
    ctx.fillText(text, 0, 0, arcLen);
  }
  ctx.restore();
}


/* ================== TIỆN ÍCH ================== */
function pastel(i, total){
  const hue = (i * (360/total) + 20) % 360;
  return `hsl(${hue},85%,80%)`;
}
function normalizeAngle(a){ a%=2*Math.PI; if(a<0)a+=2*Math.PI; return a; }
function easeOutCubic(x){ return 1 - Math.pow(1 - x, 3); }

/* ================== VẼ VÒNG QUAY ================== */
function drawWheel(){
  ctx.clearRect(0,0,canvas.width,canvas.height);
  ctx.save();
  ctx.translate(radius, radius);      // đưa gốc về tâm
  ctx.rotate(angle);                  // quay theo góc hiện tại

  const n = items.length;
  const arc = (2*Math.PI)/n;

  for(let i=0;i<n;i++){
    const start = i*arc;
    const end   = start + arc;

    // lát bánh
    ctx.beginPath();
    ctx.moveTo(0,0);
    ctx.arc(0,0,radius-2,start,end);
    ctx.closePath();
    ctx.fillStyle = pastel(i,n);
    ctx.fill();
    ctx.strokeStyle = '#e2e8f0';
    ctx.lineWidth = 2;
    ctx.stroke();

    // chữ theo bán kính
drawLabelTangential(items[i].t, start, end);

  }

  // nút tâm
  ctx.beginPath();
  ctx.arc(0,0,28,0,2*Math.PI);
  ctx.fillStyle = '#2563eb';
  ctx.fill();
  ctx.fillStyle = '#fff';
  ctx.font = 'bold 14px ui-sans-serif, Arial';
  ctx.textAlign='center'; ctx.textBaseline='middle';
  ctx.fillText('QUAY', 0, 0);

  ctx.restore();
}

/* ===== Vẽ chữ hướng về tâm (RADIAL, FIX cả hai nửa vòng) ===== */
function drawRadialText(label, midAngle){
  const rInner = radius * 0.40;          // gần tâm
  const rOuter = radius * 0.92;          // gần viền
  const span   = (rOuter - rInner) - 10; // chiều dài khả dụng

  // chọn cỡ chữ cho vừa chiều dài bán kính
  let size = 18, min = 11;
  ctx.font = `700 ${size}px ui-sans-serif, Arial`;
  while (size>min && ctx.measureText(label).width > span){
    size--; ctx.font = `700 ${size}px ui-sans-serif, Arial`;
  }

  ctx.save();
  // hệ toạ độ hiện đang ở tâm & đã rotate(angle) từ drawWheel
  ctx.rotate(midAngle);

  // Nửa trái (cos < 0) -> lật 180° để chữ không ngược
  const flip = Math.cos(midAngle) < 0;
  if (flip) ctx.rotate(Math.PI);

  ctx.fillStyle = '#0f172a';
  ctx.textAlign = 'left';      // vẽ từ trong ra ngoài
  ctx.textBaseline = 'middle';
  ctx.fillText(label, rInner, 0, span);
  ctx.restore();
}

/* ================== QUAY VÒNG ================== */
function spin(){
  if(spinning || items.length===0) return;
  spinning = true;
  showWheel(true);

  const n = items.length;
  const arc = (2*Math.PI)/n;
  const targetIndex = Math.floor(Math.random()*n);
  const targetMid = (targetIndex*arc) + arc/2;

  const pointerAngle = -Math.PI/2; // kim hướng lên
  const delta = normalizeAngle(pointerAngle - (angle + targetMid));
  const extraTurns = 3 + Math.floor(Math.random()*3); // 3–5 vòng
  const total = delta + extraTurns*2*Math.PI;

  const duration = 4200;
  const start = performance.now();
  const startAngle = angle;
  let tickAt = 0;

  function frame(now){
    const t = Math.min(1, (now-start)/duration);
    angle = startAngle + total*easeOutCubic(t);
    drawWheel();

    if(now - tickAt > 80){ softTick(); tickAt = now; }

    if(t<1) requestAnimationFrame(frame);
    else {
      spinning=false;
      angle = normalizeAngle(angle);
      showResult(items[targetIndex]);
      confettiBurst();
    }
  }
  requestAnimationFrame(frame);
}

/* ================== UI HIỂN/ẨN ================== */
const wheelZone = document.getElementById('wheelZone');
const resultZone = document.getElementById('resultZone');
const resTitle = document.getElementById('resTitle');
const resExplain = document.getElementById('resExplain');

function showWheel(whileSpinning=false){
  wheelZone.classList.remove('hide');
  resultZone.classList.add('hide');
  document.getElementById('btnSpin').disabled = whileSpinning;
}
function showResult(item){
  resTitle.textContent = '🎯 ' + item.t;
  resExplain.textContent = item.d || 'Hành động ngay!';
  wheelZone.classList.add('hide');
  resultZone.classList.remove('hide');
  document.getElementById('btnSpin').disabled = false;
}

/* ================== CONFETTI & TICK ================== */
let audioCtx;
function softTick(){
  try{
    if(!audioCtx) audioCtx = new (window.AudioContext||window.webkitAudioContext)();
    const o = audioCtx.createOscillator(), g = audioCtx.createGain();
    o.type='square'; o.frequency.value=900+Math.random()*200; g.gain.value=0.02;
    o.connect(g); g.connect(audioCtx.destination); o.start();
    setTimeout(()=>{o.stop(); o.disconnect(); g.disconnect();},40);
  }catch(e){}
}
function confettiBurst(){
  const N = 24;
  for(let i=0;i<N;i++){
    const p = document.createElement('div');
    p.style.position='fixed';
    p.style.left = (Math.random()*100)+'vw';
    p.style.top = '-12px';
    p.style.width='8px'; p.style.height='12px';
    p.style.borderRadius='3px';
    p.style.background = `hsl(${Math.random()*360},90%,60%)`;
    p.style.opacity='0.9';
    p.style.transform=`rotate(${Math.random()*180}deg)`;
    p.style.transition='transform 1.4s linear, top 1.4s ease-in, opacity .25s ease 1.1s';
    document.body.appendChild(p);
    requestAnimationFrame(()=>{
      p.style.top = '95vh';
      p.style.transform = `translateY(0) rotate(${360+Math.random()*360}deg)`;
      setTimeout(()=>{ p.style.opacity='0'; }, 1100);
      setTimeout(()=> p.remove(), 1500);
    });
  }
}

/* ================== TÙY BIẾN DỮ LIỆU ================== */
const customArea = document.getElementById('customData');
document.getElementById('btnLoadCustom').addEventListener('click', ()=>{
  const lines = customArea.value.split(/\r?\n/).map(s=>s.trim()).filter(Boolean);
  if(!lines.length){ alert('Chưa có dữ liệu để nạp.'); return; }
  const arr = lines.map(l=>{
    const [t,...rest] = l.split('|');
    return {t:t.trim(), d:(rest.join('|')||'').trim()};
  }).filter(x=>x.t);
  if(!arr.length){ alert('Dữ liệu không hợp lệ.'); return; }
  items = arr; angle = 0; drawWheel(); showWheel(false);
});
document.getElementById('btnReset').addEventListener('click', ()=>{
  items = DEFAULT_ITEMS.slice(); customArea.value=''; angle=0; drawWheel(); showWheel(false);
});

/* ================== NÚT BẤM ================== */
document.getElementById('btnSpin').addEventListener('click', spin);
document.getElementById('btnSpinAgain').addEventListener('click', ()=>{ showWheel(false); spin(); });
document.getElementById('btnShowWheel').addEventListener('click', ()=> showWheel(false));
document.getElementById('btnConfetti').addEventListener('click', confettiBurst);

/* ================== KHỞI TẠO ================== */
drawWheel();
showWheel(false);
</script>
</body>
</html>