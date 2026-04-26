<?php
require_once __DIR__ . '/config.php';

$order   = $_GET['order']   ?? null;
$shop_id = $_GET['shop_id'] ?? null;

if (!$order || !$shop_id) {
    die('<div style="text-align:center;padding:40px;color:#ef4444;font-size:18px;">Notogri havola!</div>');
}

$order_esc   = mysqli_real_escape_string($connect, $order);
$shop_id_esc = mysqli_real_escape_string($connect, $shop_id);

$checkout = mysqli_fetch_assoc(mysqli_query($connect,
    "SELECT * FROM checkout WHERE `order`='$order_esc' AND shop_id='$shop_id_esc'"
));
if (!$checkout) {
    die('<div style="text-align:center;padding:40px;color:#ef4444;font-size:18px;">Tolov topilmadi!</div>');
}

$shop = mysqli_fetch_assoc(mysqli_query($connect,
    "SELECT * FROM shops WHERE shop_id='$shop_id_esc'"
));

$amount      = (int)$checkout['amount'];
$status      = $checkout['status'];
$card_raw    = preg_replace('/\s+/', '', $shop['card_number'] ?? '5614683582279246');
$card_bank   = $shop['card_bank']  ?? 'UzCard';
$card_owner  = !empty($shop['card_owner']) ? strtoupper($shop['card_owner']) : 'KARTA EGASI';
$shop_name   = base64_decode($shop['shop_name'] ?? '');
$card_fmt    = implode(' ', str_split($card_raw, 4));

$bank_lower  = strtolower($card_bank);
$is_humo     = strpos($bank_lower,'humo')!==false;
$is_uzcard   = strpos($bank_lower,'uzcard')!==false;
$is_visa     = strpos($bank_lower,'visa')!==false;
$is_mc       = strpos($bank_lower,'master')!==false;

