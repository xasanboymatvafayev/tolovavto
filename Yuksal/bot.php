<?php

$sub_domen = "tolovavto-production.up.railway.app";
require (__DIR__ . "/../config.php");

$administrator = getenv('ADMIN_ID') ?: "6365371142";
$admin = [$administrator];

// Settings jadvalini avtomatik yaratish
mysqli_query($connect, "CREATE TABLE IF NOT EXISTS `settings` (
  `id`    int NOT NULL AUTO_INCREMENT,
  `key`   varchar(100) NOT NULL UNIQUE,
  `value` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
mysqli_query($connect, "INSERT IGNORE INTO `settings` (`key`,`value`) VALUES ('month_price','20000')");

function bot($method, $datas=[]){
    $ch = curl_init("https://api.telegram.org/bot".API_KEY."/".$method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $datas);
    $res = curl_exec($ch); curl_close($ch);
    return json_decode($res);
}

function generate(){
    $arr = ['A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','R','S','T','U','V','X','Y','Z','1','2','3','4','5','6','7','8','9','0'];
    $pass = "";
    for($i=0;$i<10;$i++) $pass .= $arr[rand(0,count($arr)-1)];
    return $pass;
}

function joinchat($id){
    global $connect, $administrator;
    $result = $connect->query("SELECT * FROM `channels`");
    if($result->num_rows > 0 && $id != $administrator){
        $no_subs=0; $button=[];
        while($row=$result->fetch_assoc()){
            $type=$row['type']; $link=$row['link']; $channelID=$row['channelID'];
            $gettitle=bot('getchat',['chat_id'=>$channelID])->result->title;
            if($type=="lock"||$type=="request"){
                if($type=="request"){
                    global $connect;
                    $c=$connect->query("SELECT * FROM `requests` WHERE id='$id' AND chat_id='$channelID'");
                    if($c->num_rows>0) $button[]=['text'=>"✅ $gettitle",'url'=>$link];
                    else{ $button[]=['text'=>"❌ $gettitle",'url'=>$link]; $no_subs++; }
                }elseif($type=="lock"){
                    $s=bot('getChatMember',['chat_id'=>$channelID,'user_id'=>$id])->result->status;
                    if($s=="left"){ $button[]=['text'=>"❌ $gettitle",'url'=>$link]; $no_subs++; }
                    else $button[]=['text'=>"✅ $gettitle",'url'=>$link];
                }
            }elseif($type=="social"){
                $button[]=['text'=>base64_decode($row['title']),'url'=>$link];
            }
        }
        if($no_subs>0){
            $button[]=['text'=>"🔄 Tekshirish",'callback_data'=>"result"];
            bot('sendMessage',['chat_id'=>$id,'text'=>"⛔ Kanallarga obuna bo'ling:",'parse_mode'=>'html','reply_markup'=>json_encode(['inline_keyboard'=>array_chunk($button,1)])]);
            exit;
        } else return true;
    } else return true;
}

// Moliyaviy statistika — checkout jadvalidan (to'g'ri)
function getStats($connect, $shop_id){
    $today     = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $month_s   = date('Y-m-01');
    $prev_s    = date('Y-m-01', strtotime('-1 month'));
    $prev_e    = date('Y-m-t',  strtotime('-1 month'));
    $sid = mysqli_real_escape_string($connect, $shop_id);

    $q = function($sql) use ($connect){ $r=mysqli_fetch_assoc(mysqli_query($connect,$sql)); return (int)($r['s']??0); };

    return [
        'bugun'    => $q("SELECT COALESCE(SUM(amount),0) as s FROM checkout WHERE status='paid' AND shop_id='$sid' AND DATE(date)='$today'"),
        'kecha'    => $q("SELECT COALESCE(SUM(amount),0) as s FROM checkout WHERE status='paid' AND shop_id='$sid' AND DATE(date)='$yesterday'"),
        'bu_oy'    => $q("SELECT COALESCE(SUM(amount),0) as s FROM checkout WHERE status='paid' AND shop_id='$sid' AND date>='$month_s'"),
        'otgan_oy' => $q("SELECT COALESCE(SUM(amount),0) as s FROM checkout WHERE status='paid' AND shop_id='$sid' AND date BETWEEN '$prev_s 00:00:00' AND '$prev_e 23:59:59'"),
        'jami'     => $q("SELECT COALESCE(SUM(amount),0) as s FROM checkout WHERE status='paid' AND shop_id='$sid'"),
    ];
}

$update   = json_decode(file_get_contents('php://input'));
$message  = $update->message;
$callback = $update->callback_query;
$bot      = bot('getme',['bot'])->result->username;

if(isset($message)){
    $contact=$message->contact; $number=$contact->phone_number;
    $cid=$message->chat->id; $text=$message->text;
    $mid=$message->message_id; $name=$message->from->first_name;
}
if(isset($callback)){
    $data=$callback->data; $qid=$callback->id;
    $cid=$callback->message->chat->id; $mid=$callback->message->message_id;
    $name=$callback->from->first_name;
}

$new_key = generate();
$res=mysqli_query($connect,"SELECT * FROM users WHERE user_id='$cid'");
while($a=mysqli_fetch_assoc($res)){ $uid=$a['id']; $balance=$a['balance']; $payment=$a['deposit']; $step=$a['step']; }

if(isset($message)){
    if(!mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM users WHERE user_id='$cid'"))){
        mysqli_query($connect,"INSERT INTO users(user_id,balance,deposit,date,time,action) VALUES('$cid','0','0','$sana','$soat','member')");
    }
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

$back   = json_encode(['resize_keyboard'=>true,'keyboard'=>[[['text'=>"⏪ Ortga"]]]]);
$m = in_array($cid,$admin) ? $menu_p : $menu;

if($data=="result"){
    bot('DeleteMessage',['chat_id'=>$cid,'message_id'=>$mid]);
    if(joinchat($cid)==true) bot('SendMessage',['chat_id'=>$cid,'text'=>"✅ <b>Tasdiqlandi!</b>",'parse_mode'=>'html','reply_markup'=>$m]);
    exit;
}

if($text=="/start"||$text=="⏪ Ortga"){
    if(joinchat($cid)==true){
        mysqli_query($connect,"UPDATE users SET step='null' WHERE user_id='$cid'");
        bot('sendMessage',['chat_id'=>$cid,'text'=>"👋🏻 <b>Assalomu alaykum $name!</b>\n\n@$bot botga xush kelibsiz.",'parse_mode'=>'html','reply_markup'=>$m]);
        exit;
    }
}

if($text=="📕 Qoʻllanma"){
    bot('sendMessage',['chat_id'=>$cid,'text'=>"<b>📘 Qoʻllanma:</b>\n\nKassa qoʻshib admindan tasdiqlatasiz. Tasdiqlangach oylik toʻlovni toʻlaysiz va hisobni ulaysiz.\n\n📣 Yordam: @xmtvv1",'parse_mode'=>'html','reply_markup'=>$m]);
    exit;
}

if($text=="💵 Hisobim"){
    bot('sendMessage',['chat_id'=>$cid,'text'=>"👔 <b>Sizning hisobingiz!</b>\n\n• ID: <code>$uid</code>\n• Balans: <b>$balance</b> so'm\n• Kiritgan: <b>$payment</b> so'm",'parse_mode'=>'html','reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"🔄 Yangilash",'callback_data'=>"Hisobim"]],[['text'=>"📋 To'ldirish tarixi",'callback_data'=>"user_history=1"]]]])]);
    exit;
}
if($data=="Hisobim"){
    $a2=mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM users WHERE user_id='$cid'"));
    bot('editMessageText',['chat_id'=>$cid,'message_id'=>$mid,'text'=>"👔 <b>Sizning hisobingiz!</b>\n\n• ID: <code>".$a2['id']."</code>\n• Balans: <b>".$a2['balance']."</b> so'm\n• Kiritgan: <b>".$a2['deposit']."</b> so'm",'parse_mode'=>'html','reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"🔄 Yangilash",'callback_data'=>"Hisobim"]],[['text'=>"📋 To'ldirish tarixi",'callback_data'=>"user_history=1"]]]])]);
    exit;
}

if($text=="💳 To'ldirish"){
    bot('sendMessage',['chat_id'=>$cid,'text'=>"<b>👇 To'lov tizimini tanlang!</b>",'parse_mode'=>'html','reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"🏦 Bank karta (Avtomatik)",'callback_data'=>"uzcard"]]]])]);
    exit;
}
if($data=="uzcard"){
    bot('DeleteMessage',['chat_id'=>$cid,'message_id'=>$mid]);
    bot('SendMessage',['chat_id'=>$cid,'text'=>"💵 <b>Toʻlov miqdorini kiriting:</b>\n\nMinimal: 1000 soʻm",'parse_mode'=>'html','reply_markup'=>$back]);
    mysqli_query($connect,"UPDATE users SET step='uzcard_auto' WHERE user_id='$cid'");
    exit;
}

if($step=="uzcard_auto"){
    if(!is_numeric($text)){ bot('sendMessage',['chat_id'=>$cid,'text'=>"🔢 <b>Faqat raqam kiriting!</b>",'parse_mode'=>'html','reply_markup'=>$back]); exit; }
    $amount_original=intval(trim($text));
    if($amount_original<1000){ bot('sendMessage',['chat_id'=>$cid,'text'=>"⚠️ <b>Minimal 1000 so'm!</b>",'parse_mode'=>'html','reply_markup'=>$back]); exit; }

    // Vaqti o'tgan pending orderlarni tozala
    $expire_time = date('Y-m-d H:i:s', strtotime('-5 minutes'));
    mysqli_query($connect,"UPDATE checkout SET status='canceled' WHERE shop_id='127000' AND status='pending' AND date <= '$expire_time'");

    // === Foydalanuvchining o'zining faol orderi bormi? ===
    $active_order = mysqli_fetch_assoc(mysqli_query($connect,
        "SELECT * FROM checkout WHERE user_id='$cid' AND shop_id='127000' AND status='pending' AND date > '$expire_time'"
    ));
    if($active_order){
        $pay_url="https://$sub_domen/pay?order=".$active_order['order']."&shop_id=127000";
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
        mysqli_query($connect,"UPDATE users SET step='null' WHERE user_id='$cid'");
        exit;
    }

    // === Miqdor o'zgartirilmaydi — aynan kiritilganicha ===
    $amount = $amount_original;

    // Kassaning karta va bank ma'lumotlari
    $main_shop = mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM shops WHERE shop_id='127000'"));
    $card_raw  = ($main_shop && !empty($main_shop['card_number'])) ? preg_replace('/\s+/','',$main_shop['card_number']) : '5614683582279246';
    $card_show = chunk_split($card_raw,4,' ');

    // checkout jadvaliga order yaratish
    $order = generate();
    $today = date("Y-m-d H:i:s");
    mysqli_query($connect,
        "INSERT INTO checkout (`order`, shop_id, shop_key, amount, status, `over`, date, user_id)
         VALUES ('$order', '127000', 'OP004K6367', '$amount', 'pending', '5', '$today', '$cid')"
    );

    $pay_url = "https://$sub_domen/pay?order=$order&shop_id=127000";

    bot('sendMessage',['chat_id'=>$cid,'text'=>"➡️ <b>To'lov kartasi:</b> <code>$card_show</code>\n\n💵 To'lash kerak: <code>$amount</code> so'm\n⏰ Kutish vaqti: <b>5</b> daqiqa\n✅ To'lov avtomatik qabul qilinadi\n\n👉🏻 Aynan <b>$amount</b> so'm yuboring!",'parse_mode'=>'html','reply_markup'=>json_encode(['inline_keyboard'=>[
        [['text'=>"💵 $amount so'm — nusxalash",'copy_text'=>['text'=>$amount]]],
        [['text'=>'💳 Kartani nusxalash','copy_text'=>['text'=>$card_raw]]],
        [['text'=>"✅ To'lovni tekshirish",'callback_data'=>"chk=$amount=$order"]],
        [['text'=>"🌐 Web to'lov sahifasi",'url'=>$pay_url]],
        [['text'=>"❌ Bekor qilish",'callback_data'=>"cancel_order=$order"]],
    ]])]);
    mysqli_query($connect,"UPDATE users SET step='null' WHERE user_id='$cid'");
    exit;
}
// === TO'LOVNI TEKSHIRISH ===
if(mb_stripos($data,"chk=")!==false){
    bot('answerCallbackQuery',['callback_query_id'=>$qid,'text'=>"🔄 Tekshirilmoqda..."]);
    $parts=explode("=",$data); $amount_c=(int)$parts[1]; $order_c=$parts[2];
    $order_esc=mysqli_real_escape_string($connect,$order_c);

    // checkout jadvalidan bu order holatini tekshir
    $checkout_row=mysqli_fetch_assoc(mysqli_query($connect,
        "SELECT * FROM checkout WHERE `order`='$order_esc' LIMIT 1"
    ));

    $paid = false;

    if($checkout_row && $checkout_row['status']==='paid'){
        // Webhook allaqachon paid qilgan — lekin balans qo'shildimi?
        $already=mysqli_fetch_assoc(mysqli_query($connect,
            "SELECT * FROM checkout WHERE `order`='$order_esc' AND user_id='$cid' AND paid_to_user='1' LIMIT 1"
        ));
        if(!$already){
            mysqli_query($connect,"UPDATE users SET balance=balance+$amount_c, deposit=deposit+$amount_c WHERE user_id='$cid'");
            mysqli_query($connect,"UPDATE checkout SET paid_to_user='1' WHERE `order`='$order_esc'");
        }
        $paid = true;
    } elseif($checkout_row && $checkout_row['status']==='pending'){
        // Hali webhook kelmadi — payments jadvalidan qidiramiz
        $expire_time = date('Y-m-d H:i:s', strtotime('-6 minutes'));
        $email_pay=mysqli_fetch_assoc(mysqli_query($connect,
            "SELECT * FROM payments 
             WHERE amount='$amount_c' AND status='pending' 
             AND card_type='credit'
             AND created_at >= '$expire_time' 
             LIMIT 1"
        ));
        if($email_pay){
            // Topildi — to'lovni tasdiqlash
            mysqli_query($connect,"UPDATE payments SET status='used', used_order='$order_esc' WHERE id='".$email_pay['id']."'");
            mysqli_query($connect,"UPDATE checkout SET status='paid', paid_to_user='1' WHERE `order`='$order_esc'");
            mysqli_query($connect,"UPDATE users SET balance=balance+$amount_c, deposit=deposit+$amount_c WHERE user_id='$cid'");
            $paid = true;
        }
    }

    if($paid){
        bot('editMessageText',['chat_id'=>$cid,'message_id'=>$mid,
            'text'=>"✅ <b>To'lov tasdiqlandi!</b>\n\n💵 <b>".number_format($amount_c,0,'.',' ')."</b> so'm hisobingizga qo'shildi!",
            'parse_mode'=>'html','reply_markup'=>json_encode(['inline_keyboard'=>[]])]);
    }else{
        $pay_url="https://$sub_domen/pay?order=$order_c&shop_id=127000";
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

// === BEKOR QILISH (tezlashtirilgan) ===
if(mb_stripos($data,"cancelpay=")!==false){
    // Darhol answerCallbackQuery
    bot('answerCallbackQuery',['callback_query_id'=>$qid,'text'=>"❓ Tasdiqlang"]);
    $id=explode("=",$data)[1];
    bot('editMessageReplyMarkup',['chat_id'=>$cid,'message_id'=>$mid,
        'reply_markup'=>json_encode(['inline_keyboard'=>[
            [['text'=>"✅ Ha, bekor qilish",'callback_data'=>"cancelpayy=$id"],['text'=>"❌ Yo'q",'callback_data'=>"del_msg"]],
        ]])]);
    exit;
}
if(mb_stripos($data,"cancelpayy=")!==false){
    bot('answerCallbackQuery',['callback_query_id'=>$qid,'text'=>"✅ Bekor qilindi"]);
    $id=explode("=",$data)[1];
    mysqli_query($connect,"UPDATE payments SET status='cancel' WHERE id='$id' AND user_id='$cid'");
    bot('editMessageText',['chat_id'=>$cid,'message_id'=>$mid,
        'text'=>"❌ <b>To'lov bekor qilindi!</b>",'parse_mode'=>'html',
        'reply_markup'=>json_encode(['inline_keyboard'=>[]])]);
    exit;
}
if($data=="del_msg"){
    bot('answerCallbackQuery',['callback_query_id'=>$qid]);
    bot('DeleteMessage',['chat_id'=>$cid,'message_id'=>$mid]);
    exit;
}

if(mb_stripos($data,"cancel_order=")!==false){
    bot('answerCallbackQuery',['callback_query_id'=>$qid,'text'=>"✅ Bekor qilindi"]);
    $order_c=explode("=",$data)[1];
    $esc=mysqli_real_escape_string($connect,$order_c);
    // checkout jadvalini ham cancel qilish (web to'lov sahifasi ham yangilanadi)
    mysqli_query($connect,"UPDATE checkout SET status='canceled' WHERE `order`='$esc'");
    mysqli_query($connect,"UPDATE payments SET status='cancel' WHERE used_order='$esc' AND user_id='$cid'");
    bot('editMessageText',['chat_id'=>$cid,'message_id'=>$mid,
        'text'=>"❌ <b>To'lov bekor qilindi!</b>",'parse_mode'=>'html',
        'reply_markup'=>json_encode(['inline_keyboard'=>[]])]);
    exit;
}

if($text=="📖 API Hujjatlar"){
    bot('sendMessage',['chat_id'=>$cid,'text'=>"<b>📖 API Hujjatlar:</b>\nhttps://tolovchiuz.vercel.app",'disable_web_page_preview'=>true,'parse_mode'=>'html','reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"📁 API Docs",'url'=>"https://tolovchiuz.vercel.app/api.html"]]]])]);
    exit;
}

// KASSALARIM
function show_kassalar($cid,$connect,$mid=null,$edit=false){
    $result=mysqli_query($connect,"SELECT * FROM `shops` WHERE `user_id`='$cid'");
    $i=0; $key=[];
    while($us=mysqli_fetch_assoc($result)){
        $i++;
        $icon=($us['status']=="waiting")?"🔄":(($us['status']=="confirm")?"✅":"⛔");
        $key[]=[["text"=>"$i. $icon ".base64_decode($us['shop_name']),"callback_data"=>"kassa_set=".$us['id']]];
    }
    $key[]=[['text'=>"➕ Kassa qoʻshish",'callback_data'=>"add_kassa"]];
    $kb=json_encode(['inline_keyboard'=>$key]);
    $r2=mysqli_query($connect,"SELECT * FROM `shops` WHERE `user_id`='$cid'");
    $txt=(mysqli_num_rows($r2)>0)?"🏪 <b>Kassalaringiz:</b>":"⚠️ Kassalar mavjud emas!";
    if($edit) bot('editmessagetext',['chat_id'=>$cid,'message_id'=>$mid,'text'=>$txt,'parse_mode'=>'html','reply_markup'=>$kb]);
    else bot('sendmessage',['chat_id'=>$cid,'text'=>$txt,'parse_mode'=>'html','reply_markup'=>$kb]);
}
if($text=="🏪 Kassalarim"){ show_kassalar($cid,$connect); }
if($data=="Kassalarim"){ show_kassalar($cid,$connect,$mid,true); }

// KASSA KO'RISH
if(mb_stripos($data,"kassa_set=")!==false){
    $id=explode("=",$data)[1];
    $rew=mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM shops WHERE id='$id'"));
    $nomi=base64_decode($rew['shop_name']);
    $shop_id=$rew['shop_id']; $shop_key=$rew['shop_key'];
    $address=$rew['shop_address']; $status=$rew['status'];
    $month_status=$rew['month_status']??"Toʻlanmagan!";
    $over=$rew['over_day']??"0";
    $phone=$rew['phone']??null;
    $card_num=$rew['card_number']??null;
    $card_bank=$rew['card_bank']??null;
    $shop_user_id=$rew['user_id'];

    $kb=['inline_keyboard'=>[]]; $confirm_text="";

    if($status=="waiting"){
        $icon="🔄 Kutilmoqda...";
        $kb['inline_keyboard'][]=[['text'=>"⏪ Ortga",'callback_data'=>"Kassalarim"]];
    }elseif($status=="confirm"){
        $created=strtotime($rew['date']??'now');
        $expire=date('Y-m-d',strtotime("+".(int)$over." days",$created));

        // === TO'G'RILANGAN: checkout jadvalidan statistika ===
        $stats=getStats($connect,$shop_id);
        $stat_text="\n📊 <b>Moliyaviy statistika</b>\n\n".
            "📅 Bugungi tushum: <b>".number_format($stats['bugun'],0,'.',' ')."</b> so'm\n".
            "📆 Kechagi tushum: <b>".number_format($stats['kecha'],0,'.',' ')."</b> so'm\n".
            "🗓 Bu oygi tushum: <b>".number_format($stats['bu_oy'],0,'.',' ')."</b> so'm\n".
            "📉 O'tgan oygi tushum: <b>".number_format($stats['otgan_oy'],0,'.',' ')."</b> so'm\n".
            "💼 Jami aylanma: <b>".number_format($stats['jami'],0,'.',' ')."</b> so'm\n";

        $need_pay=(empty($month_status)||$month_status=="Toʻlanmagan!");

        if($need_pay){
            $icon="📛 Toʻlanmagan!";
            $confirm_text="\n🔎 Oylik to'lov holati: <b>$month_status</b>\n".$stat_text;
            $kb['inline_keyboard'][]=[['text'=>"💵 Oylik to'lovni to'lash",'callback_data'=>"kassa_payment=$shop_id=$id"]];
        }elseif(empty($phone)){
            $icon="📛 Hisob ulanmagan!";
            $confirm_text="\n🔎 Holat: <b>$month_status</b>\n".$stat_text;
            $kb['inline_keyboard'][]=[['text'=>"🔗 Kassa ulash",'callback_data'=>"req_connect=$shop_id=$cid"]];
        }else{
            $icon="✅ Faol!";
            $confirm_text="\n🔘 Oylik to'lov holati: <b>$month_status</b>\n📆 Kassa muddati: <b>$expire</b>\n".$stat_text;
            $kb['inline_keyboard'][]=[['text'=>"💰 To'lovlar tarixi",'callback_data'=>"kassa_history=$shop_id=$id=1"]];
            $kb['inline_keyboard'][]=[['text'=>"⏩ Muddatni uzaytirish",'callback_data'=>"kassa_payment=$shop_id=$id"]];
            $kb['inline_keyboard'][]=[['text'=>"⚙️ Sozlamalar",'callback_data'=>"kassa_sozlama=$shop_id=$id"]];
        }
        $kb['inline_keyboard'][]=[['text'=>"⏪ Ortga",'callback_data'=>"Kassalarim"]];
    }elseif($status=="canceled"){
        $icon="⛔ Bekor qilingan!";
        $kb['inline_keyboard'][]=[['text'=>"⏪ Ortga",'callback_data'=>"Kassalarim"]];
    }

    bot('editMessageText',['chat_id'=>$cid,'message_id'=>$mid,
        'text'=>"<b>$nomi ($icon)</b>\n$confirm_text\n🆔 Shop ID: <code>$shop_id</code>\n🔑 Shop Key: <code>$shop_key</code>\n🔗 Manzil: <b>$address</b>",
        'parse_mode'=>'html','reply_markup'=>json_encode($kb)]);
    exit;
}

// SOZLAMALAR
if(mb_stripos($data,"kassa_sozlama=")!==false){
    $parts=explode("=",$data); $shop_id_s=$parts[1]; $set_id_s=$parts[2];
    $rew=mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM shops WHERE shop_id='$shop_id_s'"));
    $card_num=$rew['card_number']??null;
    $card_bank=$rew['card_bank']??null;
    $webhook_url=$rew['webhook_url']??null;
    $email_addr=$rew['email']??null;

    $provider="—";
    if($card_num){
        $first=substr(preg_replace('/\s+/','',$card_num),0,1);
        $provider=($first=='9')?"HUMO ✅":"UZCARD ✅";
    }
    $card_show=$card_num?chunk_split(preg_replace('/\s+/','',$card_num),4,' '):"Kiritilmagan";
    $email_show=$email_addr?$email_addr:"Kiritilmagan";

    bot('editMessageText',['chat_id'=>$cid,'message_id'=>$mid,
        'text'=>"⚙️ <b>Kassa sozlamalari</b>\n\n".
            "📧 Ulangan e-mail: <code>$email_show</code>\n".
            "🔌 Provider: $provider\n".
            "💳 Karta: <code>$card_show</code>".($card_bank?" <b>$card_bank</b>":"")."\n".
            "🆔 Shop ID: <code>$shop_id_s</code>\n".
            "🔑 Shop Key: <code>".$rew['shop_key']."</code>\n".
            "🔗 Manzil: <b>".$rew['shop_address']."</b>",
        'parse_mode'=>'html',
        'reply_markup'=>json_encode(['inline_keyboard'=>[
            [['text'=>"💳 Karta raqam kiritish",'callback_data'=>"set_card=$shop_id_s=$set_id_s"]],
            [['text'=>"📩 Kassa ulash",'callback_data'=>"req_connect=$shop_id_s=$cid"]],
            [['text'=>"⚠️ Keyni yangilash",'callback_data'=>"new_key=$shop_id_s"]],
            [['text'=>"⏪ Ortga",'callback_data'=>"kassa_set=$set_id_s"]],
        ]])]);
    exit;
}

// === FOYDALANUVCHI TO'LDIRISH TARIXI ===
if(mb_stripos($data,"user_history=")!==false){
    $page=(int)(explode("=",$data)[1]??1);
    $per_page=8; $offset=($page-1)*$per_page;
    $cid_esc=mysqli_real_escape_string($connect,$cid);

    // checkout jadvalidan (bot deposit) + payments (kartaga tushgan)
    $total_r=mysqli_fetch_assoc(mysqli_query($connect,
        "SELECT COUNT(*) as c FROM checkout WHERE user_id='$cid_esc' AND shop_id='127000' AND status='paid'"
    ));
    $total=(int)($total_r['c']??0);
    $total_pages=max(1,ceil($total/$per_page));

    $res=mysqli_query($connect,
        "SELECT c.amount, c.date,
                p.merchant, p.date as pay_date, p.card_type
         FROM checkout c
         LEFT JOIN payments p ON p.used_order = c.`order`
         WHERE c.user_id='$cid_esc' AND c.shop_id='127000' AND c.status='paid'
         ORDER BY c.date DESC LIMIT $per_page OFFSET $offset"
    );

    $user_bal=mysqli_fetch_assoc(mysqli_query($connect,"SELECT balance,deposit FROM users WHERE user_id='$cid_esc'"));
    $bal_show=number_format((int)($user_bal['balance']??0),0,'.',' ');
    $dep_show=number_format((int)($user_bal['deposit']??0),0,'.',' ');

    if($res && mysqli_num_rows($res)>0){
        $list=""; $i=$offset+1;
        while($row=mysqli_fetch_assoc($res)){
            $amt=number_format((int)$row['amount'],0,'.',' ');
            $dt=substr($row['date'],0,16);
            $merchant=$row['merchant']??'';
            $pay_dt=substr($row['pay_date']??'',0,16);
            $extra=(!empty($merchant))? "\n💳 $merchant" : "";
            if(!empty($pay_dt)) $extra.="\n🕒 Bank: $pay_dt";
            $list.="<b>$i</b>. ✅ <b>+$amt</b> so'm\n📅 $dt".$extra."\n\n";
            $i++;
        }
        $txt="📋 <b>To'ldirish tarixi</b>\n\n💵 Balans: <b>$bal_show</b> so'm\n📥 Jami kiritgan: <b>$dep_show</b> so'm\n\n$list";
    } else {
        $txt="📋 <b>To'ldirish tarixi</b>\n\n💵 Balans: <b>$bal_show</b> so'm\n\n❌ Hali to'ldirish tarixi yo'q.";
    }

    $nav=[];
    if($page>1) $nav[]=['text'=>"◀️ Oldingi",'callback_data'=>"user_history=".($page-1)];
    if($total_pages>1) $nav[]=['text'=>"$page/$total_pages",'callback_data'=>"noop"];
    if($page<$total_pages) $nav[]=['text'=>"Keyingi ▶️",'callback_data'=>"user_history=".($page+1)];
    $kb=['inline_keyboard'=>[]];
    if(!empty($nav)) $kb['inline_keyboard'][]=$nav;
    $kb['inline_keyboard'][]=[['text'=>"⬅️ Ortga",'callback_data'=>"Hisobim"]];

    bot('editMessageText',['chat_id'=>$cid,'message_id'=>$mid,
        'text'=>$txt,'parse_mode'=>'html','reply_markup'=>json_encode($kb)]);
    exit;
}

// === TO'LOVLAR TARIXI (checkout + payments birlashtirgan) ===
// === TO'LOVLAR TARIXI (faqat shu kassaga tegishli) ===
if(mb_stripos($data,"kassa_history=")!==false){
    $parts=explode("=",$data); 
    $shop_id_h = $parts[1]; 
    $set_id = $parts[2]; 
    $page = (int)($parts[3]??1);
    $per_page = 10; 
    $offset = ($page-1)*$per_page;
    $shop_id_h_esc = mysqli_real_escape_string($connect, $shop_id_h);
    
    // Jami yozuvlar soni - payments.shop_id yoki checkout orqali
    $total_r = mysqli_fetch_assoc(mysqli_query($connect,
        "SELECT COUNT(*) as c FROM payments p
          LEFT JOIN checkout ch ON ch.`order` = p.used_order
          WHERE COALESCE(p.shop_id, ch.shop_id) = '$shop_id_h_esc'"
    ));
    $total = (int)($total_r['c']??0);
    $total_pages = max(1, ceil($total/$per_page));
    
    // Barcha to'lovlar - kirim ham, chiqim ham
    $res = mysqli_query($connect,
        "SELECT p.amount, p.date, p.card_type, p.merchant, p.status, p.used_order
         FROM payments p
         LEFT JOIN checkout ch ON ch.`order` = p.used_order
         WHERE COALESCE(p.shop_id, ch.shop_id) = '$shop_id_h_esc'
         ORDER BY p.created_at DESC, p.id DESC
         LIMIT $per_page OFFSET $offset"
    );
    
    if($res && mysqli_num_rows($res)>0){
        $list = ""; 
        $i = $offset + 1;
        while($row = mysqli_fetch_assoc($res)){
            $amt = number_format((int)$row['amount'], 0, '.', ' ');
            $dt = substr($row['date']??'', 0, 16);
            $merchant = $row['merchant'] ?? '';
            $used = $row['used_order'] ?? '';
            
            if($row['card_type'] === 'credit'){
                $icon = "🟢"; 
                $sign = "+";
                $type_label = "Kirim (O'tkazma olindi)";
            } else {
                $icon = "🔴"; 
                $sign = "-";
                $type_label = "Chiqim (To'lov)";
            }
            
            $line = "<b>$i</b>. $icon <b>$sign$amt</b> so'm — $type_label";
            if(!empty($merchant)) $line .= "\n🏪 " . htmlspecialchars($merchant);
            if(!empty($dt)) $line .= "\n📅 " . $dt;
            if(!empty($used)) $line .= "\n🔗 <code>" . htmlspecialchars($used) . "</code>";
            $list .= $line . "\n\n";
            $i++;
        }
        
        // Jami statistika - faqat shu kassa uchun
        $stats = mysqli_fetch_assoc(mysqli_query($connect,
            "SELECT
               COALESCE(SUM(CASE WHEN p.card_type='credit' THEN p.amount ELSE 0 END),0) as jami_kirim,
               COALESCE(SUM(CASE WHEN p.card_type='debit' THEN p.amount ELSE 0 END),0) as jami_chiqim
             FROM payments p
             LEFT JOIN checkout ch ON ch.`order` = p.used_order
             WHERE COALESCE(p.shop_id, ch.shop_id) = '$shop_id_h_esc'"
        ));
        $kirim_fmt = number_format((int)$stats['jami_kirim'], 0, '.', ' ');
        $chiqim_fmt = number_format((int)$stats['jami_chiqim'], 0, '.', ' ');
        
        $header = "💰 <b>To'lovlar tarixi</b> (jami: $total ta)\n";
        $header .= "🟢 Kirim: <b>$kirim_fmt</b> so'm | 🔴 Chiqim: <b>$chiqim_fmt</b> so'm\n\n";
        
        $nav = [];
        if($page > 1) $nav[] = ['text'=>"◀️ Oldingi", 'callback_data'=>"kassa_history=$shop_id_h=$set_id=".($page-1)];
        $nav[] = ['text'=>"$page / $total_pages", 'callback_data'=>"noop"];
        if($page < $total_pages) $nav[] = ['text'=>"Keyingi ▶️", 'callback_data'=>"kassa_history=$shop_id_h=$set_id=".($page+1)];
        
        $kb = ['inline_keyboard'=>[]];
        if(count($nav) > 1 || $total_pages > 1) $kb['inline_keyboard'][] = $nav;
        $kb['inline_keyboard'][] = [['text'=>"⏪ Ortga", 'callback_data'=>"kassa_set=$set_id"]];
        
        bot('editMessageText', [
            'chat_id' => $cid,
            'message_id' => $mid,
            'text' => $header . $list,
            'parse_mode' => 'html',
            'reply_markup' => json_encode($kb)
        ]);
    } else {
        bot('answerCallbackQuery', [
            'callback_query_id' => $qid,
            'text' => "❌ To'lovlar topilmadi!",
            'show_alert' => true
        ]);
    }
    exit;
}

// MUDDATNI UZAYTIRISH
if(mb_stripos($data,"kassa_payment=")!==false){
    $parts=explode("=",$data); $shop_id_p=$parts[1]; $set_id_p=$parts[2];
    $rew=mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM users WHERE user_id='$cid'"));
    $bal=$rew['balance'];
    $settings_r=mysqli_fetch_assoc(mysqli_query($connect,"SELECT value FROM settings WHERE `key`='month_price'"));
    $month_price=(int)($settings_r['value']??20000);
    $req=in_array($cid,$admin)?0:$month_price;

    if($bal>=$req){
        $new_bal=$bal-$req;
        mysqli_query($connect,"UPDATE users SET balance=$new_bal WHERE user_id='$cid'");
        $shop_r=mysqli_fetch_assoc(mysqli_query($connect,"SELECT over_day FROM shops WHERE id='$set_id_p'"));
        $new_over=($shop_r['over_day']??0)+30;
        mysqli_query($connect,"UPDATE shops SET over_day=$new_over, month_status='Toʻlandi' WHERE id='$set_id_p'");
        bot('editMessageText',['chat_id'=>$cid,'message_id'=>$mid,
            'text'=>"✅ <b>Muddat 30 kunga uzaytirildi!</b>\n\n📆 Qolgan kun: <b>$new_over</b>",
            'parse_mode'=>'html',
            'reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"⏪ Ortga",'callback_data'=>"kassa_set=$set_id_p"]]]])]);
    }else{
        bot('answerCallbackQuery',['callback_query_id'=>$qid,'text'=>"⚠ Hisobda yetarli mablagʻ yoʻq. Kerak: 20,000 soʻm",'show_alert'=>true]);
    }
    exit;
}

// KARTA KIRITISH
if(mb_stripos($data,"set_card=")!==false){
    $parts=explode("=",$data); $shop_id_c=$parts[1]; $set_id_c=$parts[2];
    mysqli_query($connect,"UPDATE users SET step='set_card_num=$shop_id_c=$set_id_c' WHERE user_id='$cid'");
    bot('editMessageText',['chat_id'=>$cid,'message_id'=>$mid,
        'text'=>"💳 <b>16 xonali karta raqamini kiriting:</b>\n\nMasalan: 8600 1234 5678 9012",
        'parse_mode'=>'html']);
    exit;
}
if(mb_stripos($step,"set_card_num=")!==false){
    $parts=explode("=",$step); $shop_id_c=$parts[1]; $set_id_c=$parts[2];
    $card=preg_replace('/\s+/','',$text);
    if(!preg_match('/^\d{16}$/',$card)){ bot('sendMessage',['chat_id'=>$cid,'text'=>"❌ <b>16 ta raqam kiriting!</b>",'parse_mode'=>'html']); exit; }
    mysqli_query($connect,"UPDATE users SET step='set_card_bank=$shop_id_c=$set_id_c=$card' WHERE user_id='$cid'");
    bot('sendMessage',['chat_id'=>$cid,'text'=>"✅ Karta: <code>$card</code>\n\n🏦 <b>Bank turini tanlang:</b>",'parse_mode'=>'html','reply_markup'=>json_encode(['inline_keyboard'=>[
        [['text'=>"🔵 UzCard",'callback_data'=>"card_bank=UzCard=$shop_id_c=$set_id_c=$card"]],
        [['text'=>"🟢 Humo",'callback_data'=>"card_bank=Humo=$shop_id_c=$set_id_c=$card"]],
        [['text'=>"🟡 Visa",'callback_data'=>"card_bank=Visa=$shop_id_c=$set_id_c=$card"]],
        [['text'=>"🔴 MasterCard",'callback_data'=>"card_bank=MasterCard=$shop_id_c=$set_id_c=$card"]],
    ]])]);
    exit;
}
if(mb_stripos($data,"card_bank=")!==false){
    $parts=explode("=",$data); $bank=$parts[1]; $shop_id_c=$parts[2]; $set_id_c=$parts[3]; $card=$parts[4];
    mysqli_query($connect,"UPDATE shops SET card_number='$card', card_bank='$bank' WHERE shop_id='$shop_id_c'");
    mysqli_query($connect,"UPDATE users SET step='null' WHERE user_id='$cid'");
    $masked=chunk_split($card,4,' ');
    bot('editMessageText',['chat_id'=>$cid,'message_id'=>$mid,
        'text'=>"✅ <b>Karta saqlandi!</b>\n\n💳 <code>$masked</code>\n🏦 Bank: <b>$bank</b>",
        'parse_mode'=>'html',
        'reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"⏪ Ortga",'callback_data'=>"kassa_sozlama=$shop_id_c=$set_id_c"]]]])]);
    exit;
}

// WEBHOOK
// set_webhook funksiyasi o'chirildi

// KEY YANGILASH
if(mb_stripos($data,"new_key=")!==false){
    $id=explode("=",$data)[1]; $k=generate();
    mysqli_query($connect,"UPDATE shops SET shop_key='$k' WHERE shop_id='$id'");
    bot('editMessageText',['chat_id'=>$cid,'message_id'=>$mid,'text'=>"✅ <b>Kalit yangilandi!</b> <code>$k</code>",'parse_mode'=>'HTML']);
    exit;
}

// KASSA ULASH
if(mb_stripos($data,"req_connect=")!==false){
    $parts=explode("=",$data); $shop_id_r=$parts[1]; $user_id_r=$parts[2];
    $shop_email_r = mysqli_fetch_assoc(mysqli_query($connect,"SELECT email FROM shops WHERE shop_id='$shop_id_r'"));
    $email_show_r = $shop_email_r['email'] ?? 'Kiritilmagan';
    bot('answerCallbackQuery',['callback_query_id'=>$qid]);
    bot('sendMessage',['chat_id'=>$cid,
        'text'=>"📨 <b>Kassa ulash uchun Administratorga murojaat qiling</b>\n\n".
            "<code>$shop_id_r kassa uchun e-mail</code>\n".
            "deb yozing, va sizga tushuntiriladi hamda ulab beriladi.\n\n".
            "👨‍💻 Admin: @xmtvv1",
        'parse_mode'=>'html']);
    exit;
}

// ADMIN — Kassa ulash
if($text=="🔗 Kassa ulash"&&in_array($cid,$admin)){
    mysqli_query($connect,"UPDATE users SET step='admin_connect_shop' WHERE user_id='$cid'");
    bot('sendMessage',['chat_id'=>$cid,
        'text'=>"🏪 <b>Kassani ulash uchun Shop ID kiriting:</b>",
        'parse_mode'=>'html',
        'reply_markup'=>json_encode(['resize_keyboard'=>true,'keyboard'=>[[['text'=>"🗄️ Boshqaruv"]]]])]);
    exit;
}
if($step=="admin_connect_shop"&&in_array($cid,$admin)){
    if($text=="🗄️ Boshqaruv"){ mysqli_query($connect,"UPDATE users SET step='null' WHERE user_id='$cid'"); exit; }
    $shop_id_inp=mysqli_real_escape_string($connect,trim($text));
    $shop_r=mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM shops WHERE shop_id='$shop_id_inp'"));
    if(!$shop_r){
        bot('sendMessage',['chat_id'=>$cid,'text'=>"❌ Bu Shop ID topilmadi!",'reply_markup'=>$panel]);
        mysqli_query($connect,"UPDATE users SET step='null' WHERE user_id='$cid'"); exit;
    }
    $nomi=base64_decode($shop_r['shop_name']);
    $status_r=$shop_r['status']; $phone_r=$shop_r['phone']??null;
    $month_r=$shop_r['month_status']??"Toʻlanmagan"; $owner_id=$shop_r['user_id'];
    mysqli_query($connect,"UPDATE users SET step='null' WHERE user_id='$cid'");
    bot('sendMessage',['chat_id'=>$cid,
        'text'=>"🏪 <b>$nomi</b>\n\n🆔 Shop ID: <code>$shop_id_inp</code>\n👤 Egasi ID: <code>$owner_id</code>\n📊 Holat: <b>$status_r</b>\n💳 Oylik to'lov: <b>$month_r</b>\n📞 Raqam: ".($phone_r?"<code>$phone_r</code>":"Kiritilmagan"),
        'parse_mode'=>'html',
        'reply_markup'=>json_encode(['inline_keyboard'=>[
            [['text'=>"✅ Kassani faollashtirish",'callback_data'=>"activate_kassa=$shop_id_inp"]],
            [['text'=>"⏪ Ortga",'callback_data'=>"panel_back"]],
        ]])]);
    exit;
}

if($data=="panel_back"&&in_array($cid,$admin)){
    bot('editMessageText',['chat_id'=>$cid,'message_id'=>$mid,'text'=>"<b>Administrator paneli!</b>",'parse_mode'=>'html','reply_markup'=>json_encode(['inline_keyboard'=>[]])]);
    bot('sendMessage',['chat_id'=>$cid,'text'=>"<b>Administrator paneli!</b>",'parse_mode'=>'html','reply_markup'=>$panel]);
    exit;
}

if(mb_stripos($data,"admin_do_connect=")!==false&&in_array($cid,$admin)){
    $parts=explode("=",$data); $shop_id_ac=$parts[1]; $user_id_ac=$parts[2];
    bot('editMessageText',['chat_id'=>$cid,'message_id'=>$mid,
        'text'=>"📞 <b>Telefon raqamini kiriting:</b>\n\nMasalan: 998901234567",'parse_mode'=>'html']);
    mysqli_query($connect,"UPDATE users SET step='activate_phone=$shop_id_ac=$user_id_ac' WHERE user_id='$cid'");
    exit;
}

if(mb_stripos($data,"activate_kassa=")!==false&&in_array($cid,$admin)){
    $shop_id_ak=explode("=",$data)[1];
    $shop_r=mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM shops WHERE shop_id='$shop_id_ak'"));
    $owner_id=$shop_r['user_id'];
    bot('editMessageText',['chat_id'=>$cid,'message_id'=>$mid,
        'text'=>"📞 <b>Telefon raqamini kiriting:</b>\n\nMasalan: 998901234567",'parse_mode'=>'html']);
    mysqli_query($connect,"UPDATE users SET step='activate_phone=$shop_id_ak=$owner_id' WHERE user_id='$cid'");
    exit;
}

if(mb_stripos($step,"activate_phone=")!==false&&in_array($cid,$admin)){
    $parts=explode("=",$step); $shop_id_ak=$parts[1]; $owner_id=$parts[2];
    $phone_num=preg_replace('/[^0-9]/','',trim($text));
    if(strlen($phone_num)<9){ bot('sendMessage',['chat_id'=>$cid,'text'=>"❌ Noto'g'ri raqam! Qayta kiriting.",'parse_mode'=>'html']); exit; }
    mysqli_query($connect,"UPDATE users SET step='activate_email=$shop_id_ak=$owner_id=$phone_num' WHERE user_id='$cid'");
    bot('sendMessage',['chat_id'=>$cid,'text'=>"📧 <b>Foydalanuvchi emailini kiriting:</b>",'parse_mode'=>'html']);
    exit;
}

if(mb_stripos($step,"activate_email=")!==false&&in_array($cid,$admin)){
    $parts=explode("=",$step); $shop_id_ak=$parts[1]; $owner_id=$parts[2]; $phone_num=$parts[3];
    $email_inp=trim($text);
    if(!filter_var($email_inp,FILTER_VALIDATE_EMAIL)){
        bot('sendMessage',['chat_id'=>$cid,'text'=>"❌ Noto'g'ri email! Qayta kiriting.",'parse_mode'=>'html']); exit;
    }
    mysqli_query($connect,"UPDATE shops SET phone='$phone_num', email='".mysqli_real_escape_string($connect,$email_inp)."', month_status='Toʻlandi', over_day=30 WHERE shop_id='$shop_id_ak'");
    mysqli_query($connect,"UPDATE users SET step='null' WHERE user_id='$cid'");
    $shop_r=mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM shops WHERE shop_id='$shop_id_ak'"));
    $nomi=base64_decode($shop_r['shop_name']);
    bot('sendMessage',['chat_id'=>$cid,
        'text'=>"✅ <b>Kassa to'liq faollashdi!</b>\n\n🏪 Kassa: <b>$nomi</b>\n📞 Raqam: <code>$phone_num</code>\n📧 Email: <code>$email_inp</code>",
        'parse_mode'=>'html','reply_markup'=>$panel]);
    bot('sendMessage',['chat_id'=>$owner_id,
        'text'=>"✅ <b>Kassangiz to'liq faollashdi!</b>\n\n🏪 <b>$nomi</b>\nEndi to'lovlar avtomatik qabul qilinadi.",
        'parse_mode'=>'html']);
    exit;
}

// KASSA QO'SHISH
if($data=="add_kassa"){
    bot('deletemessage',['chat_id'=>$cid,'message_id'=>$mid]);
    bot('sendmessage',['chat_id'=>$cid,'text'=>"🛍️ <b>Yangi kassa qoʻshish!</b>\n\nKassa nomini yuboring:",'parse_mode'=>'html','reply_markup'=>$back]);
    mysqli_query($connect,"UPDATE users SET step='add_kassa' WHERE user_id='$cid'");
    exit;
}
if($step=="add_kassa"){
    if($text=="⏪ Ortga"){ mysqli_query($connect,"UPDATE users SET step='null' WHERE user_id='$cid'"); }
    else{
        if(mysqli_num_rows(mysqli_query($connect,"SELECT * FROM shops WHERE shop_name='".base64_encode($text)."'"))>0){
            bot('sendMessage',['chat_id'=>$cid,'text'=>"⚠️ Bu nom bilan kassa mavjud!",'parse_mode'=>'html']); exit;
        }
        bot('sendmessage',['chat_id'=>$cid,'text'=>"✅ Nom qabul qilindi!\n\nKassa havolasini kiriting:\n<i>Masalan: @username yoki tolovchi.uz</i>",'parse_mode'=>'html','reply_markup'=>$back]);
        mysqli_query($connect,"UPDATE users SET step='add_kassa_address-".base64_encode($text)."' WHERE user_id='$cid'");
        exit;
    }
}
if(mb_stripos($step,"add_kassa_address-")!==false){
    $name=explode("-",$step,2)[1];
    if($text=="⏪ Ortga"){ mysqli_query($connect,"UPDATE users SET step='null' WHERE user_id='$cid'"); }
    else{
        if(preg_match('/^@[\w]{5,25}$|^[a-z0-9-]+\.[a-z]{2,}$/i',$text)){
            bot('sendmessage',['chat_id'=>$cid,'text'=>"✅ Manzil qabul qilindi!\n\nKassa haqida ma'lumot kiriting:",'parse_mode'=>'html','reply_markup'=>$back]);
            mysqli_query($connect,"UPDATE users SET step='add_kassa_info-$name-$text' WHERE user_id='$cid'");
        }else{
            bot('sendMessage',['chat_id'=>$cid,'text'=>"⚠️ Noto'g'ri format!\n\n@username yoki domen kiriting",'parse_mode'=>'html']); exit;
        }
    }
}
if(mb_stripos($step,"add_kassa_info-")!==false){
    $parts2=explode("-",$step,3); $name=$parts2[1]; $address=$parts2[2];
    if($text=="⏪ Ortga"){ mysqli_query($connect,"UPDATE users SET step='null' WHERE user_id='$cid'"); }
    else{
        $sid=rand(111111,999999); $skey=$new_key;
        mysqli_query($connect,"INSERT INTO shops (user_id,shop_name,shop_info,shop_id,shop_key,shop_address,shop_balance,status,date) VALUES('$cid','$name','".base64_encode($text)."','$sid','$skey','$address','0','waiting','$sana')");
        bot('sendmessage',['chat_id'=>$cid,'text'=>"✅ <b>Adminga yuborildi! Kuting.</b>",'parse_mode'=>'html','reply_markup'=>$m]);
        mysqli_query($connect,"UPDATE users SET step='null' WHERE user_id='$cid'");
        bot('sendmessage',['chat_id'=>$administrator,
            'text'=>"✅ <b>Yangi kassa!</b>\n\n🆔 ID: $sid\n🔑 Key: $skey\n🛍️ Nom: <b>".base64_decode($name)."</b>\n🔗 Manzil: $address\n📖 Haqida: <b>$text</b>\n\n📅 $sana ⏰ $soat\n👤 User: <code>$cid</code>",
            'parse_mode'=>'html',
            'reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"✅ Tasdiqlash",'callback_data'=>"confirm=$sid"]],[['text'=>"⛔ Bekor qilish",'callback_data'=>"canceled=$sid"]]]])]);
        exit;
    }
}

if(mb_stripos($data,"confirm=")!==false){
    $id=explode("=",$data)[1];
    $rew=mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM shops WHERE shop_id='$id'"));
    bot('editMessageText',['chat_id'=>$cid,'message_id'=>$mid,'text'=>"✅ <b>Tasdiqlandi! #$id</b>",'parse_mode'=>'html']);
    bot('sendmessage',['chat_id'=>$rew['user_id'],'text'=>"✅ <b>Kassangiz tasdiqlandi! #$id</b>\n\nEndi sozlamalardan karta va email kiriting.",'parse_mode'=>'html','reply_markup'=>$m]);
    mysqli_query($connect,"UPDATE shops SET status='confirm' WHERE shop_id='$id'");
    exit;
}
if(mb_stripos($data,"canceled=")!==false){
    $id=explode("=",$data)[1];
    $rew=mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM shops WHERE shop_id='$id'"));
    bot('editMessageText',['chat_id'=>$cid,'message_id'=>$mid,'text'=>"⛔ <b>Bekor qilindi! #$id</b>",'parse_mode'=>'html']);
    bot('sendmessage',['chat_id'=>$rew['user_id'],'text'=>"⛔ <b>Kassangiz bekor qilindi! #$id</b>",'parse_mode'=>'html','reply_markup'=>$m]);
    mysqli_query($connect,"UPDATE shops SET status='canceled' WHERE shop_id='$id'");
    exit;
}

// ADMIN PANEL
if($text=="🗄️ Boshqaruv"||$text=="/panel"){
    if(in_array($cid,$admin)){
        mysqli_query($connect,"UPDATE users SET step='null' WHERE user_id='$cid'");
        bot('sendMessage',['chat_id'=>$cid,'text'=>"<b>Administrator paneli!</b>",'parse_mode'=>'html','reply_markup'=>$panel]);
        exit;
    }
}

// OYLIK NARH BOSHQARUV (admin)
if($text=="💰 Oylik narh"&&in_array($cid,$admin)){
    $cur=mysqli_fetch_assoc(mysqli_query($connect,"SELECT value FROM settings WHERE `key`='month_price'"));
    $cur_price=number_format((int)($cur['value']??20000),0,'.',' ');
    bot('sendMessage',['chat_id'=>$cid,
        'text'=>"💰 <b>Oylik to'lov narhi</b>\n\nHozirgi narh: <b>$cur_price</b> so'm\n\nYangi narh (so'mda) yuboring:",
        'parse_mode'=>'html',
        'reply_markup'=>$panel]);
    mysqli_query($connect,"UPDATE users SET step='set_month_price' WHERE user_id='$cid'");
    exit;
}
if($step=="set_month_price"&&in_array($cid,$admin)){
    $new_price=preg_replace('/[^0-9]/','',trim($text));
    if(!$new_price||$new_price<1000){
        bot('sendMessage',['chat_id'=>$cid,'text'=>"❌ Noto'g'ri narh! Minimal 1000 so'm.",'parse_mode'=>'html','reply_markup'=>$panel]);
        exit;
    }
    // settings jadvalida yangilash yoki yaratish
    $exists=mysqli_num_rows(mysqli_query($connect,"SELECT id FROM settings WHERE `key`='month_price'"));
    if($exists>0){
        mysqli_query($connect,"UPDATE settings SET value='$new_price' WHERE `key`='month_price'");
    } else {
        mysqli_query($connect,"INSERT INTO settings (`key`,value) VALUES('month_price','$new_price')");
    }
    mysqli_query($connect,"UPDATE users SET step='null' WHERE user_id='$cid'");
    $fmt=number_format((int)$new_price,0,'.',' ');
    bot('sendMessage',['chat_id'=>$cid,
        'text'=>"✅ <b>Oylik narh yangilandi!</b>\n\n💰 Yangi narh: <b>$fmt</b> so'm",
        'parse_mode'=>'html','reply_markup'=>$panel]);
    exit;
}

if($text=="📊 Statistika"&&in_array($cid,$admin)){
    $stat=mysqli_fetch_assoc(mysqli_query($connect,"SELECT COUNT(*) as c FROM users"))['c'];
    $bugun=mysqli_fetch_assoc(mysqli_query($connect,"SELECT COUNT(*) as c FROM users WHERE date='$sana'"))['c'];
    $textt=""; $s5=mysqli_query($connect,"SELECT * FROM users ORDER BY id DESC LIMIT 5");
    while($u=mysqli_fetch_assoc($s5)) $textt.="👤 <a href='tg://user?id=".$u['user_id']."'>".$u['user_id']."</a>\n";
    bot('sendMessage',['chat_id'=>$cid,'text'=>"📊 <b>Statistika</b>\n\n▫️ Jami: <b>$stat</b> ta\n▪️ Bugun: <b>$bugun</b> ta\n\nOxirgi 5ta:\n$textt",'parse_mode'=>"html",'reply_markup'=>$panel]);
    exit;
}

if($text=="👤 Foydalanuvchi"&&in_array($cid,$admin)){
    mysqli_query($connect,"UPDATE users SET step='user_check' WHERE user_id='$cid'");
    bot('sendMessage',['chat_id'=>$cid,'text'=>"<i>Foydalanuvchi ID kiriting:</i>",'parse_mode'=>'html','reply_markup'=>json_encode(['resize_keyboard'=>true,'keyboard'=>[[['text'=>"🗄️ Boshqaruv"]]]])]);
    exit;
}
if($step=="user_check"&&in_array($cid,$admin)){
    if($text=="🗄️ Boshqaruv"){ mysqli_query($connect,"UPDATE users SET step='null' WHERE user_id='$cid'"); exit; }
    mysqli_query($connect,"UPDATE users SET step='null' WHERE user_id='$cid'");
    $ch=mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM users WHERE user_id='$text'"));
    if(!$ch) $ch=mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM users WHERE id='$text'"));
    if($ch){
        bot('sendMessage',['chat_id'=>$cid,'text'=>"<b>📑 Foydalanuvchi!</b>\n\nID: <code>".$ch['user_id']."</code>\nBalans: <b>".$ch['balance']."</b> soʻm",'parse_mode'=>"HTML",'reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"➕ Pul qoʻshish",'callback_data'=>"pul=plus=".$ch['user_id']],['text'=>"➖ Pul ayirish",'callback_data'=>"pul=minus=".$ch['user_id']]]]])]);
    }else{
        bot('sendMessage',['chat_id'=>$cid,'text'=>"<b>🥲 Topilmadi!</b>",'parse_mode'=>"HTML",'reply_markup'=>$panel]);
    }
    exit;
}
if(mb_stripos($data,"pul=")!==false){
    $type=explode("=",$data)[1]; $id=explode("=",$data)[2];
    mysqli_query($connect,"UPDATE users SET step='pul=$type=$id' WHERE user_id='$cid'");
    bot('sendMessage',['chat_id'=>$cid,'text'=>"<b>Miqdorni kiriting:</b>",'parse_mode'=>'html','reply_markup'=>json_encode(['resize_keyboard'=>true,'keyboard'=>[[['text'=>"🗄️ Boshqaruv"]]]])]);
    exit;
}
if(mb_stripos($step,"pul=")!==false){
    $type=explode("=",$step)[1]; $id=explode("=",$step)[2];
    mysqli_query($connect,"UPDATE users SET step='null' WHERE user_id='$cid'");
    $ch=mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM users WHERE user_id='$id'"));
    $c=($type=="plus")?$ch['balance']+$text:$ch['balance']-$text;
    mysqli_query($connect,"UPDATE users SET balance='$c' WHERE user_id='$id'");
    bot('sendMessage',['chat_id'=>$cid,'text'=>($type=="plus"?"✔️":"⚠️")." <b>$text soʻm ".($type=="plus"?"qoʻshildi":"ayirildi")."!</b>",'parse_mode'=>'html','reply_markup'=>$panel]);
    exit;
}

if($text=="📨 Xabar yuborish"&&in_array($cid,$admin)){
    $send_row=mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM `send` LIMIT 1"));
    if(!$send_row){
        bot('SendMessage',['chat_id'=>$cid,'text'=>"<b>📨 Xabarni kiriting:</b>\n\nXabarni yuboring:",'parse_mode'=>'html','reply_markup'=>json_encode(['resize_keyboard'=>true,'keyboard'=>[[['text'=>"🗄️ Boshqaruv"]]]])]);
        mysqli_query($connect,"UPDATE users SET step='send' WHERE user_id='$cid'");
    }else{
        $t_start=$send_row['time1']??'—';
        bot('sendMessage',['chat_id'=>$cid,
            'text'=>"📨 <b>Yuborish davom etmoqda!</b>\n\n🕐 Vaqt: <b>$t_start</b>\n\nBekor qilish:",
            'parse_mode'=>'html',
            'reply_markup'=>json_encode(['inline_keyboard'=>[
                [['text'=>"🗑️ Yuborishni bekor qilish",'callback_data'=>"cancel_broadcast"]],
            ]])]);
    }
    exit;
}
if($data=="cancel_broadcast"&&in_array($cid,$admin)){
    mysqli_query($connect,"DELETE FROM `send`");
    bot('answerCallbackQuery',['callback_query_id'=>$qid,'text'=>"✅ Yuborish bekor qilindi"]);
    bot('editMessageText',['chat_id'=>$cid,'message_id'=>$mid,'text'=>"✅ <b>Yuborish bekor qilindi!</b>",'parse_mode'=>'html','reply_markup'=>json_encode(['inline_keyboard'=>[]])]);
    exit;
}
if($step=="send"&&in_array($cid,$admin)){
    $lu=mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM users ORDER BY id DESC LIMIT 1"));
    $t1=date('H:i',strtotime('+1 minutes')); $t2=date('H:i',strtotime('+2 minutes'));
    $rm=base64_encode(json_encode($update->message->reply_markup));
    mysqli_query($connect,"INSERT INTO `send` (time1,time2,start_id,stop_id,admin_id,message_id,reply_markup,step) VALUES('$t1','$t2','0','".$lu['user_id']."','$administrator','$mid','$rm','send')");
    bot('sendMessage',['chat_id'=>$cid,'text'=>"✅ <b>$t1 da yuboriladi!</b>",'parse_mode'=>'html','reply_markup'=>$panel]);
    mysqli_query($connect,"UPDATE users SET step='null' WHERE user_id='$cid'");
    exit;
}

if($text=="📢 Kanallar"&&in_array($cid,$admin)){
    bot('sendMessage',['chat_id'=>$cid,'text'=>"<b>Kanal turini tanlang:</b>",'parse_mode'=>'html','reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"Ommaviy",'callback_data'=>"request-false"]],[['text'=>"So'rov qabul qiluvchi",'callback_data'=>"request-true"]],[['text'=>"Ixtiyoriy havola",'callback_data'=>"socialnetwork"]]]])]);
    exit;
}
if($data=="socialnetwork"){
    bot('deleteMessage',['chat_id'=>$cid,'message_id'=>$mid]);
    bot('sendMessage',['chat_id'=>$cid,'text'=>"<b>Havola uchun nom:</b>",'parse_mode'=>'html','reply_markup'=>json_encode(['resize_keyboard'=>true,'keyboard'=>[[['text'=>"🗄️ Boshqaruv"]]]])]);
    mysqli_query($connect,"UPDATE users SET step='socialnetwork_step1' WHERE user_id='$cid'");
    exit;
}
if($step=="socialnetwork_step1"){
    mysqli_query($connect,"UPDATE users SET step='socialnetwork_step2', action='".mysqli_real_escape_string($connect,$text)."' WHERE user_id='$cid'");
    bot('sendMessage',['chat_id'=>$cid,'text'=>"✅ Qabul!\n\nHavolani kiriting:",'parse_mode'=>'html']);
    exit;
}
if($step=="socialnetwork_step2"){
    $nr=mysqli_fetch_assoc(mysqli_query($connect,"SELECT action FROM users WHERE user_id='$cid'"));
    mysqli_query($connect,"INSERT INTO `channels` (type,link,title,channelID) VALUES('social','".mysqli_real_escape_string($connect,$text)."','".base64_encode($nr['action'])."','')");
    bot('sendMessage',['chat_id'=>$cid,'text'=>"✅ <b>Kanal qo'shildi!</b>",'parse_mode'=>'html','reply_markup'=>$panel]);
    mysqli_query($connect,"UPDATE users SET step='null' WHERE user_id='$cid'");
    exit;
}
if(mb_stripos($data,"request-")!==false){
    $type=explode("-",$data)[1];
    mysqli_query($connect,"UPDATE users SET step='qosh', action='$type' WHERE user_id='$cid'");
    bot('sendMessage',['chat_id'=>$cid,'text'=>"<b>Kanaldan \"forward\" xabar yuboring:</b>",'parse_mode'=>'html','reply_markup'=>json_encode(['resize_keyboard'=>true,'keyboard'=>[[['text'=>"🗄️ Boshqaruv"]]]])]);
    exit;
}
if($step=="qosh"&&isset($message->forward_origin)){
    $kid=$message->forward_origin->chat->id;
    $tr=mysqli_fetch_assoc(mysqli_query($connect,"SELECT action FROM users WHERE user_id='$cid'"));
    if($tr['action']=="true"){
        $lnk=bot('createChatInviteLink',['chat_id'=>$kid,'creates_join_request'=>true])->result->invite_link;
        $sq="INSERT INTO `channels` (channelID,link,type) VALUES('$kid','$lnk','request')";
    }else{
        $lnk="https://t.me/".$message->forward_origin->chat->username;
        $sq="INSERT INTO `channels` (channelID,link,type) VALUES('$kid','$lnk','lock')";
    }
    $connect->query($sq);
    bot('sendMessage',['chat_id'=>$cid,'text'=>"✅ <b>Kanal qo'shildi!</b>",'parse_mode'=>'html','reply_markup'=>$panel]);
    mysqli_query($connect,"UPDATE users SET step='null' WHERE user_id='$cid'");
    exit;
}
if($text=="🗑️ Kanal o'chirish"&&in_array($cid,$admin)){
    $r=$connect->query("SELECT * FROM `channels`");
    if($r->num_rows>0){
        $btn=[];
        while($row=$r->fetch_assoc()){
            $gt=($row['type']=="lock"||$row['type']=="request")?bot('getchat',['chat_id'=>$row['channelID']])->result->title:base64_decode($row['title']);
            $btn[]=['text'=>"🗑️ $gt",'callback_data'=>"delchan=".$row['channelID']];
        }
        bot('sendMessage',['chat_id'=>$cid,'text'=>"<b>O'chirish uchun tanlang:</b>",'parse_mode'=>'html','reply_markup'=>json_encode(['inline_keyboard'=>array_chunk($btn,1)])]);
    }else bot('sendMessage',['chat_id'=>$cid,'text'=>"<b>Kanal yo'q!</b>",'parse_mode'=>'html']);
    exit;
}
if(stripos($data,"delchan=")!==false){
    $ex=explode("=",$data)[1];
    $connect->query("DELETE FROM channels WHERE channelID='$ex'");
    bot('editMessageText',['chat_id'=>$cid,'message_id'=>$mid,'text'=>"✅ <b>Kanal oʻchirildi!</b>",'parse_mode'=>'html']);
    exit;
}


// ============================================
// ADMIN: KASSA BOSHQARUV (oylik to'lov + muddat)
// ============================================
if($text=="🏪 Kassa boshqaruv"&&in_array($cid,$admin)){
    mysqli_query($connect,"UPDATE users SET step='admin_kassa_manage' WHERE user_id='$cid'");
    bot('sendMessage',['chat_id'=>$cid,
        'text'=>"🏪 <b>Kassa boshqaruv</b>\n\nShop ID kiriting:",
        'parse_mode'=>'html',
        'reply_markup'=>json_encode(['resize_keyboard'=>true,'keyboard'=>[[['text'=>"🗄️ Boshqaruv"]]]])]);
    exit;
}
if($step=="admin_kassa_manage"&&in_array($cid,$admin)){
    if($text=="🗄️ Boshqaruv"){ mysqli_query($connect,"UPDATE users SET step='null' WHERE user_id='$cid'"); exit; }
    $sid_esc=mysqli_real_escape_string($connect,trim($text));
    $shop_r=mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM shops WHERE shop_id='$sid_esc'"));
    if(!$shop_r){
        bot('sendMessage',['chat_id'=>$cid,'text'=>"❌ Bu Shop ID topilmadi!"]);
        exit;
    }
    $nomi=base64_decode($shop_r['shop_name']);
    $month_s=$shop_r['month_status']??'Toʻlanmagan';
    $over_d=(int)($shop_r['over_day']??0);
    $status_s=$shop_r['status'];
    mysqli_query($connect,"UPDATE users SET step='null', action='manage_$sid_esc' WHERE user_id='$cid'");
    
    $btn=[];
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

// Admin: Oylik to'lovni yoqish (to'lanmagan kassaga)
if(mb_stripos($data,"admin_activate_pay=")!==false&&in_array($cid,$admin)){
    $sid_esc=explode("=",$data)[1];
    mysqli_query($connect,"UPDATE users SET step='admin_set_days=$sid_esc=activate' WHERE user_id='$cid'");
    bot('editMessageText',['chat_id'=>$cid,'message_id'=>$mid,
        'text'=>"📆 <b>Necha kunga yoqish?</b>\n\nMasalan: 30 (1 oy), 90 (3 oy)\n\nKunlar sonini kiriting:",
        'parse_mode'=>'html']);
    exit;
}

// Admin: Muddatni uzaytirish (to'langan kassaga)
if(mb_stripos($data,"admin_extend=")!==false&&in_array($cid,$admin)){
    $sid_esc=explode("=",$data)[1];
    mysqli_query($connect,"UPDATE users SET step='admin_set_days=$sid_esc=extend' WHERE user_id='$cid'");
    bot('editMessageText',['chat_id'=>$cid,'message_id'=>$mid,
        'text'=>"📆 <b>Necha kunga uzaytirish?</b>\n\nMasalan: 30 (1 oy), 90 (3 oy)\n\nKunlar sonini kiriting:",
        'parse_mode'=>'html']);
    exit;
}

// Admin: Kunlar sonini qabul qilish
if(mb_stripos($step,"admin_set_days=")!==false&&in_array($cid,$admin)){
    $parts_s=explode("=",$step); $sid_esc=$parts_s[1]; $action_type=$parts_s[2];
    $days_inp=preg_replace('/[^0-9]/','',trim($text));
    if(!$days_inp||$days_inp<1){
        bot('sendMessage',['chat_id'=>$cid,'text'=>"❌ Noto'g'ri son! Qayta kiriting."]);
        exit;
    }
    $shop_r=mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM shops WHERE shop_id='$sid_esc'"));
    $old_over=(int)($shop_r['over_day']??0);
    $new_over=$old_over+(int)$days_inp;
    
    mysqli_query($connect,"UPDATE shops SET over_day='$new_over', month_status='Toʻlandi' WHERE shop_id='$sid_esc'");
    mysqli_query($connect,"UPDATE users SET step='null' WHERE user_id='$cid'");
    
    $nomi=base64_decode($shop_r['shop_name']);
    $owner_id=$shop_r['user_id'];
    $action_txt=($action_type==='activate')?'Yoqildi':'Uzaytirildi';
    
    bot('sendMessage',['chat_id'=>$cid,
        'text'=>"✅ <b>$action_txt!</b>\n\n🏪 <b>$nomi</b>\n🆔 Shop ID: <code>$sid_esc</code>\n➕ Qo'shildi: <b>$days_inp kun</b>\n📆 Jami qolgan: <b>$new_over kun</b>",
        'parse_mode'=>'html','reply_markup'=>$panel]);
    
    // Kassa egasiga xabar
    bot('sendMessage',['chat_id'=>$owner_id,
        'text'=>"✅ <b>Kassangiz muddati uzaytirildi!</b>\n\n🏪 <b>$nomi</b>\n📆 Qolgan kun: <b>$new_over</b>",
        'parse_mode'=>'html']);
    exit;
}

// ============================================
// ADMIN: KASSA BAN
// ============================================
if($text=="🚫 Kassa ban"&&in_array($cid,$admin)){
    mysqli_query($connect,"UPDATE users SET step='admin_ban_shop' WHERE user_id='$cid'");
    bot('sendMessage',['chat_id'=>$cid,
        'text'=>"🚫 <b>Kassa ban</b>\n\nBan qilish uchun Shop ID kiriting:",
        'parse_mode'=>'html',
        'reply_markup'=>json_encode(['resize_keyboard'=>true,'keyboard'=>[[['text'=>"🗄️ Boshqaruv"]]]])]);
    exit;
}
if($step=="admin_ban_shop"&&in_array($cid,$admin)){
    if($text=="🗄️ Boshqaruv"){ mysqli_query($connect,"UPDATE users SET step='null' WHERE user_id='$cid'"); exit; }
    $sid_esc=mysqli_real_escape_string($connect,trim($text));
    $shop_r=mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM shops WHERE shop_id='$sid_esc'"));
    if(!$shop_r){
        bot('sendMessage',['chat_id'=>$cid,'text'=>"❌ Bu Shop ID topilmadi!"]);
        exit;
    }
    $nomi=base64_decode($shop_r['shop_name']);
    $status_now=$shop_r['status'];
    mysqli_query($connect,"UPDATE users SET step='null' WHERE user_id='$cid'");
    
    $is_banned=($status_now==='banned');
    $btn_text=$is_banned?"✅ Banni olib tashlash":"🚫 Kassani ban qilish";
    $btn_data=$is_banned?"admin_unban=$sid_esc":"admin_ban=$sid_esc";
    $status_icon=$is_banned?"🚫 Banned!":"✅ Faol";
    
    bot('sendMessage',['chat_id'=>$cid,
        'text'=>"🏪 <b>$nomi</b>\n\n🆔 Shop ID: <code>$sid_esc</code>\n📊 Holat: <b>$status_icon</b>",
        'parse_mode'=>'html',
        'reply_markup'=>json_encode(['inline_keyboard'=>[
            [['text'=>$btn_text,'callback_data'=>$btn_data]],
            [['text'=>"⏪ Ortga",'callback_data'=>"noop"]],
        ]])]);
    exit;
}
if(mb_stripos($data,"admin_ban=")!==false&&in_array($cid,$admin)){
    $sid_esc=explode("=",$data)[1];
    $shop_r=mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM shops WHERE shop_id='$sid_esc'"));
    $nomi=base64_decode($shop_r['shop_name']);
    $owner_id=$shop_r['user_id'];
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
if(mb_stripos($data,"admin_unban=")!==false&&in_array($cid,$admin)){
    $sid_esc=explode("=",$data)[1];
    $shop_r=mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM shops WHERE shop_id='$sid_esc'"));
    $nomi=base64_decode($shop_r['shop_name']);
    $owner_id=$shop_r['user_id'];
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

// noop (sahifa raqami tugmasi uchun)
if($data=="noop"){
    bot('answerCallbackQuery',['callback_query_id'=>$qid]);
    exit;
}
?>
