<?php

$sub_domen = "tolovavto.up.railway.app";
require (__DIR__ . "/../config.php");

$administrator = getenv('ADMIN_ID') ?: "6365371142";
$admin = [$administrator];

// Sana va soat — global
$sana = date('Y-m-d');
$soat = date('H:i:s');

// Persistent cURL handle — TCP/SSL qayta ishlatiladi, har safar yangi ulanish yo'q
$_bot_ch = null;
function bot($method, $datas=[]){
    global $_bot_ch;
    if(!$_bot_ch){
        $_bot_ch = curl_init();
        curl_setopt_array($_bot_ch, [
            CURLOPT_RETURNTRANSFER    => true,
            CURLOPT_TIMEOUT           => 8,
            CURLOPT_CONNECTTIMEOUT    => 3,
            CURLOPT_HTTP_VERSION      => CURL_HTTP_VERSION_1_1,
            CURLOPT_TCP_NODELAY       => true,
            CURLOPT_NOSIGNAL          => 1,
            CURLOPT_DNS_CACHE_TIMEOUT => 600,
            CURLOPT_FORBID_REUSE      => false,
            CURLOPT_FRESH_CONNECT     => false,
            CURLOPT_HTTPHEADER        => ['Connection: keep-alive','Expect:'],
            CURLOPT_POST              => true,
        ]);
    }
    curl_setopt($_bot_ch, CURLOPT_URL, "https://api.telegram.org/bot".API_KEY."/".$method);
    curl_setopt($_bot_ch, CURLOPT_POSTFIELDS, $datas);
    $res = curl_exec($_bot_ch);
    return $res ? json_decode($res) : null;
}

// Parallel ikki bot() chaqiruvini bir vaqtda yuborish
// answerCallbackQuery + editMessage/sendMessage bitta HTTP roundtrip
function botParallel($calls){
    $mh = curl_multi_init();
    $handles = [];
    foreach($calls as $i => $call){
        $ch = curl_init("https://api.telegram.org/bot".API_KEY."/".$call[0]);
        curl_setopt_array($ch,[
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $call[1] ?? [],
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_NOSIGNAL       => 1,
            CURLOPT_TCP_NODELAY    => true,
            CURLOPT_HTTPHEADER     => ['Connection: keep-alive','Expect:'],
        ]);
        curl_multi_add_handle($mh, $ch);
        $handles[$i] = $ch;
    }
    $active = null;
    do {
        $st = curl_multi_exec($mh, $active);
        if($active) curl_multi_select($mh, 0.01);
    } while($active > 0 && $st == CURLM_OK);
    $results = [];
    foreach($handles as $i => $ch){
        $r = curl_multi_getcontent($ch);
        $results[$i] = $r ? json_decode($r) : null;
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);
    return $results;
}

function generate(){
    $arr = ['A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','R','S','T','U','V','X','Y','Z','1','2','3','4','5','6','7','8','9','0'];
    $pass = "";
    for($i=0;$i<10;$i++) $pass .= $arr[rand(0,count($arr)-1)];
    return $pass;
}

function joinchat($id){
    global $connect, $administrator;
    $result = $connect->query("SELECT type,link,channelID,title FROM `channels`");
    if($result->num_rows == 0 || $id == $administrator) return true;

    // 30 soniya DB cache — Telegram API ni keraksiz chaqirmaymiz
    $id_esc = mysqli_real_escape_string($connect, $id);
    $cache_ok = false;
    $cache_res = mysqli_query($connect,
        "SELECT joined_ok, joined_check FROM users WHERE user_id='$id_esc' LIMIT 1"
    );
    if($cache_res){
        $cache_row = mysqli_fetch_assoc($cache_res);
        if($cache_row
           && isset($cache_row['joined_ok'])
           && $cache_row['joined_ok'] == 1
           && !empty($cache_row['joined_check'])
           && (time() - strtotime($cache_row['joined_check'])) < 30){
            return true;
        }
    }
    // joined_ok ustuni yo'q bo'lsa — qo'shib qo'yamiz (bir marta)
    elseif(!$cache_res){
        @mysqli_query($connect,"ALTER TABLE users ADD COLUMN joined_ok TINYINT(1) NOT NULL DEFAULT 0");
        @mysqli_query($connect,"ALTER TABLE users ADD COLUMN joined_check DATETIME NULL DEFAULT NULL");
    }

    $lock_channels = [];
    $all_rows = [];
    while($row=$result->fetch_assoc()){
        $all_rows[] = $row;
        if($row['type']=="lock") $lock_channels[] = $row['channelID'];
    }

    $statuses = [];
    if(!empty($lock_channels)){
        $mh = curl_multi_init();
        curl_multi_setopt($mh, CURLMOPT_PIPELINING, 2);
        $handles = [];
        foreach($lock_channels as $chId){
            $ch = curl_init("https://api.telegram.org/bot".API_KEY."/getChatMember");
            curl_setopt_array($ch,[
                CURLOPT_RETURNTRANSFER=>true,
                CURLOPT_POSTFIELDS=>['chat_id'=>$chId,'user_id'=>$id],
                CURLOPT_TIMEOUT=>4,
                CURLOPT_CONNECTTIMEOUT=>2,
                CURLOPT_NOSIGNAL=>1,
                CURLOPT_TCP_NODELAY=>true,
                CURLOPT_HTTPHEADER=>['Connection: keep-alive','Expect:'],
            ]);
            curl_multi_add_handle($mh,$ch);
            $handles[$chId]=$ch;
        }
        $active=null;
        do{
            $st = curl_multi_exec($mh,$active);
            if($active) curl_multi_select($mh, 0.01);
        } while($active>0 && $st==CURLM_OK);
        foreach($handles as $chId=>$ch){
            $res=json_decode(curl_multi_getcontent($ch));
            $statuses[$chId]=$res->result->status ?? null;
            curl_multi_remove_handle($mh,$ch); curl_close($ch);
        }
        curl_multi_close($mh);
    }

    $no_subs=0; $button=[];
    foreach($all_rows as $row){
        $type=$row['type']; $link=$row['link']; $channelID=$row['channelID'];
        $title = $row['title'] ? base64_decode($row['title']) : "Kanal";
        if($type=="lock"){
            $s = $statuses[$channelID] ?? null;
            if($s=="left"||$s=="kicked"||$s===null){
                $button[]=['text'=>"❌ $title",'url'=>$link]; $no_subs++;
            } else {
                $button[]=['text'=>"✅ $title",'url'=>$link];
            }
        }elseif($type=="request"){
            $c=$connect->query("SELECT id FROM `requests` WHERE id='$id' AND chat_id='$channelID' LIMIT 1");
            if($c->num_rows>0) $button[]=['text'=>"✅ $title",'url'=>$link];
            else{ $button[]=['text'=>"❌ $title",'url'=>$link]; $no_subs++; }
        }elseif($type=="social"){
            $button[]=['text'=>base64_decode($row['title']),'url'=>$link];
        }
    }
    if($no_subs>0){
        mysqli_query($connect,"UPDATE users SET joined_ok=0, joined_check=NOW() WHERE user_id='$id_esc'");
        $button[]=['text'=>"🔄 Tekshirish",'callback_data'=>"result"];
        bot('sendMessage',['chat_id'=>$id,'text'=>"⛔ Kanallarga obuna bo'ling:",'parse_mode'=>'html','reply_markup'=>json_encode(['inline_keyboard'=>array_chunk($button,1)])]);
        exit;
    } else {
        mysqli_query($connect,"UPDATE users SET joined_ok=1, joined_check=NOW() WHERE user_id='$id_esc'");
        return true;
    }
}

