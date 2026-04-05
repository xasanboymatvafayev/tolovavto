<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>To'lov | Tolovchi.uz</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    min-height: 100vh;
    background: linear-gradient(135deg, #e8f4fd 0%, #f0f8e8 50%, #e8f4fd 100%);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 20px;
  }
  .brand {
    font-size: 14px;
    font-weight: 600;
    color: #555;
    margin-bottom: 20px;
    letter-spacing: 0.5px;
  }
  .card-box {
    background: white;
    border-radius: 24px;
    padding: 32px 24px;
    width: 100%;
    max-width: 380px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.08);
  }
  .amount-label {
    text-align: center;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 1.5px;
    color: #999;
    margin-bottom: 8px;
  }
  .amount-value {
    text-align: center;
    font-size: 48px;
    font-weight: 800;
    color: #2ecc71;
    margin-bottom: 4px;
  }
  .amount-value span {
    font-size: 22px;
    font-weight: 600;
    color: #2ecc71;
  }
  .warning-box {
    background: #fffbeb;
    border-radius: 12px;
    padding: 12px 16px;
    margin: 16px 0;
    display: flex;
    align-items: flex-start;
    gap: 10px;
  }
  .warning-box .icon { font-size: 18px; margin-top: 1px; }
  .warning-box p { font-size: 13px; color: #d97706; font-weight: 500; line-height: 1.4; }

  /* Karta */
  .bank-card {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
    border-radius: 16px;
    padding: 20px;
    margin: 16px 0;
    position: relative;
    overflow: hidden;
  }
  .bank-card::before {
    content: '';
    position: absolute;
    top: -30px; right: -30px;
    width: 120px; height: 120px;
    border-radius: 50%;
    background: rgba(255,255,255,0.05);
  }
  .card-chip {
    width: 40px; height: 30px;
    background: linear-gradient(135deg, #e0c97f, #c8a84b);
    border-radius: 6px;
    margin-bottom: 20px;
  }
  .card-number {
    font-size: 20px;
    font-weight: 600;
    color: white;
    letter-spacing: 4px;
    margin-bottom: 20px;
    font-family: monospace;
  }
  .card-info {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
  }
  .card-info-item label {
    font-size: 9px;
    color: rgba(255,255,255,0.6);
    letter-spacing: 1px;
    text-transform: uppercase;
    display: block;
    margin-bottom: 3px;
  }
  .card-info-item span {
    font-size: 13px;
    font-weight: 700;
    color: white;
    letter-spacing: 0.5px;
  }

  /* Copy button */
  .copy-btn {
    width: 100%;
    padding: 16px;
    border: none;
    border-radius: 14px;
    background: linear-gradient(135deg, #3b82f6, #2ecc71);
    color: white;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    margin-bottom: 24px;
    transition: opacity 0.2s;
  }
  .copy-btn:active { opacity: 0.85; }
  .copy-btn.copied { background: #2ecc71; }

  /* Timer */
  .timer-label {
    text-align: center;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 1.5px;
    color: #999;
    margin-bottom: 8px;
  }
  .timer-value {
    text-align: center;
    font-size: 40px;
    font-weight: 800;
    color: #111;
    margin-bottom: 12px;
    font-family: monospace;
  }
  .timer-bar-bg {
    background: #f0f0f0;
    border-radius: 99px;
    height: 6px;
    margin-bottom: 12px;
    overflow: hidden;
  }
  .timer-bar {
    height: 100%;
    background: linear-gradient(90deg, #3b82f6, #2ecc71);
    border-radius: 99px;
    transition: width 1s linear;
  }
  .status-text {
    text-align: center;
    font-size: 13px;
    color: #d97706;
    font-weight: 600;
    margin-bottom: 4px;
    display: none;
  }
  .status-text.expired { color: #ef4444; display: block; }
  .checking-text {
    text-align: center;
    font-size: 13px;
    color: #888;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
  }
  .spinner {
    width: 16px; height: 16px;
    border: 2px solid #ddd;
    border-top-color: #3b82f6;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    display: inline-block;
  }
  @keyframes spin { to { transform: rotate(360deg); } }

  .success-box {
    display: none;
    text-align: center;
    padding: 20px;
  }
  .success-box .icon { font-size: 60px; margin-bottom: 12px; }
  .success-box h2 { color: #2ecc71; font-size: 22px; margin-bottom: 8px; }
  .success-box p { color: #666; font-size: 14px; }

  .expired-box {
    display: none;
    text-align: center;
    padding: 20px;
  }
  .expired-box .icon { font-size: 50px; margin-bottom: 12px; }
  .expired-box h2 { color: #ef4444; font-size: 20px; margin-bottom: 8px; }
  .expired-box p { color: #666; font-size: 13px; margin-bottom: 16px; }
  .retry-btn {
    padding: 12px 28px;
    background: #3b82f6;
    color: white;
    border: none;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
  }
</style>
</head>
<body>

<?php
require_once __DIR__ . '/config.php';

$order   = $_GET['order']   ?? null;
$shop_id = $_GET['shop_id'] ?? null;

if (!$order || !$shop_id) {
    die('<div style="text-align:center;padding:40px;color:#ef4444;font-size:18px;">❌ Noto\'g\'ri havola!</div>');
}

$order_esc   = mysqli_real_escape_string($connect, $order);
$shop_id_esc = mysqli_real_escape_string($connect, $shop_id);

$checkout = mysqli_fetch_assoc(mysqli_query($connect,
    "SELECT * FROM checkout WHERE `order`='$order_esc' AND shop_id='$shop_id_esc'"
));

if (!$checkout) {
    die('<div style="text-align:center;padding:40px;color:#ef4444;font-size:18px;">❌ To\'lov topilmadi!</div>');
}

$shop = mysqli_fetch_assoc(mysqli_query($connect,
    "SELECT * FROM shops WHERE shop_id='$shop_id_esc'"
));

$amount     = $checkout['amount'];
$status     = $checkout['status'];
$card_num   = $shop['card_number'] ?? '5614 6835 8227 9246';
$card_bank  = $shop['card_bank']   ?? 'Uzcard';
$shop_name  = base64_decode($shop['shop_name'] ?? '');

// Karta formatlash: 5614683588279246 -> 5614 6835 8827 9246
$card_fmt = implode(' ', str_split(preg_replace('/\s+/', '', $card_num), 4));

// To'lov vaqti — checkout yaratilgan vaqtdan 5 daqiqa
$created_at = strtotime($checkout['date']);
$expires_at = $created_at + (5 * 60);
$now        = time();
$remaining  = max(0, $expires_at - $now);
$total_secs = 5 * 60;
?>

<div class="brand">Secure Payments | Tolovchi.uz</div>

<div class="card-box" id="main-box">

  <?php if ($status === 'paid'): ?>
  <div class="success-box" style="display:block;">
    <div class="icon">✅</div>
    <h2>To'lov qabul qilindi!</h2>
    <p>Miqdor: <strong><?= number_format($amount, 0, '.', ' ') ?> UZS</strong></p>
  </div>

  <?php elseif ($status === 'canceled' || $remaining <= 0): ?>
  <div class="expired-box" style="display:block;">
    <div class="icon">⏰</div>
    <h2>Vaqt tugadi!</h2>
    <p>To'lov muddati o'tib ketdi yoki bekor qilindi.</p>
    <button class="retry-btn" onclick="history.back()">⬅️ Ortga</button>
  </div>

  <?php else: ?>

  <div class="amount-label">TO'LOV SUMMASI</div>
  <div class="amount-value"><?= number_format($amount, 0, '.', ' ') ?> <span>UZS</span></div>

  <div class="warning-box">
    <span class="icon">⚠️</span>
    <p>Summani aynan shu miqdorda o'tkazing, aks holda to'lov boshqa birovga o'tishi mumkin!</p>
  </div>

  <div class="bank-card">
    <div class="card-chip"></div>
    <div class="card-number"><?= htmlspecialchars($card_fmt) ?></div>
    <div class="card-info">
      <div class="card-info-item">
        <label>Karta egasi</label>
        <span>X.M</span>
      </div>
      <div class="card-info-item" style="text-align:right;">
        <label>Bank</label>
        <span><?= htmlspecialchars($card_bank) ?></span>
      </div>
    </div>
  </div>

  <button class="copy-btn" onclick="copyCard(this)">💳 Karta raqamni nusxalash</button>

  <div class="timer-label">QOLGAN VAQT</div>
  <div class="timer-value" id="timer">00:00</div>
  <div class="timer-bar-bg">
    <div class="timer-bar" id="timer-bar" style="width:100%"></div>
  </div>
  <div class="status-text" id="expired-text">Vaqt tugagach to'lov qabul qilinmaydi!</div>
  <div class="checking-text" id="checking-text">
    <span class="spinner"></span> Tekshirilmoqda...
  </div>

  <?php endif; ?>

</div>

<script>
const cardRaw = "<?= preg_replace('/\s+/', '', $card_num) ?>";
const order   = "<?= htmlspecialchars($order) ?>";
const shopId  = "<?= htmlspecialchars($shop_id) ?>";
let remaining = <?= $remaining ?>;
const total   = <?= $total_secs ?>;

function copyCard(btn) {
  navigator.clipboard.writeText(cardRaw).then(() => {
    btn.textContent = '✅ Nusxalandi!';
    btn.classList.add('copied');
    setTimeout(() => {
      btn.textContent = '💳 Karta raqamni nusxalash';
      btn.classList.remove('copied');
    }, 2000);
  });
}

function pad(n) { return String(n).padStart(2, '0'); }

function updateTimer() {
  if (remaining <= 0) {
    document.getElementById('timer').textContent = '00:00';
    document.getElementById('timer-bar').style.width = '0%';
    document.getElementById('expired-text').classList.add('expired');
    document.getElementById('checking-text').style.display = 'none';
    // Sahifani yangilash
    setTimeout(() => location.reload(), 2000);
    return;
  }
  const m = Math.floor(remaining / 60);
  const s = remaining % 60;
  document.getElementById('timer').textContent = pad(m) + ':' + pad(s);
  document.getElementById('timer-bar').style.width = ((remaining / total) * 100) + '%';
  remaining--;
}

// To'lovni tekshirish (har 5 sekund)
function checkPayment() {
  fetch(`/api?method=check&order=${order}`)
    .then(r => r.json())
    .then(d => {
      if (d.data && d.data.status === 'paid') {
        document.getElementById('main-box').innerHTML = `
          <div class="success-box" style="display:block;">
            <div class="icon">✅</div>
            <h2>To'lov qabul qilindi!</h2>
            <p>Rahmat!</p>
          </div>`;
      } else if (d.data && d.data.status === 'canceled') {
        location.reload();
      }
    })
    .catch(() => {});
}

if (remaining > 0) {
  updateTimer();
  const timerInterval = setInterval(() => {
    updateTimer();
    if (remaining < 0) clearInterval(timerInterval);
  }, 1000);

  // Har 5 sekund tekshirish
  setInterval(checkPayment, 5000);
  checkPayment();
}
</script>

</body>
</html>