$created_at  = strtotime($checkout['date']);
$over_min    = (int)($checkout['over'] ?? 5);
$expires_at  = $created_at + ($over_min * 60);
$now         = time();
$remaining   = max(0, $expires_at - $now);
$total_secs  = $over_min * 60;
?>
<!DOCTYPE html>
<html lang="uz" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>Tolov | <?php echo htmlspecialchars($shop_name ?: 'Tolovchi.uz'); ?></title>
<style>
:root{
  --bg:#f0f4f8;--surface:#fff;--surface2:#f8fafc;--border:#e2e8f0;
  --text:#0f172a;--text2:#64748b;--text3:#94a3b8;
  --accent:#2563eb;--green:#16a34a;--green-light:#dcfce7;
  --warn-bg:#fff7ed;--warn-border:#fed7aa;--warn-text:#c2410c;
  --card1:#1e293b;--card2:#0f172a;--card3:#1e3a5f;
  --shadow:0 1px 3px rgba(0,0,0,.06),0 4px 16px rgba(0,0,0,.06);
  --r:16px;--rs:10px;
}
[data-theme="dark"]{
  --bg:#0f172a;--surface:#1e293b;--surface2:#162032;--border:#334155;
  --text:#f1f5f9;--text2:#94a3b8;--text3:#64748b;
  --warn-bg:#1c1007;--warn-border:#7c3a00;--warn-text:#fb923c;
  --green-light:rgba(22,163,74,.15);
  --shadow:0 1px 3px rgba(0,0,0,.3),0 4px 16px rgba(0,0,0,.3);
}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:-apple-system,BlinkMacSystemFont,'SF Pro Display','Segoe UI',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;transition:background .3s,color .3s;}
.header{display:flex;align-items:center;justify-content:space-between;padding:13px 18px;background:var(--surface);border-bottom:1px solid var(--border);position:sticky;top:0;z-index:100;}
.logo{display:flex;align-items:center;gap:9px;font-size:17px;font-weight:700;color:var(--text);text-decoration:none;letter-spacing:-.3px;}
.logo-icon{width:33px;height:33px;background:linear-gradient(135deg,#2563eb,#06b6d4);border-radius:8px;display:flex;align-items:center;justify-content:center;color:white;font-size:15px;}
.logo em{color:var(--text3);font-style:normal;}
.hdr-r{display:flex;align-items:center;gap:9px;}
.secure{display:flex;align-items:center;gap:5px;padding:5px 11px;background:var(--green-light);color:var(--green);border-radius:99px;font-size:12px;font-weight:600;}
[data-theme="dark"] .secure{color:#4ade80;}
.theme-btn{width:35px;height:35px;border:1px solid var(--border);background:var(--surface2);border-radius:8px;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:17px;transition:transform .2s;}
.theme-btn:hover{transform:scale(1.1);}
.page{max-width:430px;margin:0 auto;padding:18px 15px 40px;}
.sec{background:var(--surface);border-radius:var(--r);margin-bottom:11px;overflow:hidden;box-shadow:var(--shadow);border:1px solid var(--border);}
.sec-lbl{font-size:10.5px;font-weight:700;letter-spacing:1.5px;color:var(--text3);text-transform:uppercase;padding:15px 17px 0;}
.amt-row{display:flex;align-items:center;gap:10px;padding:9px 17px 15px;}
.amt-big{font-size:40px;font-weight:800;color:var(--text);letter-spacing:-1px;line-height:1;}
.amt-cur{font-size:17px;font-weight:600;color:var(--text2);margin-top:5px;}
.copy-amt{margin-left:auto;width:37px;height:37px;border-radius:8px;background:var(--surface2);border:1px solid var(--border);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:16px;transition:background .2s;flex-shrink:0;}
.copy-amt:hover{background:var(--border);}
.copy-amt.done{background:var(--green-light);}
.warn{margin:0 13px 13px;background:var(--warn-bg);border:1px solid var(--warn-border);border-radius:var(--rs);padding:10px 13px;display:flex;gap:8px;align-items:flex-start;}
.warn-ic{font-size:14px;margin-top:1px;flex-shrink:0;}
.warn p{font-size:12px;color:var(--warn-text);font-weight:500;line-height:1.45;}
.warn strong{font-weight:700;}
.card-lbl{font-size:10.5px;font-weight:700;letter-spacing:1.5px;color:var(--text3);text-transform:uppercase;padding:13px 17px 11px;}
.bank-card{margin:0 13px;background:linear-gradient(135deg,var(--card1) 0%,var(--card2) 50%,var(--card3) 100%);border-radius:13px;padding:17px 17px 15px;position:relative;overflow:hidden;}
.bank-card::before{content:'';position:absolute;top:-40px;right:-40px;width:140px;height:140px;border-radius:50%;background:rgba(255,255,255,.04);}
.card-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;}
.chip{width:35px;height:26px;background:linear-gradient(135deg,#e0c97f,#c8a84b);border-radius:5px;z-index:1;position:relative;}
.blogo{height:22px;display:flex;align-items:center;z-index:1;position:relative;}
.logo-uz{background:white;border-radius:4px;padding:2px 6px;display:flex;align-items:center;gap:3px;}
.logo-uz .uz-u{font-size:14px;font-weight:900;color:#1a3a8f;}
.logo-uz .uz-t{font-size:7.5px;font-weight:700;color:#1a3a8f;letter-spacing:.4px;}
.logo-humo{background:linear-gradient(90deg,#e8292a,#2d9d47);border-radius:4px;padding:3px 7px;font-size:10px;font-weight:900;color:white;letter-spacing:.4px;}
.logo-visa{font-size:19px;font-weight:900;color:#fff;font-style:italic;letter-spacing:-1px;font-family:Georgia,serif;}
.logo-mc{display:flex;align-items:center;}
.mc-c{width:21px;height:21px;border-radius:50%;}
.mc-r{background:#eb001b;}
.mc-o{background:#f79e1b;margin-left:-7px;opacity:.9;}
.card-num{font-size:18px;font-weight:600;color:white;letter-spacing:3px;margin-bottom:15px;font-family:monospace;z-index:1;position:relative;}
.card-bot{display:flex;justify-content:space-between;align-items:flex-end;z-index:1;position:relative;}
.cf label{display:block;font-size:8px;color:rgba(255,255,255,.5);letter-spacing:1.2px;text-transform:uppercase;margin-bottom:2px;}
.cf span{font-size:11.5px;font-weight:700;color:white;letter-spacing:.3px;}
.card-wrap{padding:11px 13px 13px;}
.copy-card{width:100%;padding:14px;border:none;border-radius:11px;background:linear-gradient(135deg,#2563eb,#0ea5e9);color:white;font-size:14.5px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:7px;transition:opacity .2s,transform .1s;}
.copy-card:active{transform:scale(.98);opacity:.9;}
.copy-card.done{background:linear-gradient(135deg,#16a34a,#22c55e);}
.timer-sec{padding:15px 17px;}
.timer-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:9px;}
.t-lbl{font-size:10.5px;font-weight:700;letter-spacing:1.5px;color:var(--text3);text-transform:uppercase;}
.pill{display:flex;align-items:center;gap:5px;padding:4px 10px;border-radius:99px;font-size:12px;font-weight:600;}
.pill.ok{background:rgba(22,163,74,.12);color:var(--green);}
[data-theme="dark"] .pill.ok{color:#4ade80;}
.pill.exp{background:rgba(239,68,68,.1);color:#ef4444;}
.dot{width:7px;height:7px;border-radius:50%;background:currentColor;animation:pulse 1.4s ease-in-out infinite;}
@keyframes pulse{0%,100%{opacity:1;}50%{opacity:.3;}}
.t-num{font-size:42px;font-weight:800;font-family:monospace;letter-spacing:2px;margin-bottom:11px;}
.t-bar-bg{background:var(--border);border-radius:99px;height:5px;overflow:hidden;margin-bottom:9px;}
.t-bar{height:100%;border-radius:99px;background:linear-gradient(90deg,#2563eb,#06b6d4);transition:width 1s linear;}
.t-bar.warn-bar{background:linear-gradient(90deg,#f97316,#ef4444);}
.chk{font-size:12px;color:var(--text3);display:flex;align-items:center;gap:6px;}
.spin{width:12px;height:12px;border:2px solid var(--border);border-top-color:var(--accent);border-radius:50%;animation:spin .7s linear infinite;flex-shrink:0;}
@keyframes spin{to{transform:rotate(360deg);}}
.result-box{text-align:center;padding:38px 22px;}
.r-icon{font-size:60px;margin-bottom:14px;display:block;}
.r-title{font-size:21px;font-weight:800;margin-bottom:7px;}
.r-title.g{color:var(--green);}
.r-title.r{color:#ef4444;}
.r-sub{font-size:13.5px;color:var(--text2);line-height:1.5;}
.back-btn{display:inline-block;margin-top:18px;padding:10px 24px;background:var(--accent);color:white;border:none;border-radius:9px;font-size:13.5px;font-weight:600;cursor:pointer;}
</style>
</head>
<body>

<div class="header">
  <a href="#" class="logo">
    <div class="logo-icon">&#x1F4B3;</div>
    tolovchi<em>.uz</em>
  </a>
  <div class="hdr-r">
    <div class="secure">&#x1F512; Secure Payment</div>
    <button class="theme-btn" onclick="toggleTheme()" id="themeBtn">&#x1F319;</button>
  </div>
</div>

<div class="page">

<?php if($status === 'paid'): ?>
  <div class="sec"><div class="result-box">
    <span class="r-icon">&#x2705;</span>
    <div class="r-title g">To'lov qabul qilindi!</div>
    <div class="r-sub">Miqdor: <strong><?php echo number_format($amount,0,'.',' '); ?> UZS</strong><br>Rahmat!</div>
  </div></div>

<?php elseif($status === 'canceled' || $remaining <= 0): ?>
  <div class="sec"><div class="result-box">
    <span class="r-icon">&#x23F0;</span>
    <div class="r-title r">Vaqt tugadi!</div>
    <div class="r-sub">To'lov muddati o'tib ketdi yoki bekor qilindi.</div>
    <button class="back-btn" onclick="history.back()">&#x2B05;&#xFE0F; Ortga qaytish</button>
  </div></div>

<?php else: ?>

  <div class="sec">
    <div class="sec-lbl">TO'LOV SUMMASI</div>
    <div class="amt-row">
      <div>
        <div class="amt-big"><?php echo number_format($amount,0,'.',' '); ?></div>
        <div class="amt-cur">UZS</div>
      </div>
      <button class="copy-amt" onclick="copyAmt(this)" title="Nusxalash">&#x1F4CB;</button>
    </div>
    <div class="warn">
      <span class="warn-ic">&#x26A0;&#xFE0F;</span>
      <p>Summani aynan <strong><?php echo number_format($amount,0,'.',' '); ?> UZS</strong> miqdorda o'tkazing &mdash; boshqa summa kelsa to'lov tasdiqlanmaydi!</p>
    </div>
  </div>

  <div class="sec">
    <div class="card-lbl">TO'LOV KARTASI</div>
    <div class="bank-card">
      <div class="card-top">
        <div class="chip"></div>
        <div class="blogo">
          <?php if($is_uzcard): ?>
          <div class="logo-uz"><span class="uz-u">U</span><span class="uz-t">UZCARD</span></div>
          <?php elseif($is_humo): ?>
          <div class="logo-humo">HUMO</div>
          <?php elseif($is_visa): ?>
          <div class="logo-visa">VISA</div>
          <?php elseif($is_mc): ?>
          <div class="logo-mc"><div class="mc-c mc-r"></div><div class="mc-c mc-o"></div></div>
          <?php else: ?>
          <span style="color:white;font-size:11px;font-weight:700;"><?php echo htmlspecialchars($card_bank); ?></span>
          <?php endif; ?>
        </div>
      </div>
      <div class="card-num"><?php echo htmlspecialchars($card_fmt); ?></div>
      <div class="card-bot">
        <div class="cf">
          <label>Karta egasi</label>
          <span><?php echo htmlspecialchars($card_owner); ?></span>
        </div>
        <div class="cf" style="text-align:right;">
          <label>Bank</label>
          <span><?php echo htmlspecialchars($card_bank); ?></span>
        </div>
      </div>
    </div>
    <div class="card-wrap">
      <button class="copy-card" onclick="copyCard(this)" id="copyCardBtn">
        <span>&#x1F4CB;</span> Karta raqamni nusxalash
      </button>
    </div>
  </div>

  <div class="sec">
    <div class="timer-sec">
      <div class="timer-top">
        <div class="t-lbl">QOLGAN VAQT</div>
        <div class="pill ok" id="pill"><span class="dot"></span> Kutilmoqda</div>
      </div>
      <div class="t-num" id="timer">00:00</div>
      <div class="t-bar-bg"><div class="t-bar" id="tbar" style="width:100%"></div></div>
      <div class="chk" id="chkStatus"><span class="spin"></span> To'lov holati tekshirilmoqda...</div>
    </div>
  </div>

<?php endif; ?>
</div>

<script>
const cardRaw = <?php echo json_encode($card_raw); ?>;
const amtV    = <?php echo json_encode((string)$amount); ?>;
const order   = <?php echo json_encode($order); ?>;
const shopId  = <?php echo json_encode($shop_id); ?>;
let rem       = <?php echo (int)$remaining; ?>;
const tot     = <?php echo (int)$total_secs; ?>;

function toggleTheme(){
  const h=document.documentElement,d=h.dataset.theme==='dark';
  h.dataset.theme=d?'light':'dark';
  document.getElementById('themeBtn').textContent=d?'\uD83C\uDF19':'\u2600\uFE0F';
  document.cookie='theme='+h.dataset.theme+';path=/;max-age=31536000';
}
function pad(n){return String(n).padStart(2,'0');}
function flashCard(btn){
  btn.classList.add('done');
  btn.innerHTML='&#x2705; Nusxalandi!';
  setTimeout(()=>{btn.classList.remove('done');btn.innerHTML='<span>&#x1F4CB;</span> Karta raqamni nusxalash';},2200);
}
function copyCard(btn){
  if(navigator.clipboard){navigator.clipboard.writeText(cardRaw).then(()=>flashCard(btn));}
  else{const t=document.createElement('textarea');t.value=cardRaw;document.body.appendChild(t);t.select();document.execCommand('copy');document.body.removeChild(t);flashCard(btn);}
}
function copyAmt(btn){
  if(navigator.clipboard)navigator.clipboard.writeText(amtV).then(()=>{btn.textContent='\u2705';btn.classList.add('done');setTimeout(()=>{btn.textContent='\uD83D\uDCCB';btn.classList.remove('done');},2000);});
}
function tick(){
  if(rem<=0){
    document.getElementById('timer').textContent='00:00';
    document.getElementById('tbar').style.width='0%';
    const p=document.getElementById('pill');
    p.className='pill exp';p.innerHTML='<span class="dot"></span> Vaqt tugadi';
    document.getElementById('chkStatus').style.display='none';
    setTimeout(()=>location.reload(),2500);return;
  }
  document.getElementById('timer').textContent=pad(Math.floor(rem/60))+':'+pad(rem%60);
  const pct=(rem/tot)*100;
  document.getElementById('tbar').style.width=pct+'%';
  if(pct<25)document.getElementById('tbar').classList.add('warn-bar');
  rem--;
}
function checkPay(){
  fetch('/api?method=check&order='+order).then(r=>r.json()).then(d=>{
    if(d.data&&d.data.status==='paid'){
      document.querySelector('.page').innerHTML='<div class="sec"><div class="result-box"><span class="r-icon">\u2705</span><div class="r-title g">To\'lov qabul qilindi!</div><div class="r-sub">Rahmat!</div></div></div>';
    }else if(d.data&&d.data.status==='canceled')location.reload();
  }).catch(()=>{});
}
if(rem>0){tick();const iv=setInterval(()=>{tick();if(rem<0)clearInterval(iv);},1000);setInterval(checkPay,5000);checkPay();}
</script>
</body>
</html>