function getStats($connect, $shop_id){
    static $_idx_done = false;
    if(!$_idx_done){
        @mysqli_query($connect,"ALTER TABLE checkout ADD INDEX idx_shop_status_paid (shop_id, status)");
        @mysqli_query($connect,"ALTER TABLE payments ADD INDEX idx_shop_id_type (shop_id, card_type)");
        $_idx_done = true;
    }
    $today    = date('Y-m-d');
    $yesterday= date('Y-m-d', strtotime('-1 day'));
    $month_s  = date('Y-m-01');
    $prev_s   = date('Y-m-01', strtotime('-1 month'));
    $prev_e   = date('Y-m-t',  strtotime('-1 month'));
    $sid      = mysqli_real_escape_string($connect, $shop_id);

    $row = mysqli_fetch_assoc(mysqli_query($connect, "
        SELECT
          COALESCE(SUM(CASE WHEN DATE(date)='$today'     THEN amount ELSE 0 END),0) as bugun,
          COALESCE(SUM(CASE WHEN DATE(date)='$yesterday' THEN amount ELSE 0 END),0) as kecha,
          COALESCE(SUM(CASE WHEN date>='$month_s'        THEN amount ELSE 0 END),0) as bu_oy,
          COALESCE(SUM(CASE WHEN date BETWEEN '$prev_s 00:00:00' AND '$prev_e 23:59:59' THEN amount ELSE 0 END),0) as otgan_oy,
          COALESCE(SUM(amount),0) as jami
        FROM checkout
        WHERE status='paid' AND shop_id='$sid'
    "));

    return $row;
}

// ============================================================
// UPDATE QABUL QILISH — xavfsiz, null-safe
// ============================================================
$raw_input = file_get_contents('php://input');
$update    = json_decode($raw_input);
$message   = $update->message  ?? null;
$callback  = $update->callback_query ?? null;

$bot = 'tolovci_uz_bot';

// Default qiymatlar — hech biri undefined bo'lmaydi
$cid    = null;
$text   = null;
$data   = null;
$mid    = null;
$qid    = null;
$name   = '';
$number = null;
$contact= null;

if(isset($message)){
    $contact = $message->contact ?? null;
    $number  = $contact->phone_number ?? null;
    $cid     = $message->chat->id ?? null;
    $text    = $message->text ?? null;
    $mid     = $message->message_id ?? null;
    $name    = $message->from->first_name ?? '';
}
if(isset($callback)){
    $data = $callback->data ?? null;
    $qid  = $callback->id ?? null;
    $cid  = $callback->message->chat->id ?? null;
    $mid  = $callback->message->message_id ?? null;
    $name = $callback->from->first_name ?? '';
}

// Agar hech qanday update kelmagan bo'lsa — chiqib ket
if(!$cid) exit;

$cid_esc = mysqli_real_escape_string($connect, $cid);

$res      = mysqli_query($connect,"SELECT id,balance,deposit,step FROM users WHERE user_id='$cid_esc' LIMIT 1");
$user_row = mysqli_fetch_assoc($res);
if($user_row){
    $uid     = $user_row['id'];
    $balance = $user_row['balance'];
    $payment = $user_row['deposit'];
    $step    = $user_row['step'];
} else {
    if(isset($message)){
        mysqli_query($connect,"INSERT INTO users(user_id,balance,deposit,date,time,action) VALUES('$cid_esc','0','0','$sana','$soat','member')");
    }
    $uid=0; $balance=0; $payment=0; $step='null';
}

$menu   = json_encode(['resize_keyboard'=>true,'keyboard'=>[[['text'=>"🏪 Kassalarim"]],[['text'=>"💵 Hisobim"],['text'=>"💳 To'ldirish"]],[['text'=>"📕 Qoʻllanma"],['text'=>"📖 API Hujjatlar"]]]]);
$menu_p = json_encode(['resize_keyboard'=>true,'keyboard'=>[
    [['text'=>"🏪 Kassalarim"]],
    [['text'=>"💵 Hisobim"],['text'=>"💳 To'ldirish"]],
    [['text'=>"📕 Qoʻllanma"],['text'=>"📖 API Hujjatlar"]],
    [['text'=>"🗄️ Boshqaruv"]]
]]);
$panel  = json_encode(['resize_keyboard'=>true,'keyboard'=>[
    [['text'=>"📊 Statistika"],['text'=>"📢 Kanallar"]],
    [['text'=>"🗑️ Kanal o'chirish"]],
    [['text'=>"👤 Foydalanuvchi"],['text'=>"📨 Xabar yuborish"]],
    [['text'=>"🔗 Kassa ulash"],['text'=>"💰 Oylik narh"]],
    [['text'=>"🏪 Kassa boshqaruv"],['text'=>"🚫 Kassa ban"]],
    [['text'=>"⏪ Ortga"]]
]]);

$back = json_encode(['resize_keyboard'=>true,'keyboard'=>[[['text'=>"⏪ Ortga"]]]]);
$m    = in_array($cid,$admin) ? $menu_p : $menu;

// ============================================================
// CALLBACK: result (kanallarni tekshirish)
// ============================================================
if(!empty($data) && $data=="result"){
    bot('DeleteMessage',['chat_id'=>$cid,'message_id'=>$mid]);
    if(joinchat($cid)==true) bot('SendMessage',['chat_id'=>$cid,'text'=>"✅ <b>Tasdiqlandi!</b>",'parse_mode'=>'html','reply_markup'=>$m]);
    exit;
}

// ============================================================
// /start va Ortga
// ============================================================
if(!empty($text) && ($text=="/start" || $text=="⏪ Ortga")){
    static $has_channels = null;
    if($has_channels === null)
        $has_channels = mysqli_num_rows(mysqli_query($connect,"SELECT id FROM channels LIMIT 1")) > 0;
    if(!$has_channels || joinchat($cid)==true){
        mysqli_query($connect,"UPDATE users SET step='null' WHERE user_id='$cid_esc'");
        bot('sendMessage',['chat_id'=>$cid,'text'=>"👋🏻 <b>Assalomu alaykum $name!</b>\n\n@$bot botga xush kelibsiz.",'parse_mode'=>'html','reply_markup'=>$m]);
        exit;
    }
}

// ============================================================
// QO'LLANMA
// ============================================================
if(!empty($text) && $text=="📕 Qoʻllanma"){
    bot('sendMessage',['chat_id'=>$cid,'text'=>"<b>📘 Qoʻllanma:</b>\n\nKassa qoʻshib admindan tasdiqlatasiz. Tasdiqlangach oylik toʻlovni toʻlaysiz va hisobni ulaysiz.\n\n📣 Yordam: @xmtvv1",'parse_mode'=>'html','reply_markup'=>$m]);
    exit;
}

// ============================================================
// HISOBIM
// ============================================================
if(!empty($text) && $text=="💵 Hisobim"){
    bot('sendMessage',['chat_id'=>$cid,'text'=>"👔 <b>Sizning hisobingiz!</b>\n\n• ID: <code>$uid</code>\n• Balans: <b>$balance</b> so'm\n• Kiritgan: <b>$payment</b> so'm",'parse_mode'=>'html','reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"🔄 Yangilash",'callback_data'=>"Hisobim"]],[['text'=>"📋 To'ldirish tarixi",'callback_data'=>"user_history=1"]]]])]);
    exit;
}
if(!empty($data) && $data=="Hisobim"){
    bot('editMessageText',['chat_id'=>$cid,'message_id'=>$mid,'text'=>"👔 <b>Sizning hisobingiz!</b>\n\n• ID: <code>$uid</code>\n• Balans: <b>$balance</b> so'm\n• Kiritgan: <b>$payment</b> so'm",'parse_mode'=>'html','reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"🔄 Yangilash",'callback_data'=>"Hisobim"]],[['text'=>"📋 To'ldirish tarixi",'callback_data'=>"user_history=1"]]]])]);
    exit;
}

// ============================================================
// TO'LDIRISH
// ============================================================
if(!empty($text) && $text=="💳 To'ldirish"){
    bot('sendMessage',['chat_id'=>$cid,'text'=>"<b>👇 To'lov tizimini tanlang!</b>",'parse_mode'=>'html','reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"🏦 Bank karta (Avtomatik)",'callback_data'=>"uzcard"]]]])]);
    exit;
}
if(!empty($data) && $data=="uzcard"){
    bot('DeleteMessage',['chat_id'=>$cid,'message_id'=>$mid]);
    bot('SendMessage',['chat_id'=>$cid,'text'=>"💵 <b>Toʻlov miqdorini kiriting:</b>\n\nMinimal: 1000 soʻm",'parse_mode'=>'html','reply_markup'=>$back]);
    mysqli_query($connect,"UPDATE users SET step='uzcard_auto' WHERE user_id='$cid_esc'");
    exit;
}

if($step=="uzcard_auto" && !empty($text)){
    if(!is_numeric($text)){ bot('sendMessage',['chat_id'=>$cid,'text'=>"🔢 <b>Faqat raqam kiriting!</b>",'parse_mode'=>'html','reply_markup'=>$back]); exit; }
    $amount_original = intval(trim($text));
    if($amount_original < 1000){ bot('sendMessage',['chat_id'=>$cid,'text'=>"⚠️ <b>Minimal 1000 so'm!</b>",'parse_mode'=>'html','reply_markup'=>$back]); exit; }

    $expire_time = date('Y-m-d H:i:s', strtotime('-5 minutes'));
    mysqli_query($connect,"UPDATE checkout SET status='canceled' WHERE shop_id='127000' AND status='pending' AND date <= '$expire_time'");

    $active_order = mysqli_fetch_assoc(mysqli_query($connect,
        "SELECT * FROM checkout WHERE user_id='$cid_esc' AND shop_id='127000' AND status='pending' AND date > '$expire_time'"
    ));
    if($active_order){
        $pay_url  = "https://$sub_domen/pay?order=".$active_order['order']."&shop_id=127000";
        $main_shop = mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM shops WHERE shop_id='127000'"));
        $card_raw  = ($main_shop && !empty($main_shop['card_number'])) ? preg_replace('/\s+/','',$main_shop['card_number']) : '5614683582279246';
        $act_amount = $active_order['amount'];
        bot('sendMessage',['chat_id'=>$cid,'text'=>"✅ <b>Faol to'lovingiz mavjud!</b>\n\n💵 To'lash kerak: <b>".number_format($act_amount,0,'.',' ')."</b> so'm\n⚠️ Aynan shu miqdorni yuboring!\n\n❌ Bekor qilish uchun tugmani bosing.",'parse_mode'=>'html','reply_markup'=>json_encode(['inline_keyboard'=>[
            [['text'=>'💵 Miqdorni nusxalash','copy_text'=>['text'=>$act_amount]]],
            [['text'=>'💳 Kartani nusxalash','copy_text'=>['text'=>$card_raw]]],
            [['text'=>"✅ To'lovni tekshirish",'callback_data'=>"chk=".$act_amount."=".$active_order['order']]],
            [['text'=>"🌐 Web to'lov",'url'=>$pay_url]],
            [['text'=>"❌ Bekor qilish",'callback_data'=>"cancel_order=".$active_order['order']]],
        ]])]);
        mysqli_query($connect,"UPDATE users SET step='null' WHERE user_id='$cid_esc'");
        exit;
    }

    $amount    = $amount_original;
    $main_shop = mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM shops WHERE shop_id='127000'"));
    $card_raw  = ($main_shop && !empty($main_shop['card_number'])) ? preg_replace('/\s+/','',$main_shop['card_number']) : '5614683582279246';
    $card_show = chunk_split($card_raw,4,' ');

    $order = generate();
    $today_dt = date("Y-m-d H:i:s");
    mysqli_query($connect,
        "INSERT INTO checkout (`order`, shop_id, shop_key, amount, status, `over`, date, user_id)
         VALUES ('$order', '127000', 'OP004K6367', '$amount', 'pending', '5', '$today_dt', '$cid_esc')"
    );

    $pay_url = "https://$sub_domen/pay?order=$order&shop_id=127000";

    bot('sendMessage',['chat_id'=>$cid,'text'=>"➡️ <b>To'lov kartasi:</b> <code>$card_show</code>\n\n💵 To'lash kerak: <code>$amount</code> so'm\n⏰ Kutish vaqti: <b>5</b> daqiqa\n✅ To'lov avtomatik qabul qilinadi\n\n👉🏻 Aynan <b>$amount</b> so'm yuboring!",'parse_mode'=>'html','reply_markup'=>json_encode(['inline_keyboard'=>[
        [['text'=>"💵 $amount so'm — nusxalash",'copy_text'=>['text'=>$amount]]],
        [['text'=>'💳 Kartani nusxalash','copy_text'=>['text'=>$card_raw]]],
        [['text'=>"✅ To'lovni tekshirish",'callback_data'=>"chk=$amount=$order"]],
        [['text'=>"🌐 Web to'lov sahifasi",'url'=>$pay_url]],
        [['text'=>"❌ Bekor qilish",'callback_data'=>"cancel_order=$order"]],
    ]])]);
    mysqli_query($connect,"UPDATE users SET step='null' WHERE user_id='$cid_esc'");
    exit;
}

// ============================================================
// TO'LOVNI TEKSHIRISH
// ============================================================
if(!empty($data) && mb_stripos($data,"chk=")!==false){
    bot('answerCallbackQuery',['callback_query_id'=>$qid,'text'=>"🔄 Tekshirilmoqda..."]);
    $parts    = explode("=",$data);
    $amount_c = (int)$parts[1];
    $order_c  = $parts[2];
    $order_esc = mysqli_real_escape_string($connect,$order_c);

    $checkout_row = mysqli_fetch_assoc(mysqli_query($connect,
        "SELECT * FROM checkout WHERE `order`='$order_esc' LIMIT 1"
    ));

    $paid = false;

    if($checkout_row && $checkout_row['status']==='paid'){
        $already = mysqli_fetch_assoc(mysqli_query($connect,
            "SELECT * FROM checkout WHERE `order`='$order_esc' AND user_id='$cid_esc' AND paid_to_user='1' LIMIT 1"
        ));
        if(!$already){
            mysqli_query($connect,"UPDATE users SET balance=balance+$amount_c, deposit=deposit+$amount_c WHERE user_id='$cid_esc'");
            mysqli_query($connect,"UPDATE checkout SET paid_to_user='1' WHERE `order`='$order_esc'");
        }
        $paid = true;
    } elseif($checkout_row && $checkout_row['status']==='pending'){
        $expire_time = date('Y-m-d H:i:s', strtotime('-6 minutes'));
        $email_pay = mysqli_fetch_assoc(mysqli_query($connect,
            "SELECT * FROM payments
             WHERE amount='$amount_c' AND status='pending'
             AND card_type='credit'
             AND created_at >= '$expire_time'
             LIMIT 1"
        ));
        if($email_pay){
            mysqli_query($connect,"UPDATE payments SET status='used', used_order='$order_esc' WHERE id='".$email_pay['id']."'");
            mysqli_query($connect,"UPDATE checkout SET status='paid', paid_to_user='1' WHERE `order`='$order_esc'");
            mysqli_query($connect,"UPDATE users SET balance=balance+$amount_c, deposit=deposit+$amount_c WHERE user_id='$cid_esc'");
            $paid = true;
        }
    }

    if($paid){
        bot('editMessageText',['chat_id'=>$cid,'message_id'=>$mid,
            'text'=>"✅ <b>To'lov tasdiqlandi!</b>\n\n💵 <b>".number_format($amount_c,0,'.',' ')."</b> so'm hisobingizga qo'shildi!",
            'parse_mode'=>'html','reply_markup'=>json_encode(['inline_keyboard'=>[]])]);
    } else {
        $pay_url = "https://$sub_domen/pay?order=$order_c&shop_id=127000";
        bot('editMessageText',['chat_id'=>$cid,'message_id'=>$mid,
            'text'=>"⏳ <b>To'lov hali kelmagan!</b>\n\n5 daqiqa ichida to'lang va qayta tekshiring.",
            'parse_mode'=>'html','reply_markup'=>json_encode(['inline_keyboard'=>[
                [['text'=>"🔄 Qayta tekshirish",'callback_data'=>"chk=$amount_c=$order_c"]],
                [['text'=>"🌐 Web to'lov",'url'=>$pay_url]],
                [['text'=>"❌ Bekor qilish",'callback_data'=>"cancel_order=$order_c"]],
            ]])]);
    }
    exit;
}

// ============================================================
// BEKOR QILISH
// ============================================================
if(!empty($data) && mb_stripos($data,"cancelpay=")!==false){
    bot('answerCallbackQuery',['callback_query_id'=>$qid,'text'=>"❓ Tasdiqlang"]);
    $id = explode("=",$data)[1];
    bot('editMessageReplyMarkup',['chat_id'=>$cid,'message_id'=>$mid,
        'reply_markup'=>json_encode(['inline_keyboard'=>[
            [['text'=>"✅ Ha, bekor qilish",'callback_data'=>"cancelpayy=$id"],['text'=>"❌ Yo'q",'callback_data'=>"del_msg"]],
        ]])]);
    exit;
}
if(!empty($data) && mb_stripos($data,"cancelpayy=")!==false){
    bot('answerCallbackQuery',['callback_query_id'=>$qid,'text'=>"✅ Bekor qilindi"]);
    $id = explode("=",$data)[1];
    mysqli_query($connect,"UPDATE payments SET status='cancel' WHERE id='$id' AND user_id='$cid_esc'");
    bot('editMessageText',['chat_id'=>$cid,'message_id'=>$mid,
        'text'=>"❌ <b>To'lov bekor qilindi!</b>",'parse_mode'=>'html',
        'reply_markup'=>json_encode(['inline_keyboard'=>[]])]);
    exit;
}
if(!empty($data) && $data=="del_msg"){
    bot('answerCallbackQuery',['callback_query_id'=>$qid]);
    bot('DeleteMessage',['chat_id'=>$cid,'message_id'=>$mid]);
    exit;
}
if(!empty($data) && mb_stripos($data,"cancel_order=")!==false){
    bot('answerCallbackQuery',['callback_query_id'=>$qid,'text'=>"✅ Bekor qilindi"]);
    $order_c = explode("=",$data)[1];
    $esc     = mysqli_real_escape_string($connect,$order_c);
    mysqli_query($connect,"UPDATE checkout SET status='canceled' WHERE `order`='$esc'");
    mysqli_query($connect,"UPDATE payments SET status='cancel' WHERE used_order='$esc' AND user_id='$cid_esc'");
    bot('editMessageText',['chat_id'=>$cid,'message_id'=>$mid,
        'text'=>"❌ <b>To'lov bekor qilindi!</b>",'parse_mode'=>'html',
        'reply_markup'=>json_encode(['inline_keyboard'=>[]])]);
    exit;
}

// ============================================================
// API HUJJATLAR
// ============================================================
if(!empty($text) && $text=="📖 API Hujjatlar"){
    bot('sendMessage',['chat_id'=>$cid,'text'=>"<b>📖 API Hujjatlar:</b>\nhttps://tolovavto.up.railway.app/docs",'disable_web_page_preview'=>true,'parse_mode'=>'html','reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"📁 API Docs",'url'=>"https://tolovavto.up.railway.app/docs"]]]])]);
    exit;
}

// ============================================================
// KASSALARIM
// ============================================================
function show_kassalar($cid,$connect,$mid=null,$edit=false,$qid=null){
    $cid_e = mysqli_real_escape_string($connect,$cid);
    $result = mysqli_query($connect,"SELECT id,shop_name,status FROM `shops` WHERE `user_id`='$cid_e'");
    $i=0; $key=[]; $has_rows=false;
    while($us=mysqli_fetch_assoc($result)){
        $has_rows=true; $i++;
        $icon = ($us['status']=="waiting")?"🔄":(($us['status']=="confirm")?"✅":"⛔");
        $key[]=[["text"=>"$i. $icon ".base64_decode($us['shop_name']),"callback_data"=>"kassa_set=".$us['id']]];
    }
    $key[]=[['text'=>"➕ Kassa qoʻshish",'callback_data'=>"add_kassa"]];
    $kb  = json_encode(['inline_keyboard'=>$key]);
    $txt = $has_rows ? "🏪 <b>Kassalaringiz:</b>" : "⚠️ Kassalar mavjud emas!";
    if($edit){
        botParallel([
            ['answerCallbackQuery', ['callback_query_id'=>$qid]],
            ['editmessagetext', ['chat_id'=>$cid,'message_id'=>$mid,'text'=>$txt,'parse_mode'=>'html','reply_markup'=>$kb]],
        ]);
    } else {
        bot('sendmessage',['chat_id'=>$cid,'text'=>$txt,'parse_mode'=>'html','reply_markup'=>$kb]);
    }
}
if(!empty($text) && $text=="🏪 Kassalarim"){ show_kassalar($cid,$connect); exit; }
if(!empty($data) && $data=="Kassalarim"){    show_kassalar($cid,$connect,$mid,true,$qid); exit; }

// ============================================================
// KASSA KO'RISH
// ============================================================
if(!empty($data) && mb_stripos($data,"kassa_set=")!==false){
    $id  = explode("=",$data)[1];
    $rew = mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM shops WHERE id='$id'"));
    $nomi        = base64_decode($rew['shop_name']);
    $shop_id     = $rew['shop_id'];
    $shop_key    = $rew['shop_key'];
    $address     = $rew['shop_address'];
    $status      = $rew['status'];
    $month_status= $rew['month_status'] ?? "Toʻlanmagan!";
    $over        = $rew['over_day'] ?? "0";
    $phone       = $rew['phone'] ?? null;

    $kb = ['inline_keyboard'=>[]]; $confirm_text = "";

    if($status=="waiting"){
        $icon = "🔄 Kutilmoqda...";
        $kb['inline_keyboard'][]=[['text'=>"🗑️ Kassani o'chirish",'callback_data'=>"delete_kassa_ask=$id"]];
        $kb['inline_keyboard'][]=[['text'=>"⏪ Ortga",'callback_data'=>"Kassalarim"]];
    } elseif($status=="confirm"){
        $created = strtotime($rew['date'] ?? 'now');
        $expire  = date('Y-m-d', strtotime("+".(int)$over." days", $created));
        $stats   = getStats($connect,$shop_id);
        $stat_text = "\n📊 <b>Moliyaviy statistika</b>\n\n".
            "📅 Bugungi tushum: <b>".number_format($stats['bugun'],0,'.',' ')."</b> so'm\n".
            "📆 Kechagi tushum: <b>".number_format($stats['kecha'],0,'.',' ')."</b> so'm\n".
            "🗓 Bu oygi tushum: <b>".number_format($stats['bu_oy'],0,'.',' ')."</b> so'm\n".
            "📉 O'tgan oygi tushum: <b>".number_format($stats['otgan_oy'],0,'.',' ')."</b> so'm\n".
            "💼 Jami aylanma: <b>".number_format($stats['jami'],0,'.',' ')."</b> so'm\n";

        $need_pay = (empty($month_status) || $month_status=="Toʻlanmagan!");

        if($need_pay){
            $icon         = "📛 Toʻlanmagan!";
            $confirm_text = "\n🔎 Oylik to'lov holati: <b>$month_status</b>\n".$stat_text;
            $kb['inline_keyboard'][]=[['text'=>"💵 Oylik to'lovni to'lash",'callback_data'=>"kassa_payment=$shop_id=$id"]];
        } elseif(empty($phone)){
            $icon         = "📛 Hisob ulanmagan!";
            $confirm_text = "\n🔎 Holat: <b>$month_status</b>\n".$stat_text;
            $kb['inline_keyboard'][]=[['text'=>"🔗 Kassa ulash",'callback_data'=>"req_connect=$shop_id=$cid"]];
        } else {
            $icon         = "✅ Faol!";
            $confirm_text = "\n🔘 Oylik to'lov holati: <b>$month_status</b>\n📆 Kassa muddati: <b>$expire</b>\n".$stat_text;
            $kb['inline_keyboard'][]=[['text'=>"💰 To'lovlar tarixi",'callback_data'=>"kassa_history=$shop_id=$id=1"]];
            $kb['inline_keyboard'][]=[['text'=>"⏩ Muddatni uzaytirish",'callback_data'=>"kassa_payment=$shop_id=$id"]];
            $kb['inline_keyboard'][]=[['text'=>"⚙️ Sozlamalar",'callback_data'=>"kassa_sozlama=$shop_id=$id"]];
        }
        $kb['inline_keyboard'][]=[['text'=>"🗑️ Kassani o'chirish",'callback_data'=>"delete_kassa_ask=$id"]];
        $kb['inline_keyboard'][]=[['text'=>"⏪ Ortga",'callback_data'=>"Kassalarim"]];
    } elseif($status=="canceled"){
        $icon = "⛔ Bekor qilingan!";
        $kb['inline_keyboard'][]=[['text'=>"🗑️ Kassani o'chirish",'callback_data'=>"delete_kassa_ask=$id"]];
        $kb['inline_keyboard'][]=[['text'=>"⏪ Ortga",'callback_data'=>"Kassalarim"]];
    }

    botParallel([
        ['answerCallbackQuery', ['callback_query_id'=>$qid]],
        ['editMessageText', ['chat_id'=>$cid,'message_id'=>$mid,
            'text'=>"<b>$nomi ($icon)</b>\n$confirm_text\n🆔 Shop ID: <code>$shop_id</code>\n🔑 Shop Key: <code>$shop_key</code>\n🔗 Manzil: <b>$address</b>",
            'parse_mode'=>'html','reply_markup'=>json_encode($kb)]],
    ]);
    exit;
}

// ============================================================
// SOZLAMALAR
// ============================================================
if(!empty($data) && mb_stripos($data,"kassa_sozlama=")!==false){
    $parts     = explode("=",$data);
    $shop_id_s = $parts[1];
    $set_id_s  = $parts[2];
    $rew       = mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM shops WHERE shop_id='$shop_id_s'"));
    $card_num  = $rew['card_number'] ?? null;
    $card_bank = $rew['card_bank'] ?? null;
    $card_owner= $rew['card_owner'] ?? null;

    $provider = "—";
    if($card_num){
        $first    = substr(preg_replace('/\s+/','',$card_num),0,1);
        $provider = ($first=='9') ? "HUMO ✅" : "UZCARD ✅";
    }
    $card_show   = $card_num ? chunk_split(preg_replace('/\s+/','',$card_num),4,' ') : "Kiritilmagan";
    $owner_show  = !empty($card_owner) ? $card_owner : "Kiritilmagan";
    $email_show  = !empty($rew['email']) ? $rew['email'] : "Kiritilmagan";

    $sozlama_kb = json_encode(['inline_keyboard'=>[
        [['text'=>"💳 Karta raqam kiritish",'callback_data'=>"set_card=$shop_id_s=$set_id_s"]],
        [['text'=>"📩 Kassa ulash",'callback_data'=>"req_connect=$shop_id_s=$cid"]],
        [['text'=>"⚠️ Keyni yangilash",'callback_data'=>"new_key=$shop_id_s"]],
        [['text'=>"⏪ Ortga",'callback_data'=>"kassa_set=$set_id_s"]],
    ]]);
    $sozlama_txt = "⚙️ <b>Kassa sozlamalari</b>\n\n".
        "📧 Ulangan e-mail: <code>$email_show</code>\n".
        "🔌 Provider: $provider\n".
        "💳 Karta: <code>$card_show</code>".($card_bank?" <b>$card_bank</b>":"")."\n".
        "👤 Karta egasi: <b>$owner_show</b>\n".
        "🆔 Shop ID: <code>$shop_id_s</code>\n".
        "🔑 Shop Key: <code>".$rew['shop_key']."</code>\n".
        "🔗 Manzil: <b>".$rew['shop_address']."</b>";
    botParallel([
        ['answerCallbackQuery', ['callback_query_id'=>$qid]],
        ['editMessageText', ['chat_id'=>$cid,'message_id'=>$mid,'text'=>$sozlama_txt,'parse_mode'=>'html','reply_markup'=>$sozlama_kb]],
    ]);
    exit;
}

// ============================================================
// FOYDALANUVCHI TO'LDIRISH TARIXI
// ============================================================
if(!empty($data) && mb_stripos($data,"user_history=")!==false){
    $page     = (int)(explode("=",$data)[1] ?? 1);
    $per_page = 8;
    $offset   = ($page-1)*$per_page;

    $total_r    = mysqli_fetch_assoc(mysqli_query($connect,
        "SELECT COUNT(*) as c FROM checkout WHERE user_id='$cid_esc' AND shop_id='127000' AND status='paid'"
    ));
    $total      = (int)($total_r['c'] ?? 0);
    $total_pages= max(1, ceil($total/$per_page));

    $res = mysqli_query($connect,
        "SELECT c.amount, c.date,
                p.merchant, p.date as pay_date, p.card_type
         FROM checkout c
         LEFT JOIN payments p ON p.used_order = c.`order`
         WHERE c.user_id='$cid_esc' AND c.shop_id='127000' AND c.status='paid'
         ORDER BY c.date DESC LIMIT $per_page OFFSET $offset"
    );

    $bal_show = number_format((int)$balance,0,'.',' ');
    $dep_show = number_format((int)$payment,0,'.',' ');

    if($res && mysqli_num_rows($res)>0){
        $list = ""; $i = $offset+1;
        while($row=mysqli_fetch_assoc($res)){
            $amt    = number_format((int)$row['amount'],0,'.',' ');
            $dt     = substr($row['date'],0,16);
            $merchant = $row['merchant'] ?? '';
            $pay_dt   = substr($row['pay_date'] ?? '',0,16);
            $extra    = (!empty($merchant)) ? "\n💳 $merchant" : "";
            if(!empty($pay_dt)) $extra .= "\n🕒 Bank: $pay_dt";
            $list .= "<b>$i</b>. ✅ <b>+$amt</b> so'm\n📅 $dt".$extra."\n\n";
            $i++;
        }
        $txt = "📋 <b>To'ldirish tarixi</b>\n\n💵 Balans: <b>$bal_show</b> so'm\n📥 Jami kiritgan: <b>$dep_show</b> so'm\n\n$list";
    } else {
        $txt = "📋 <b>To'ldirish tarixi</b>\n\n💵 Balans: <b>$bal_show</b> so'm\n\n❌ Hali to'ldirish tarixi yo'q.";
    }

    $nav = [];
    if($page>1)           $nav[]=['text'=>"◀️ Oldingi",'callback_data'=>"user_history=".($page-1)];
    if($total_pages>1)    $nav[]=['text'=>"$page/$total_pages",'callback_data'=>"noop"];
    if($page<$total_pages)$nav[]=['text'=>"Keyingi ▶️",'callback_data'=>"user_history=".($page+1)];
    $kb = ['inline_keyboard'=>[]];
    if(!empty($nav)) $kb['inline_keyboard'][] = $nav;
    $kb['inline_keyboard'][]=[['text'=>"⬅️ Ortga",'callback_data'=>"Hisobim"]];

    bot('editMessageText',['chat_id'=>$cid,'message_id'=>$mid,
        'text'=>$txt,'parse_mode'=>'html','reply_markup'=>json_encode($kb)]);
    exit;
}

// ============================================================
// TO'LOVLAR TARIXI (kassa uchun)
// ============================================================
if(!empty($data) && mb_stripos($data,"kassa_history=")!==false){
    $parts      = explode("=",$data);
    $shop_id_h  = $parts[1];
    $set_id     = $parts[2];
    $page       = (int)($parts[3] ?? 1);
    $per_page   = 10;
    $offset     = ($page-1)*$per_page;
    $shop_id_h_esc = mysqli_real_escape_string($connect,$shop_id_h);

    $total_r = mysqli_fetch_assoc(mysqli_query($connect,
        "SELECT COUNT(*) as c FROM payments p
          LEFT JOIN checkout ch ON ch.`order` = p.used_order
          WHERE COALESCE(p.shop_id, ch.shop_id) = '$shop_id_h_esc'"
    ));
    $total      = (int)($total_r['c'] ?? 0);
    $total_pages= max(1, ceil($total/$per_page));

    // Bitta so'rovda ham ro'yxat, ham umumiy statistika
    $res = mysqli_query($connect,
        "SELECT p.amount, p.date, p.card_type, p.merchant, p.status, p.used_order,
                SUM(CASE WHEN p.card_type='credit' THEN p.amount ELSE 0 END) OVER() as _kirim,
                SUM(CASE WHEN p.card_type='debit'  THEN p.amount ELSE 0 END) OVER() as _chiqim
         FROM payments p
         LEFT JOIN checkout ch ON ch.`order` = p.used_order
         WHERE COALESCE(p.shop_id, ch.shop_id) = '$shop_id_h_esc'
         ORDER BY p.created_at DESC, p.id DESC
         LIMIT $per_page OFFSET $offset"
    );

    if($res && mysqli_num_rows($res)>0){
        $list = ""; $i = $offset+1;
        $kirim_fmt = '0'; $chiqim_fmt = '0'; $first = true;
        while($row=mysqli_fetch_assoc($res)){
            if($first){
                $kirim_fmt  = number_format((int)($row['_kirim'] ?? 0),0,'.',' ');
                $chiqim_fmt = number_format((int)($row['_chiqim'] ?? 0),0,'.',' ');
                $first = false;
            }
            $amt     = number_format((int)$row['amount'],0,'.',' ');
            $dt      = substr($row['date'] ?? '',0,16);
            $merchant= $row['merchant'] ?? '';
            $used    = $row['used_order'] ?? '';
            if($row['card_type']==='credit'){
                $icon='🟢'; $sign="+"; $type_label="Kirim (O'tkazma olindi)";
            } else {
                $icon='🔴'; $sign="-"; $type_label="Chiqim (To'lov)";
            }
            $line = "<b>$i</b>. $icon <b>$sign$amt</b> so'm — $type_label";
            if(!empty($merchant)) $line .= "\n🏪 ".htmlspecialchars($merchant);
            if(!empty($dt))       $line .= "\n📅 $dt";
            if(!empty($used))     $line .= "\n🔗 <code>".htmlspecialchars($used)."</code>";
            $list .= $line."\n\n";
            $i++;
        }

        $header = "💰 <b>To'lovlar tarixi</b> (jami: $total ta)\n";
        $header .= "🟢 Kirim: <b>$kirim_fmt</b> so'm | 🔴 Chiqim: <b>$chiqim_fmt</b> so'm\n\n";

        $nav = [];
        if($page>1)            $nav[]=['text'=>"◀️ Oldingi",'callback_data'=>"kassa_history=$shop_id_h=$set_id=".($page-1)];
        $nav[]=['text'=>"$page / $total_pages",'callback_data'=>"noop"];
        if($page<$total_pages) $nav[]=['text'=>"Keyingi ▶️",'callback_data'=>"kassa_history=$shop_id_h=$set_id=".($page+1)];

        $kb = ['inline_keyboard'=>[]];
        if(count($nav)>1 || $total_pages>1) $kb['inline_keyboard'][] = $nav;
        $kb['inline_keyboard'][]=[['text'=>"⏪ Ortga",'callback_data'=>"kassa_set=$set_id"]];

        bot('editMessageText',['chat_id'=>$cid,'message_id'=>$mid,
            'text'=>$header.$list,'parse_mode'=>'html','reply_markup'=>json_encode($kb)]);
    } else {
        bot('answerCallbackQuery',['callback_query_id'=>$qid,'text'=>"❌ To'lovlar topilmadi!",'show_alert'=>true]);
    }
    exit;
}

// ============================================================
// MUDDATNI UZAYTIRISH
// ============================================================
if(!empty($data) && mb_stripos($data,"kassa_payment=")!==false){
    $parts      = explode("=",$data);
    $shop_id_p  = $parts[1];
    $set_id_p   = $parts[2];
    $rew        = mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM users WHERE user_id='$cid_esc'"));
    $bal        = $rew['balance'];
    $settings_r = mysqli_fetch_assoc(mysqli_query($connect,"SELECT value FROM settings WHERE `key`='month_price'"));
    $month_price= (int)($settings_r['value'] ?? 20000);
    $req        = in_array($cid,$admin) ? 0 : $month_price;

    if($bal >= $req){
        $new_bal = $bal - $req;
        mysqli_query($connect,"UPDATE users SET balance=$new_bal WHERE user_id='$cid_esc'");
        $shop_r   = mysqli_fetch_assoc(mysqli_query($connect,"SELECT over_day FROM shops WHERE id='$set_id_p'"));
        $new_over = ($shop_r['over_day'] ?? 0) + 30;
        mysqli_query($connect,"UPDATE shops SET over_day=$new_over, month_status='Toʻlandi' WHERE id='$set_id_p'");
        bot('editMessageText',['chat_id'=>$cid,'message_id'=>$mid,
            'text'=>"✅ <b>Muddat 30 kunga uzaytirildi!</b>\n\n📆 Qolgan kun: <b>$new_over</b>",
            'parse_mode'=>'html',
            'reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"⏪ Ortga",'callback_data'=>"kassa_set=$set_id_p"]]]])]);
    } else {
        $need_fmt = number_format($req, 0, '.', ' ');
        bot('answerCallbackQuery',['callback_query_id'=>$qid,'text'=>"⚠ Hisobda yetarli mablagʻ yoʻq. Kerak: $need_fmt soʻm",'show_alert'=>true]);
    }
    exit;
}

// ============================================================
// KARTA KIRITISH
// ============================================================
if(!empty($data) && mb_stripos($data,"set_card=")!==false){
    $parts      = explode("=",$data);
    $shop_id_c  = $parts[1];
    $set_id_c   = $parts[2];
    mysqli_query($connect,"UPDATE users SET step='set_card_num=$shop_id_c=$set_id_c' WHERE user_id='$cid_esc'");
    bot('editMessageText',['chat_id'=>$cid,'message_id'=>$mid,
        'text'=>"💳 <b>16 xonali karta raqamini kiriting:</b>\n\nMasalan: 8600 1234 5678 9012",
        'parse_mode'=>'html']);
    exit;
}
if(!empty($step) && mb_stripos($step,"set_card_num=")!==false && !empty($text)){
    $parts     = explode("=",$step);
    $shop_id_c = $parts[1];
    $set_id_c  = $parts[2];
    $card      = preg_replace('/\s+/','',$text);
    if(!preg_match('/^\d{16}$/',$card)){
        bot('sendMessage',['chat_id'=>$cid,'text'=>"❌ <b>16 ta raqam kiriting!</b>",'parse_mode'=>'html']);
        exit;
    }
    // Karta raqami qabul qilindi, endi ism-familya so'raymiz
    mysqli_query($connect,"UPDATE users SET step='set_card_owner=$shop_id_c=$set_id_c=$card' WHERE user_id='$cid_esc'");
    bot('sendMessage',['chat_id'=>$cid,'text'=>"✅ Karta: <code>".chunk_split($card,4,' ')."</code>\n\n👤 <b>Karta egasining ism-familyasini kiriting:</b>\n\n<i>Masalan: ALI VALIYEV</i>",'parse_mode'=>'html','reply_markup'=>$back]);
    exit;
}
if(!empty($step) && mb_stripos($step,"set_card_owner=")!==false && !empty($text)){
    $parts     = explode("=",$step);
    $shop_id_c = $parts[1];
    $set_id_c  = $parts[2];
    $card      = $parts[3];
    if($text=="⏪ Ortga"){
        mysqli_query($connect,"UPDATE users SET step='null' WHERE user_id='$cid_esc'");
        exit;
    }
    $owner = strtoupper(trim($text));
    if(strlen($owner)<3 || strlen($owner)>40){
        bot('sendMessage',['chat_id'=>$cid,'text'=>"❌ <b>Ism-familyani to'g'ri kiriting!</b>",'parse_mode'=>'html']);
        exit;
    }
    $owner_esc = mysqli_real_escape_string($connect,$owner);
    mysqli_query($connect,"UPDATE users SET step='set_card_bank=$shop_id_c=$set_id_c=$card' WHERE user_id='$cid_esc'");
    // Vaqtincha owner ni action ga saqlaymiz
    mysqli_query($connect,"UPDATE users SET action='".base64_encode($owner_esc)."' WHERE user_id='$cid_esc'");
    bot('sendMessage',['chat_id'=>$cid,'text'=>"✅ Egasi: <b>$owner</b>\n\n🏦 <b>Bank turini tanlang:</b>",'parse_mode'=>'html','reply_markup'=>json_encode(['inline_keyboard'=>[
        [['text'=>"🔵 UzCard",'callback_data'=>"card_bank=UzCard=$shop_id_c=$set_id_c=$card"]],
        [['text'=>"🟢 Humo",'callback_data'=>"card_bank=Humo=$shop_id_c=$set_id_c=$card"]],
        [['text'=>"🟡 Visa",'callback_data'=>"card_bank=Visa=$shop_id_c=$set_id_c=$card"]],
        [['text'=>"🔴 MasterCard",'callback_data'=>"card_bank=MasterCard=$shop_id_c=$set_id_c=$card"]],
    ]])]);
    exit;
}
if(!empty($data) && mb_stripos($data,"card_bank=")!==false){
    $parts     = explode("=",$data);
    $bank      = $parts[1];
    $shop_id_c = $parts[2];
    $set_id_c  = $parts[3];
    $card      = $parts[4];
    // Owner ni action dan olamiz
    $user_action = mysqli_fetch_assoc(mysqli_query($connect,"SELECT action FROM users WHERE user_id='$cid_esc'"));
    $owner = !empty($user_action['action']) ? base64_decode($user_action['action']) : '';
    $owner_esc = mysqli_real_escape_string($connect,$owner);
    mysqli_query($connect,"UPDATE shops SET card_number='$card', card_bank='$bank', card_owner='$owner_esc' WHERE shop_id='$shop_id_c'");
    mysqli_query($connect,"UPDATE users SET step='null', action='member' WHERE user_id='$cid_esc'");
    $masked = chunk_split($card,4,' ');
    bot('editMessageText',['chat_id'=>$cid,'message_id'=>$mid,
        'text'=>"✅ <b>Karta saqlandi!</b>\n\n💳 <code>$masked</code>\n👤 Egasi: <b>$owner</b>\n🏦 Bank: <b>$bank</b>",
        'parse_mode'=>'html',
        'reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"⏪ Ortga",'callback_data'=>"kassa_sozlama=$shop_id_c=$set_id_c"]]]])]);
    exit;
}

// ============================================================
// KEY YANGILASH
// ============================================================
if(!empty($data) && mb_stripos($data,"new_key=")!==false){
    $id = explode("=",$data)[1];
    $k  = generate();
    mysqli_query($connect,"UPDATE shops SET shop_key='$k' WHERE shop_id='$id'");
    bot('editMessageText',['chat_id'=>$cid,'message_id'=>$mid,'text'=>"✅ <b>Kalit yangilandi!</b> <code>$k</code>",'parse_mode'=>'HTML']);
    exit;
}

// ============================================================
// KASSA ULASH (foydalanuvchi)
// ============================================================
if(!empty($data) && mb_stripos($data,"req_connect=")!==false){
    $parts      = explode("=",$data);
    $shop_id_r  = $parts[1];
    bot('answerCallbackQuery',['callback_query_id'=>$qid]);
    bot('sendMessage',['chat_id'=>$cid,
        'text'=>"📨 <b>Kassa ulash uchun Administratorga murojaat qiling</b>\n\n".
            "<code>$shop_id_r kassa uchun e-mail</code>\n".
            "deb yozing, va sizga tushuntiriladi hamda ulab beriladi.\n\n".
            "👨‍💻 Admin: @xmtvv1",
        'parse_mode'=>'html']);
    exit;
}

// ============================================================
// ADMIN — KASSA ULASH
// ============================================================
if(!empty($text) && $text=="🔗 Kassa ulash" && in_array($cid,$admin)){
    mysqli_query($connect,"UPDATE users SET step='admin_connect_shop' WHERE user_id='$cid_esc'");
    bot('sendMessage',['chat_id'=>$cid,
        'text'=>"🏪 <b>Kassani ulash uchun Shop ID kiriting:</b>",
        'parse_mode'=>'html',
        'reply_markup'=>json_encode(['resize_keyboard'=>true,'keyboard'=>[[['text'=>"🗄️ Boshqaruv"]]]])]);
    exit;
}
if($step=="admin_connect_shop" && in_array($cid,$admin) && !empty($text)){
    if($text=="🗄️ Boshqaruv"){ mysqli_query($connect,"UPDATE users SET step='null' WHERE user_id='$cid_esc'"); exit; }
    $shop_id_inp = mysqli_real_escape_string($connect,trim($text));
    $shop_r      = mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM shops WHERE shop_id='$shop_id_inp'"));
    if(!$shop_r){
        bot('sendMessage',['chat_id'=>$cid,'text'=>"❌ Bu Shop ID topilmadi!",'reply_markup'=>$panel]);
        mysqli_query($connect,"UPDATE users SET step='null' WHERE user_id='$cid_esc'"); exit;
    }
    $nomi     = base64_decode($shop_r['shop_name']);
    $status_r = $shop_r['status'];
    $phone_r  = $shop_r['phone'] ?? null;
    $month_r  = $shop_r['month_status'] ?? "Toʻlanmagan";
    $owner_id = $shop_r['user_id'];
    mysqli_query($connect,"UPDATE users SET step='null' WHERE user_id='$cid_esc'");
    bot('sendMessage',['chat_id'=>$cid,
        'text'=>"🏪 <b>$nomi</b>\n\n🆔 Shop ID: <code>$shop_id_inp</code>\n👤 Egasi ID: <code>$owner_id</code>\n📊 Holat: <b>$status_r</b>\n💳 Oylik to'lov: <b>$month_r</b>\n📞 Raqam: ".($phone_r?"<code>$phone_r</code>":"Kiritilmagan"),
        'parse_mode'=>'html',
        'reply_markup'=>json_encode(['inline_keyboard'=>[
            [['text'=>"✅ Kassani faollashtirish",'callback_data'=>"activate_kassa=$shop_id_inp"]],
            [['text'=>"⏪ Ortga",'callback_data'=>"panel_back"]],
        ]])]);
    exit;
}

if(!empty($data) && $data=="panel_back" && in_array($cid,$admin)){
    bot('editMessageText',['chat_id'=>$cid,'message_id'=>$mid,'text'=>"<b>Administrator paneli!</b>",'parse_mode'=>'html','reply_markup'=>json_encode(['inline_keyboard'=>[]])]);
    bot('sendMessage',['chat_id'=>$cid,'text'=>"<b>Administrator paneli!</b>",'parse_mode'=>'html','reply_markup'=>$panel]);
    exit;
}

if(!empty($data) && mb_stripos($data,"activate_kassa=")!==false && in_array($cid,$admin)){
    $shop_id_ak = explode("=",$data)[1];
    $shop_r     = mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM shops WHERE shop_id='$shop_id_ak'"));
    $owner_id   = $shop_r['user_id'];
    bot('editMessageText',['chat_id'=>$cid,'message_id'=>$mid,
        'text'=>"📞 <b>Telefon raqamini kiriting:</b>\n\nMasalan: 998901234567",'parse_mode'=>'html']);
    mysqli_query($connect,"UPDATE users SET step='activate_phone=$shop_id_ak=$owner_id' WHERE user_id='$cid_esc'");
    exit;
}

if(!empty($step) && mb_stripos($step,"activate_phone=")!==false && in_array($cid,$admin) && !empty($text)){
    $parts      = explode("=",$step);
    $shop_id_ak = $parts[1];
    $owner_id   = $parts[2];
    $phone_num  = preg_replace('/[^0-9]/','',trim($text));
    if(strlen($phone_num)<9){ bot('sendMessage',['chat_id'=>$cid,'text'=>"❌ Noto'g'ri raqam! Qayta kiriting.",'parse_mode'=>'html']); exit; }
    mysqli_query($connect,"UPDATE users SET step='activate_email=$shop_id_ak=$owner_id=$phone_num' WHERE user_id='$cid_esc'");
    bot('sendMessage',['chat_id'=>$cid,'text'=>"📧 <b>Foydalanuvchi emailini kiriting:</b>",'parse_mode'=>'html']);
    exit;
}

if(!empty($step) && mb_stripos($step,"activate_email=")!==false && in_array($cid,$admin) && !empty($text)){
    $parts      = explode("=",$step);
    $shop_id_ak = $parts[1];
    $owner_id   = $parts[2];
    $phone_num  = $parts[3];
    $email_inp  = trim($text);
    if(!filter_var($email_inp,FILTER_VALIDATE_EMAIL)){
        bot('sendMessage',['chat_id'=>$cid,'text'=>"❌ Noto'g'ri email! Qayta kiriting.",'parse_mode'=>'html']); exit;
    }
    mysqli_query($connect,"UPDATE shops SET phone='$phone_num', email='".mysqli_real_escape_string($connect,$email_inp)."', month_status='Toʻlandi', over_day=30 WHERE shop_id='$shop_id_ak'");
    mysqli_query($connect,"UPDATE users SET step='null' WHERE user_id='$cid_esc'");
    $shop_r = mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM shops WHERE shop_id='$shop_id_ak'"));
    $nomi   = base64_decode($shop_r['shop_name']);
    bot('sendMessage',['chat_id'=>$cid,
        'text'=>"✅ <b>Kassa to'liq faollashdi!</b>\n\n🏪 Kassa: <b>$nomi</b>\n📞 Raqam: <code>$phone_num</code>\n📧 Email: <code>$email_inp</code>",
        'parse_mode'=>'html','reply_markup'=>$panel]);
    bot('sendMessage',['chat_id'=>$owner_id,
        'text'=>"✅ <b>Kassangiz to'liq faollashdi!</b>\n\n🏪 <b>$nomi</b>\nEndi to'lovlar avtomatik qabul qilinadi.",
        'parse_mode'=>'html']);
    exit;
}

// ============================================================
// KASSA QO'SHISH
// ============================================================
if(!empty($data) && $data=="add_kassa"){
    bot('deletemessage',['chat_id'=>$cid,'message_id'=>$mid]);
    bot('sendmessage',['chat_id'=>$cid,'text'=>"🛍️ <b>Yangi kassa qoʻshish!</b>\n\nKassa nomini yuboring:",'parse_mode'=>'html','reply_markup'=>$back]);
    mysqli_query($connect,"UPDATE users SET step='add_kassa' WHERE user_id='$cid_esc'");
    exit;
}
if($step=="add_kassa" && !empty($text)){
    if($text=="⏪ Ortga"){
        mysqli_query($connect,"UPDATE users SET step='null' WHERE user_id='$cid_esc'");
    } else {
        if(mysqli_num_rows(mysqli_query($connect,"SELECT * FROM shops WHERE shop_name='".base64_encode($text)."'"))>0){
            bot('sendMessage',['chat_id'=>$cid,'text'=>"⚠️ Bu nom bilan kassa mavjud!",'parse_mode'=>'html']); exit;
        }
        bot('sendmessage',['chat_id'=>$cid,'text'=>"✅ Nom qabul qilindi!\n\nKassa havolasini kiriting:\n<i>Masalan: @username yoki tolovavto.up.railway.app</i>",'parse_mode'=>'html','reply_markup'=>$back]);
        mysqli_query($connect,"UPDATE users SET step='add_kassa_address-".base64_encode($text)."' WHERE user_id='$cid_esc'");
        exit;
    }
}
if(!empty($step) && mb_stripos($step,"add_kassa_address-")!==false && !empty($text)){
    $name = explode("-",$step,2)[1];
    if($text=="⏪ Ortga"){
        mysqli_query($connect,"UPDATE users SET step='null' WHERE user_id='$cid_esc'");
    } else {
        if(preg_match('/^@[\w]{5,25}$|^[a-z0-9-]+\.[a-z]{2,}$/i',$text)){
            bot('sendmessage',['chat_id'=>$cid,'text'=>"✅ Manzil qabul qilindi!\n\nKassa haqida ma'lumot kiriting:",'parse_mode'=>'html','reply_markup'=>$back]);
            mysqli_query($connect,"UPDATE users SET step='add_kassa_info-$name-$text' WHERE user_id='$cid_esc'");
        } else {
            bot('sendMessage',['chat_id'=>$cid,'text'=>"⚠️ Noto'g'ri format!\n\n@username yoki domen kiriting",'parse_mode'=>'html']); exit;
        }
    }
}
if(!empty($step) && mb_stripos($step,"add_kassa_info-")!==false && !empty($text)){
    $parts2  = explode("-",$step,3);
    $name    = $parts2[1];
    $address = $parts2[2];
    if($text=="⏪ Ortga"){
        mysqli_query($connect,"UPDATE users SET step='null' WHERE user_id='$cid_esc'");
    } else {
        $sid  = rand(111111,999999);
        $skey = generate();
        mysqli_query($connect,"INSERT INTO shops (user_id,shop_name,shop_info,shop_id,shop_key,shop_address,shop_balance,status,date) VALUES('$cid_esc','$name','".base64_encode($text)."','$sid','$skey','$address','0','waiting','$sana')");
        bot('sendmessage',['chat_id'=>$cid,'text'=>"✅ <b>Adminga yuborildi! Kuting.</b>",'parse_mode'=>'html','reply_markup'=>$m]);
        mysqli_query($connect,"UPDATE users SET step='null' WHERE user_id='$cid_esc'");
        bot('sendmessage',['chat_id'=>$administrator,
            'text'=>"✅ <b>Yangi kassa!</b>\n\n🆔 ID: $sid\n🔑 Key: $skey\n🛍️ Nom: <b>".base64_decode($name)."</b>\n🔗 Manzil: $address\n📖 Haqida: <b>$text</b>\n\n📅 $sana ⏰ $soat\n👤 User: <code>$cid</code>",
            'parse_mode'=>'html',
            'reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"✅ Tasdiqlash",'callback_data'=>"confirm=$sid"]],[['text'=>"⛔ Bekor qilish",'callback_data'=>"canceled=$sid"]]]])]);
        exit;
    }
}

if(!empty($data) && mb_stripos($data,"confirm=")!==false){
    $id  = explode("=",$data)[1];
    $rew = mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM shops WHERE shop_id='$id'"));
    bot('editMessageText',['chat_id'=>$cid,'message_id'=>$mid,'text'=>"✅ <b>Tasdiqlandi! #$id</b>",'parse_mode'=>'html']);
    bot('sendmessage',['chat_id'=>$rew['user_id'],'text'=>"✅ <b>Kassangiz tasdiqlandi! #$id</b>\n\nEndi sozlamalardan karta va email kiriting.",'parse_mode'=>'html','reply_markup'=>$m]);
    mysqli_query($connect,"UPDATE shops SET status='confirm' WHERE shop_id='$id'");
    exit;
}
if(!empty($data) && mb_stripos($data,"canceled=")!==false){
    $id  = explode("=",$data)[1];
    $rew = mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM shops WHERE shop_id='$id'"));
    bot('editMessageText',['chat_id'=>$cid,'message_id'=>$mid,'text'=>"⛔ <b>Bekor qilindi! #$id</b>",'parse_mode'=>'html']);
    bot('sendmessage',['chat_id'=>$rew['user_id'],'text'=>"⛔ <b>Kassangiz bekor qilindi! #$id</b>",'parse_mode'=>'html','reply_markup'=>$m]);
    mysqli_query($connect,"UPDATE shops SET status='canceled' WHERE shop_id='$id'");
    exit;
}

// ============================================================
// ADMIN PANEL
// ============================================================
if(!empty($text) && ($text=="🗄️ Boshqaruv" || $text=="/panel") && in_array($cid,$admin)){
    mysqli_query($connect,"UPDATE users SET step='null' WHERE user_id='$cid_esc'");
    bot('sendMessage',['chat_id'=>$cid,'text'=>"<b>Administrator paneli!</b>",'parse_mode'=>'html','reply_markup'=>$panel]);
    exit;
}

// ============================================================
// OYLIK NARH
// ============================================================
if(!empty($text) && $text=="💰 Oylik narh" && in_array($cid,$admin)){
    $cur       = mysqli_fetch_assoc(mysqli_query($connect,"SELECT value FROM settings WHERE `key`='month_price'"));
    $cur_price = number_format((int)($cur['value'] ?? 20000),0,'.',' ');
    bot('sendMessage',['chat_id'=>$cid,
        'text'=>"💰 <b>Oylik to'lov narhi</b>\n\nHozirgi narh: <b>$cur_price</b> so'm\n\nYangi narh (so'mda) yuboring:",
        'parse_mode'=>'html','reply_markup'=>$panel]);
    mysqli_query($connect,"UPDATE users SET step='set_month_price' WHERE user_id='$cid_esc'");
    exit;
}
if($step=="set_month_price" && in_array($cid,$admin) && !empty($text)){
    $new_price = preg_replace('/[^0-9]/','',trim($text));
    if(!$new_price || $new_price < 1000){
        bot('sendMessage',['chat_id'=>$cid,'text'=>"❌ Noto'g'ri narh! Minimal 1000 so'm.",'parse_mode'=>'html','reply_markup'=>$panel]);
        exit;
    }
    $exists = mysqli_num_rows(mysqli_query($connect,"SELECT id FROM settings WHERE `key`='month_price'"));
    if($exists>0){
        mysqli_query($connect,"UPDATE settings SET value='$new_price' WHERE `key`='month_price'");
    } else {
        mysqli_query($connect,"INSERT INTO settings (`key`,value) VALUES('month_price','$new_price')");
    }
    mysqli_query($connect,"UPDATE users SET step='null' WHERE user_id='$cid_esc'");
    $fmt = number_format((int)$new_price,0,'.',' ');
    bot('sendMessage',['chat_id'=>$cid,
        'text'=>"✅ <b>Oylik narh yangilandi!</b>\n\n💰 Yangi narh: <b>$fmt</b> so'm",
        'parse_mode'=>'html','reply_markup'=>$panel]);
    exit;
}

// ============================================================
// STATISTIKA (admin)
// ============================================================
if(!empty($text) && $text=="📊 Statistika" && in_array($cid,$admin)){
    $s_row = mysqli_fetch_assoc(mysqli_query($connect,
        "SELECT COUNT(*) as jami, SUM(date='$sana') as bugun FROM users"
    ));
    $stat  = $s_row['jami'];
    $bugun = $s_row['bugun'] ?? 0;
    $textt = "";
    $s5    = mysqli_query($connect,"SELECT user_id,id FROM users ORDER BY id DESC LIMIT 5");
    while($u=mysqli_fetch_assoc($s5)) $textt .= "👤 <a href='tg://user?id=".$u['user_id']."'>".$u['user_id']."</a>\n";
    bot('sendMessage',['chat_id'=>$cid,'text'=>"📊 <b>Statistika</b>\n\n▫️ Jami: <b>$stat</b> ta\n▪️ Bugun: <b>$bugun</b> ta\n\nOxirgi 5ta:\n$textt",'parse_mode'=>"html",'reply_markup'=>$panel]);
    exit;
}

// ============================================================
// FOYDALANUVCHI (admin)
// ============================================================
if(!empty($text) && $text=="👤 Foydalanuvchi" && in_array($cid,$admin)){
    mysqli_query($connect,"UPDATE users SET step='user_check' WHERE user_id='$cid_esc'");
    bot('sendMessage',['chat_id'=>$cid,'text'=>"<i>Foydalanuvchi ID kiriting:</i>",'parse_mode'=>'html',
        'reply_markup'=>json_encode(['resize_keyboard'=>true,'keyboard'=>[[['text'=>"🗄️ Boshqaruv"]]]])]);
    exit;
}
if($step=="user_check" && in_array($cid,$admin) && !empty($text)){
    if($text=="🗄️ Boshqaruv"){ mysqli_query($connect,"UPDATE users SET step='null' WHERE user_id='$cid_esc'"); exit; }
    mysqli_query($connect,"UPDATE users SET step='null' WHERE user_id='$cid_esc'");
    $text_esc = mysqli_real_escape_string($connect,$text);
    $ch = mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM users WHERE user_id='$text_esc'"));
    if(!$ch) $ch = mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM users WHERE id='$text_esc'"));
    if($ch){
        bot('sendMessage',['chat_id'=>$cid,'text'=>"<b>📑 Foydalanuvchi!</b>\n\nID: <code>".$ch['user_id']."</code>\nBalans: <b>".$ch['balance']."</b> soʻm",'parse_mode'=>"HTML",
            'reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"➕ Pul qoʻshish",'callback_data'=>"pul=plus=".$ch['user_id']],['text'=>"➖ Pul ayirish",'callback_data'=>"pul=minus=".$ch['user_id']]]]])]);
    } else {
        bot('sendMessage',['chat_id'=>$cid,'text'=>"<b>🥲 Topilmadi!</b>",'parse_mode'=>"HTML",'reply_markup'=>$panel]);
    }
    exit;
}
if(!empty($data) && mb_stripos($data,"pul=")!==false && in_array($cid,$admin)){
    $type = explode("=",$data)[1];
    $id   = explode("=",$data)[2];
    mysqli_query($connect,"UPDATE users SET step='pul=$type=$id' WHERE user_id='$cid_esc'");
    bot('sendMessage',['chat_id'=>$cid,'text'=>"<b>Miqdorni kiriting:</b>",'parse_mode'=>'html',
        'reply_markup'=>json_encode(['resize_keyboard'=>true,'keyboard'=>[[['text'=>"🗄️ Boshqaruv"]]]])]);
    exit;
}
if(!empty($step) && mb_stripos($step,"pul=")!==false && in_array($cid,$admin) && !empty($text)){
    $type = explode("=",$step)[1];
    $id   = explode("=",$step)[2];
    mysqli_query($connect,"UPDATE users SET step='null' WHERE user_id='$cid_esc'");
    $id_esc = mysqli_real_escape_string($connect,$id);
    $ch     = mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM users WHERE user_id='$id_esc'"));
    $amount = (int)preg_replace('/[^0-9]/','',trim($text));
    $c      = ($type=="plus") ? $ch['balance']+$amount : $ch['balance']-$amount;
    mysqli_query($connect,"UPDATE users SET balance='$c' WHERE user_id='$id_esc'");
    bot('sendMessage',['chat_id'=>$cid,'text'=>($type=="plus"?"✔️":"⚠️")." <b>$amount soʻm ".($type=="plus"?"qoʻshildi":"ayirildi")."!</b>",'parse_mode'=>'html','reply_markup'=>$panel]);
    exit;
}

// ============================================================
// XABAR YUBORISH (admin broadcast)
// ============================================================
if(!empty($text) && $text=="📨 Xabar yuborish" && in_array($cid,$admin)){
    $send_row = mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM `send` LIMIT 1"));
    if(!$send_row){
        bot('SendMessage',['chat_id'=>$cid,'text'=>"<b>📨 Xabarni kiriting:</b>\n\nXabarni yuboring:",'parse_mode'=>'html',
            'reply_markup'=>json_encode(['resize_keyboard'=>true,'keyboard'=>[[['text'=>"🗄️ Boshqaruv"]]]])]);
        mysqli_query($connect,"UPDATE users SET step='send' WHERE user_id='$cid_esc'");
    } else {
        $t_start = $send_row['time1'] ?? '—';
        bot('sendMessage',['chat_id'=>$cid,
            'text'=>"📨 <b>Yuborish davom etmoqda!</b>\n\n🕐 Vaqt: <b>$t_start</b>\n\nBekor qilish:",
            'parse_mode'=>'html',
            'reply_markup'=>json_encode(['inline_keyboard'=>[
                [['text'=>"🗑️ Yuborishni bekor qilish",'callback_data'=>"cancel_broadcast"]],
            ]])]);
    }
    exit;
}
if(!empty($data) && $data=="cancel_broadcast" && in_array($cid,$admin)){
    mysqli_query($connect,"DELETE FROM `send`");
    bot('answerCallbackQuery',['callback_query_id'=>$qid,'text'=>"✅ Yuborish bekor qilindi"]);
    bot('editMessageText',['chat_id'=>$cid,'message_id'=>$mid,'text'=>"✅ <b>Yuborish bekor qilindi!</b>",'parse_mode'=>'html','reply_markup'=>json_encode(['inline_keyboard'=>[]])]);
    exit;
}
if($step=="send" && in_array($cid,$admin) && isset($message)){
    $lu  = mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM users ORDER BY id DESC LIMIT 1"));
    $t1  = date('H:i',strtotime('+1 minutes'));
    $t2  = date('H:i',strtotime('+2 minutes'));
    $rm  = base64_encode(json_encode($message->reply_markup ?? null));
    $fwd_from_chat = $message->forward_from_chat->id ?? null;
    $fwd_msg_id    = $message->forward_from_message_id ?? null;
    $use_forward   = ($fwd_from_chat && $fwd_msg_id) ? 1 : 0;
    mysqli_query($connect,"INSERT INTO `send` (time1,time2,start_id,stop_id,admin_id,message_id,reply_markup,step) VALUES('$t1','$t2','0','".$lu['user_id']."','$administrator','$mid','$rm','send')");
    bot('sendMessage',['chat_id'=>$cid,'text'=>"✅ <b>$t1 da yuboriladi!</b>\n\n👥 Jami foydalanuvchilar: <b>".$lu['id']."</b> ta",'parse_mode'=>'html','reply_markup'=>$panel]);
    mysqli_query($connect,"UPDATE users SET step='null' WHERE user_id='$cid_esc'");
    exit;
}

// ============================================================
// KANALLAR (admin)
// ============================================================
if(!empty($text) && $text=="📢 Kanallar" && in_array($cid,$admin)){
    bot('sendMessage',['chat_id'=>$cid,'text'=>"<b>Kanal turini tanlang:</b>",'parse_mode'=>'html',
        'reply_markup'=>json_encode(['inline_keyboard'=>[
            [['text'=>"Ommaviy",'callback_data'=>"request-false"]],
            [['text'=>"So'rov qabul qiluvchi",'callback_data'=>"request-true"]],
            [['text'=>"Ixtiyoriy havola",'callback_data'=>"socialnetwork"]],
        ]])]);
    exit;
}
if(!empty($data) && $data=="socialnetwork"){
    bot('deleteMessage',['chat_id'=>$cid,'message_id'=>$mid]);
    bot('sendMessage',['chat_id'=>$cid,'text'=>"<b>Havola uchun nom:</b>",'parse_mode'=>'html',
        'reply_markup'=>json_encode(['resize_keyboard'=>true,'keyboard'=>[[['text'=>"🗄️ Boshqaruv"]]]])]);
    mysqli_query($connect,"UPDATE users SET step='socialnetwork_step1' WHERE user_id='$cid_esc'");
    exit;
}
if($step=="socialnetwork_step1" && !empty($text)){
    mysqli_query($connect,"UPDATE users SET step='socialnetwork_step2', action='".mysqli_real_escape_string($connect,$text)."' WHERE user_id='$cid_esc'");
    bot('sendMessage',['chat_id'=>$cid,'text'=>"✅ Qabul!\n\nHavolani kiriting:",'parse_mode'=>'html']);
    exit;
}
if($step=="socialnetwork_step2" && !empty($text)){
    $nr = mysqli_fetch_assoc(mysqli_query($connect,"SELECT action FROM users WHERE user_id='$cid_esc'"));
    mysqli_query($connect,"INSERT INTO `channels` (type,link,title,channelID) VALUES('social','".mysqli_real_escape_string($connect,$text)."','".base64_encode($nr['action'])."','')");
    bot('sendMessage',['chat_id'=>$cid,'text'=>"✅ <b>Kanal qo'shildi!</b>",'parse_mode'=>'html','reply_markup'=>$panel]);
    mysqli_query($connect,"UPDATE users SET step='null' WHERE user_id='$cid_esc'");
    exit;
}
if(!empty($data) && mb_stripos($data,"request-")!==false){
    $type = explode("-",$data)[1];
    mysqli_query($connect,"UPDATE users SET step='qosh', action='$type' WHERE user_id='$cid_esc'");
    bot('sendMessage',['chat_id'=>$cid,'text'=>"<b>Kanaldan \"forward\" xabar yuboring:</b>",'parse_mode'=>'html',
        'reply_markup'=>json_encode(['resize_keyboard'=>true,'keyboard'=>[[['text'=>"🗄️ Boshqaruv"]]]])]);
    exit;
}
if($step=="qosh" && isset($message->forward_origin)){
    $kid = $message->forward_origin->chat->id;
    $tr  = mysqli_fetch_assoc(mysqli_query($connect,"SELECT action FROM users WHERE user_id='$cid_esc'"));
    if($tr['action']=="true"){
        $lnk = bot('createChatInviteLink',['chat_id'=>$kid,'creates_join_request'=>true])->result->invite_link;
        $sq  = "INSERT INTO `channels` (channelID,link,type) VALUES('$kid','$lnk','request')";
    } else {
        $lnk = "https://t.me/".$message->forward_origin->chat->username;
        $sq  = "INSERT INTO `channels` (channelID,link,type) VALUES('$kid','$lnk','lock')";
    }
    $connect->query($sq);
    bot('sendMessage',['chat_id'=>$cid,'text'=>"✅ <b>Kanal qo'shildi!</b>",'parse_mode'=>'html','reply_markup'=>$panel]);
    mysqli_query($connect,"UPDATE users SET step='null' WHERE user_id='$cid_esc'");
    exit;
}

// ============================================================
// KANAL O'CHIRISH (admin)
// ============================================================
if(!empty($text) && $text=="🗑️ Kanal o'chirish" && in_array($cid,$admin)){
    $r = $connect->query("SELECT * FROM `channels`");
    if($r->num_rows>0){
        $btn = [];
        while($row=$r->fetch_assoc()){
            $gt = ($row['type']=="lock"||$row['type']=="request")
                ? bot('getchat',['chat_id'=>$row['channelID']])->result->title
                : base64_decode($row['title']);
            $btn[]=['text'=>"🗑️ $gt",'callback_data'=>"delchan=".$row['channelID']];
        }
        bot('sendMessage',['chat_id'=>$cid,'text'=>"<b>O'chirish uchun tanlang:</b>",'parse_mode'=>'html',
            'reply_markup'=>json_encode(['inline_keyboard'=>array_chunk($btn,1)])]);
    } else {
        bot('sendMessage',['chat_id'=>$cid,'text'=>"<b>Kanal yo'q!</b>",'parse_mode'=>'html']);
    }
    exit;
}
if(!empty($data) && stripos($data,"delchan=")!==false){
    $ex = explode("=",$data)[1];
    $connect->query("DELETE FROM channels WHERE channelID='$ex'");
    bot('editMessageText',['chat_id'=>$cid,'message_id'=>$mid,'text'=>"✅ <b>Kanal oʻchirildi!</b>",'parse_mode'=>'html']);
    exit;
}

// ============================================================
// ADMIN: KASSA BOSHQARUV
// ============================================================
if(!empty($text) && $text=="🏪 Kassa boshqaruv" && in_array($cid,$admin)){
    mysqli_query($connect,"UPDATE users SET step='admin_kassa_manage' WHERE user_id='$cid_esc'");
    bot('sendMessage',['chat_id'=>$cid,
        'text'=>"🏪 <b>Kassa boshqaruv</b>\n\nShop ID kiriting:",
        'parse_mode'=>'html',
        'reply_markup'=>json_encode(['resize_keyboard'=>true,'keyboard'=>[[['text'=>"🗄️ Boshqaruv"]]]])]);
    exit;
}
if($step=="admin_kassa_manage" && in_array($cid,$admin) && !empty($text)){
    if($text=="🗄️ Boshqaruv"){ mysqli_query($connect,"UPDATE users SET step='null' WHERE user_id='$cid_esc'"); exit; }
    $sid_esc = mysqli_real_escape_string($connect,trim($text));
    $shop_r  = mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM shops WHERE shop_id='$sid_esc'"));
    if(!$shop_r){
        bot('sendMessage',['chat_id'=>$cid,'text'=>"❌ Bu Shop ID topilmadi!"]);
        exit;
    }
    $nomi    = base64_decode($shop_r['shop_name']);
    $month_s = $shop_r['month_status'] ?? 'Toʻlanmagan';
    $over_d  = (int)($shop_r['over_day'] ?? 0);
    $status_s= $shop_r['status'];
    mysqli_query($connect,"UPDATE users SET step='null', action='manage_$sid_esc' WHERE user_id='$cid_esc'");

    $btn = [];
    if($month_s==='Toʻlandi'){
        $btn[]=[['text'=>"📆 Muddatni uzaytirish",'callback_data'=>"admin_extend=$sid_esc"]];
    } else {
        $btn[]=[['text'=>"✅ Oylik to'lovni yoqish",'callback_data'=>"admin_activate_pay=$sid_esc"]];
    }
    $btn[]=[['text'=>"⏪ Ortga",'callback_data'=>"noop"]];

    bot('sendMessage',['chat_id'=>$cid,
        'text'=>"🏪 <b>$nomi</b>\n\n🆔 Shop ID: <code>$sid_esc</code>\n📊 Holat: <b>$status_s</b>\n💳 Oylik to'lov: <b>$month_s</b>\n📆 Qolgan kun: <b>$over_d</b>",
        'parse_mode'=>'html',
        'reply_markup'=>json_encode(['inline_keyboard'=>$btn])]);
    exit;
}

if(!empty($data) && mb_stripos($data,"admin_activate_pay=")!==false && in_array($cid,$admin)){
    $sid_esc = explode("=",$data)[1];
    mysqli_query($connect,"UPDATE users SET step='admin_set_days=$sid_esc=activate' WHERE user_id='$cid_esc'");
    bot('editMessageText',['chat_id'=>$cid,'message_id'=>$mid,
        'text'=>"📆 <b>Necha kunga yoqish?</b>\n\nMasalan: 30 (1 oy), 90 (3 oy)\n\nKunlar sonini kiriting:",
        'parse_mode'=>'html']);
    exit;
}
if(!empty($data) && mb_stripos($data,"admin_extend=")!==false && in_array($cid,$admin)){
    $sid_esc = explode("=",$data)[1];
    mysqli_query($connect,"UPDATE users SET step='admin_set_days=$sid_esc=extend' WHERE user_id='$cid_esc'");
    bot('editMessageText',['chat_id'=>$cid,'message_id'=>$mid,
        'text'=>"📆 <b>Necha kunga uzaytirish?</b>\n\nMasalan: 30 (1 oy), 90 (3 oy)\n\nKunlar sonini kiriting:",
        'parse_mode'=>'html']);
    exit;
}
if(!empty($step) && mb_stripos($step,"admin_set_days=")!==false && in_array($cid,$admin) && !empty($text)){
    $parts_s     = explode("=",$step);
    $sid_esc     = $parts_s[1];
    $action_type = $parts_s[2];
    $days_inp    = preg_replace('/[^0-9]/','',trim($text));
    if(!$days_inp || $days_inp<1){
        bot('sendMessage',['chat_id'=>$cid,'text'=>"❌ Noto'g'ri son! Qayta kiriting."]);
        exit;
    }
    $shop_r   = mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM shops WHERE shop_id='$sid_esc'"));
    $old_over = (int)($shop_r['over_day'] ?? 0);
    $new_over = $old_over + (int)$days_inp;

    mysqli_query($connect,"UPDATE shops SET over_day='$new_over', month_status='Toʻlandi' WHERE shop_id='$sid_esc'");
    mysqli_query($connect,"UPDATE users SET step='null' WHERE user_id='$cid_esc'");

    $nomi       = base64_decode($shop_r['shop_name']);
    $owner_id   = $shop_r['user_id'];
    $action_txt = ($action_type==='activate') ? 'Yoqildi' : 'Uzaytirildi';

    bot('sendMessage',['chat_id'=>$cid,
        'text'=>"✅ <b>$action_txt!</b>\n\n🏪 <b>$nomi</b>\n🆔 Shop ID: <code>$sid_esc</code>\n➕ Qo'shildi: <b>$days_inp kun</b>\n📆 Jami qolgan: <b>$new_over kun</b>",
        'parse_mode'=>'html','reply_markup'=>$panel]);
    bot('sendMessage',['chat_id'=>$owner_id,
        'text'=>"✅ <b>Kassangiz muddati uzaytirildi!</b>\n\n🏪 <b>$nomi</b>\n📆 Qolgan kun: <b>$new_over</b>",
        'parse_mode'=>'html']);
    exit;
}

// ============================================================
// ADMIN: KASSA BAN
// ============================================================
if(!empty($text) && $text=="🚫 Kassa ban" && in_array($cid,$admin)){
    mysqli_query($connect,"UPDATE users SET step='admin_ban_shop' WHERE user_id='$cid_esc'");
    bot('sendMessage',['chat_id'=>$cid,
        'text'=>"🚫 <b>Kassa ban</b>\n\nBan qilish uchun Shop ID kiriting:",
        'parse_mode'=>'html',
        'reply_markup'=>json_encode(['resize_keyboard'=>true,'keyboard'=>[[['text'=>"🗄️ Boshqaruv"]]]])]);
    exit;
}
if($step=="admin_ban_shop" && in_array($cid,$admin) && !empty($text)){
    if($text=="🗄️ Boshqaruv"){ mysqli_query($connect,"UPDATE users SET step='null' WHERE user_id='$cid_esc'"); exit; }
    $sid_esc   = mysqli_real_escape_string($connect,trim($text));
    $shop_r    = mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM shops WHERE shop_id='$sid_esc'"));
    if(!$shop_r){
        bot('sendMessage',['chat_id'=>$cid,'text'=>"❌ Bu Shop ID topilmadi!"]);
        exit;
    }
    $nomi      = base64_decode($shop_r['shop_name']);
    $status_now= $shop_r['status'];
    mysqli_query($connect,"UPDATE users SET step='null' WHERE user_id='$cid_esc'");

    $is_banned  = ($status_now==='banned');
    $btn_text   = $is_banned ? "✅ Banni olib tashlash" : "🚫 Kassani ban qilish";
    $btn_data   = $is_banned ? "admin_unban=$sid_esc" : "admin_ban=$sid_esc";
    $status_icon= $is_banned ? "🚫 Banned!" : "✅ Faol";

    bot('sendMessage',['chat_id'=>$cid,
        'text'=>"🏪 <b>$nomi</b>\n\n🆔 Shop ID: <code>$sid_esc</code>\n📊 Holat: <b>$status_icon</b>",
        'parse_mode'=>'html',
        'reply_markup'=>json_encode(['inline_keyboard'=>[
            [['text'=>$btn_text,'callback_data'=>$btn_data]],
            [['text'=>"⏪ Ortga",'callback_data'=>"noop"]],
        ]])]);
    exit;
}
if(!empty($data) && mb_stripos($data,"admin_ban=")!==false && in_array($cid,$admin)){
    $sid_esc  = explode("=",$data)[1];
    $shop_r   = mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM shops WHERE shop_id='$sid_esc'"));
    $nomi     = base64_decode($shop_r['shop_name']);
    $owner_id = $shop_r['user_id'];
    mysqli_query($connect,"UPDATE shops SET status='banned' WHERE shop_id='$sid_esc'");
    bot('answerCallbackQuery',['callback_query_id'=>$qid,'text'=>"✅ Kassa ban qilindi"]);
    bot('editMessageText',['chat_id'=>$cid,'message_id'=>$mid,
        'text'=>"🚫 <b>Kassa ban qilindi!</b>\n\n🏪 <b>$nomi</b>\n🆔 <code>$sid_esc</code>",
        'parse_mode'=>'html','reply_markup'=>json_encode(['inline_keyboard'=>[
            [['text'=>"✅ Banni olib tashlash",'callback_data'=>"admin_unban=$sid_esc"]],
        ]])]);
    bot('sendMessage',['chat_id'=>$owner_id,
        'text'=>"🚫 <b>Kassangiz ban qilindi!</b>\n\n🏪 <b>$nomi</b>\nMurojaat: @xmtvv1",
        'parse_mode'=>'html']);
    exit;
}
if(!empty($data) && mb_stripos($data,"admin_unban=")!==false && in_array($cid,$admin)){
    $sid_esc  = explode("=",$data)[1];
    $shop_r   = mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM shops WHERE shop_id='$sid_esc'"));
    $nomi     = base64_decode($shop_r['shop_name']);
    $owner_id = $shop_r['user_id'];
    mysqli_query($connect,"UPDATE shops SET status='confirm' WHERE shop_id='$sid_esc'");
    bot('answerCallbackQuery',['callback_query_id'=>$qid,'text'=>"✅ Ban olib tashlandi"]);
    bot('editMessageText',['chat_id'=>$cid,'message_id'=>$mid,
        'text'=>"✅ <b>Ban olib tashlandi!</b>\n\n🏪 <b>$nomi</b>\n🆔 <code>$sid_esc</code>",
        'parse_mode'=>'html','reply_markup'=>json_encode(['inline_keyboard'=>[
            [['text'=>"🚫 Qayta ban qilish",'callback_data'=>"admin_ban=$sid_esc"]],
        ]])]);
    bot('sendMessage',['chat_id'=>$owner_id,
        'text'=>"✅ <b>Kassangiz qayta faollashdi!</b>\n\n🏪 <b>$nomi</b>",
        'parse_mode'=>'html']);
    exit;
}

// ============================================================
// KASSA O'CHIRISH (foydalanuvchi)
// ============================================================
if(!empty($data) && mb_stripos($data,"delete_kassa_ask=")!==false){
    $set_id = explode("=",$data)[1];
    $shop_r = mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM shops WHERE id='$set_id' AND user_id='$cid_esc'"));
    if(!$shop_r){ bot('answerCallbackQuery',['callback_query_id'=>$qid,'text'=>"❌ Kassa topilmadi!",'show_alert'=>true]); exit; }
    $nomi = base64_decode($shop_r['shop_name']);
    bot('editMessageReplyMarkup',['chat_id'=>$cid,'message_id'=>$mid,
        'reply_markup'=>json_encode(['inline_keyboard'=>[
            [['text'=>"⚠️ Haqiqatan ham \"$nomi\" kassasini o'chirmoqchimisiz?",'callback_data'=>"noop"]],
            [['text'=>"✅ Ha, o'chirish",'callback_data'=>"delete_kassa_yes=$set_id"],['text'=>"❌ Yo'q",'callback_data'=>"kassa_set=$set_id"]],
        ]])]);
    exit;
}
if(!empty($data) && mb_stripos($data,"delete_kassa_yes=")!==false){
    $set_id = explode("=",$data)[1];
    $shop_r = mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM shops WHERE id='$set_id' AND user_id='$cid_esc'"));
    if(!$shop_r){ bot('answerCallbackQuery',['callback_query_id'=>$qid,'text'=>"❌ Kassa topilmadi!",'show_alert'=>true]); exit; }
    $nomi    = base64_decode($shop_r['shop_name']);
    $shop_id_del = $shop_r['shop_id'];
    mysqli_query($connect,"DELETE FROM shops WHERE id='$set_id' AND user_id='$cid_esc'");
    mysqli_query($connect,"UPDATE checkout SET status='canceled' WHERE shop_id='$shop_id_del' AND status='pending'");
    bot('answerCallbackQuery',['callback_query_id'=>$qid,'text'=>"✅ Kassa o'chirildi"]);
    bot('editMessageText',['chat_id'=>$cid,'message_id'=>$mid,
        'text'=>"🗑️ <b>\"$nomi\" kassasi o'chirildi!</b>",
        'parse_mode'=>'html','reply_markup'=>json_encode(['inline_keyboard'=>[
            [['text'=>"⏪ Kassalarimga qaytish",'callback_data'=>"Kassalarim"]],
        ]])]);
    exit;
}

// ============================================================
// NOOP (sahifa raqami tugmasi)
// ============================================================
if(!empty($data) && $data=="noop"){
    bot('answerCallbackQuery',['callback_query_id'=>$qid]);
    exit;
}
?>
