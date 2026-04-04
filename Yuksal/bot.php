<?php

$sub_domen = "tolovavto-production.up.railway.app";
require (__DIR__ . "/../config.php");

// Sana va vaqt o'zgaruvchilarini aniqlash
$sana = date('Y-m-d');
$soat = date('H:i:s');

$administrator = getenv('ADMIN_ID') ?: "7678663640";
$admin = array($administrator);

function bot($method, $datas=[]){
    $ch = curl_init("https://api.telegram.org/bot".API_KEY."/".$method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $datas);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res);
}

function generate(){
    $arr = array('A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','R','S','T','U','V','X','Y','Z','1','2','3','4','5','6','7','8','9','0');
    $pass = "";
    for($i=0;$i<10;$i++) $pass .= $arr[rand(0,count($arr)-1)];
    return $pass;
}

// requests jadvalini yaratish
function createRequestsTable($connect) {
    $connect->query("CREATE TABLE IF NOT EXISTS `requests` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `chat_id` VARCHAR(255) NOT NULL,
        `user_id` VARCHAR(255) DEFAULT NULL,
        `type` VARCHAR(100) DEFAULT NULL,
        `status` VARCHAR(50) DEFAULT 'pending',
        `data` TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_chat_id` (`chat_id`)
    )");
}

// channels jadvalini tekshirish va to'g'irlash
function checkChannelsTable($connect) {
    // Jadval mavjudligini tekshirish
    $result = $connect->query("SHOW TABLES LIKE 'channels'");
    if($result->num_rows == 0) {
        // Yangi jadval yaratish
        $connect->query("CREATE TABLE `channels` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `channelID` VARCHAR(255) DEFAULT NULL,
            `chat_id` VARCHAR(255) DEFAULT NULL,
            `link` TEXT,
            `type` VARCHAR(50) DEFAULT 'lock',
            `title` TEXT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    } else {
        // Ustunlarni tekshirish
        $columns = $connect->query("SHOW COLUMNS FROM `channels`");
        $has_chat_id = false;
        while($col = $columns->fetch_assoc()) {
            if($col['Field'] == 'chat_id') $has_chat_id = true;
        }
        if(!$has_chat_id) {
            $connect->query("ALTER TABLE `channels` ADD COLUMN `chat_id` VARCHAR(255) DEFAULT NULL");
            $connect->query("UPDATE `channels` SET `chat_id` = `channelID` WHERE `channelID` IS NOT NULL");
        }
    }
}

// Jadval yaratish
createRequestsTable($connect);
checkChannelsTable($connect);

function joinchat($id){
    global $connect, $administrator;
    
    // Admin bo'lsa tekshiruv shart emas
    if($id == $administrator) return true;
    
    $result = $connect->query("SELECT * FROM `channels`");
    if($result->num_rows == 0) return true;
    
    $no_subs = 0; 
    $button = [];
    
    while($row = $result->fetch_assoc()){
        $type = $row['type'];
        $link = $row['link'];
        
        // chat_id yoki channelID dan foydalanish
        $channelID = !empty($row['chat_id']) ? $row['chat_id'] : $row['channelID'];
        if(empty($channelID)) continue;
        
        // Kanal nomini olish
        $get_chat = bot('getchat',['chat_id'=>$channelID]);
        $gettitle = isset($get_chat->result->title) ? $get_chat->result->title : "Kanal";
        
        if($type == "lock"){
            $member = bot('getChatMember',['chat_id'=>$channelID,'user_id'=>$id]);
            $status = isset($member->result->status) ? $member->result->status : 'left';
            if($status == "left" || $status == "kicked"){ 
                $button[] = ['text'=>"❌ $gettitle",'url'=>$link]; 
                $no_subs++; 
            } else {
                $button[] = ['text'=>"✅ $gettitle",'url'=>$link];
            }
        }
        elseif($type == "request"){
            $c = $connect->query("SELECT * FROM `requests` WHERE user_id='$id' AND chat_id='$channelID'");
            if($c && $c->num_rows > 0){
                $button[] = ['text'=>"✅ $gettitle",'url'=>$link];
            } else { 
                $button[] = ['text'=>"❌ $gettitle",'url'=>$link]; 
                $no_subs++; 
            }
        }
        elseif($type == "social"){
            $title = isset($row['title']) ? base64_decode($row['title']) : "Havola";
            $button[] = ['text'=>$title,'url'=>$link];
        }
    }
    
    if($no_subs > 0){
        $button[] = ['text'=>"🔄 Tekshirish",'callback_data'=>"result"];
        bot('sendMessage',[
            'chat_id'=>$id,
            'text'=>"⛔ Kanallarga obuna bo'ling:",
            'parse_mode'=>'html',
            'reply_markup'=>json_encode(['inline_keyboard'=>array_chunk($button,1)])
        ]);
        return false;
    }
    
    return true;
}

$update = json_decode(file_get_contents('php://input'));
$message  = $update->message ?? null;
$callback = $update->callback_query ?? null;
$bot = bot('getme',['bot'])->result->username ?? 'bot';

if(isset($message)){
    $contact = $message->contact ?? null;
    $number  = $contact->phone_number ?? null;
    $cid     = $message->chat->id;
    $text    = $message->text ?? '';
    $mid     = $message->message_id;
    $name    = $message->from->first_name ?? 'User';
}
if(isset($callback)){
    $data = $callback->data;
    $qid  = $callback->id;
    $cid  = $callback->message->chat->id;
    $mid  = $callback->message->message_id;
    $name = $callback->from->first_name ?? 'User';
}

$new_key = generate();

$res = mysqli_query($connect,"SELECT * FROM users WHERE user_id='$cid'");
$uid = null; $balance = 0; $payment = 0; $step = 'null';
while($a=mysqli_fetch_assoc($res)){
    $uid=$a['id']; 
    $balance=$a['balance']; 
    $payment=$a['deposit']; 
    $step=$a['step'] ?? 'null';
}

if(isset($message)){
    $check_user = mysqli_query($connect,"SELECT * FROM users WHERE user_id='$cid'");
    if(!mysqli_fetch_assoc($check_user)){
        mysqli_query($connect,"INSERT INTO users(user_id,balance,deposit,date,time,action) VALUES('$cid','0','0','$sana','$soat','member')");
    }
}

$menu   = json_encode(['resize_keyboard'=>true,'keyboard'=>[[['text'=>"🏪 Kassalarim"]],[['text'=>"💵 Hisobim"],['text'=>"💳 To'ldirish"]],[['text'=>"📕 Qoʻllanma"],['text'=>"📖 API Hujjatlar"]]]]);
$menu_p = json_encode(['resize_keyboard'=>true,'keyboard'=>[[['text'=>"🏪 Kassalarim"]],[['text'=>"💵 Hisobim"],['text'=>"💳 To'ldirish"]],[['text'=>"📕 Qoʻllanma"],['text'=>"📖 API Hujjatlar"]],[['text'=>"🗄️ Boshqaruv"]]]]);
$panel  = json_encode(['resize_keyboard'=>true,'keyboard'=>[[['text'=>"📊 Statistika"],['text'=>"📢 Kanallar"]],[['text'=>"🗑️ Kanal o'chirish"]],[['text'=>"👤 Foydalanuvchi"],['text'=>"📨 Xabar yuborish"]],[['text'=>"🔗 Kassa ulash"]],[['text'=>"⏪ Ortga"]]]]);
$back   = json_encode(['resize_keyboard'=>true,'keyboard'=>[[['text'=>"⏪ Ortga"]]]]);
$m = in_array($cid,$admin) ? $menu_p : $menu;

// Obuna tekshirish
if($data=="result"){
    bot('DeleteMessage',['chat_id'=>$cid,'message_id'=>$mid]);
    if(joinchat($cid)==true) bot('SendMessage',['chat_id'=>$cid,'text'=>"✅ <b>Tasdiqlandi!</b>",'parse_mode'=>'html','reply_markup'=>$m]);
    exit;
}

// /start
if($text=="/start"||$text=="⏪ Ortga"){
    if(joinchat($cid)==true){
        mysqli_query($connect,"UPDATE users SET step='null' WHERE user_id='$cid'");
        bot('sendMessage',['chat_id'=>$cid,'text'=>"👋🏻 <b>Assalomu alaykum $name!</b>\n\n@$bot botga xush kelibsiz.",'parse_mode'=>'html','reply_markup'=>$m]);
        exit;
    }
}

// Qo'llanma
if($text=="📕 Qoʻllanma"){
    bot('sendMessage',['chat_id'=>$cid,'text'=>"<b>📘 Qoʻllanma:</b>\n\nKassa qoʻshib admindan tasdiqlatasiz. Tasdiqlangach oylik toʻlovni toʻlaysiz va hisobni ulaysiz.\n\n📣 Yordam: @Matvafaevv",'parse_mode'=>'html','reply_markup'=>$m]);
    exit;
}

// Hisobim
if($text=="💵 Hisobim"){
    bot('sendMessage',['chat_id'=>$cid,'text'=>"👔 <b>Sizning hisobingiz!</b>\n\n• ID: <code>$uid</code>\n• Balans: <b>$balance</b> so'm\n• Kiritgan: <b>$payment</b> so'm",'parse_mode'=>'html','reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"🔄 Yangilash",'callback_data'=>"Hisobim"]]]])]);
    exit;
}
if($data=="Hisobim"){
    $a2=mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM users WHERE user_id='$cid'"));
    bot('editMessageText',['chat_id'=>$cid,'message_id'=>$mid,'text'=>"👔 <b>Sizning hisobingiz!</b>\n\n• ID: <code>".$a2['id']."</code>\n• Balans: <b>".$a2['balance']."</b> so'm\n• Kiritgan: <b>".$a2['deposit']."</b> so'm",'parse_mode'=>'html','reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"🔄 Yangilash",'callback_data'=>"Hisobim"]]]])]);
    exit;
}

// To'ldirish
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
    $amount=intval(trim($text));
    if($amount<1000){ bot('sendMessage',['chat_id'=>$cid,'text'=>"⚠️ <b>Minimal 1000 so'm!</b>",'parse_mode'=>'html','reply_markup'=>$back]); exit; }

    $active=mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM payments WHERE user_id='$cid' AND status='pending'"));
    if($active){
        bot('sendMessage',['chat_id'=>$cid,'text'=>"✅ <b>Faol amaliyot bor!</b>\n\n💵 To'lash kerak: <b>".$active['amount']."</b> so'm\n⚠️ Aynan shu miqdorni to'lang!",'parse_mode'=>'html','reply_markup'=>json_encode(['inline_keyboard'=>[
            [['text'=>'💵 Miqdorni nusxalash','copy_text'=>['text'=>$active['amount']]]],
            [['text'=>'💳 Kartani nusxalash','copy_text'=>['text'=>'5614 6835 8227 9246']]],
            [['text'=>"✅ To'lovni tekshirish",'callback_data'=>"check_pay=".$active['amount']."=".$active['used_order']]],
            [['text'=>"❌ Bekor qilish",'callback_data'=>"cancelpay=".$active['id']]],
        ]])]);
        mysqli_query($connect,"UPDATE users SET step='null' WHERE user_id='$cid'");
        exit;
    }

    if(mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM payments WHERE amount='$amount' AND status='pending'"))){
        bot('sendMessage',['chat_id'=>$cid,'text'=>"❌ <b>$amount</b> so'm hozirda band!\n\nBoshqa miqdor kiriting.",'parse_mode'=>'html','reply_markup'=>$back]);
        mysqli_query($connect,"UPDATE users SET step='uzcard_auto' WHERE user_id='$cid'");
        exit;
    }

    $order=generate();
    mysqli_query($connect,"INSERT INTO payments (message_id,amount,status,used_order,user_id,created_at) VALUES('user_{$cid}_{$order}','$amount','pending','$order','$cid',NOW())");
    bot('sendMessage',['chat_id'=>$cid,'text'=>"➡️ <b>To'lov kartasi:</b> <code>5614 6835 8227 9246</code>\n\n💵 Miqdori: <code>$amount</code> so'm\n⏰ Kutish vaqti: <b>5</b> daqiqa\n✅ To'lov avtomatik qabul qilinadi\n\n👉🏻 Aynan <b>$amount</b> so'm yuboring!",'parse_mode'=>'html','reply_markup'=>json_encode(['inline_keyboard'=>[
        [['text'=>'💵 Miqdorni nusxalash','copy_text'=>['text'=>$amount]]],
        [['text'=>'💳 Kartani nusxalash','copy_text'=>['text'=>'5614 6835 8227 9246']]],
        [['text'=>"✅ To'lovni tekshirish",'callback_data'=>"check_pay=$amount=$order"]],
    ]])]);
    mysqli_query($connect,"UPDATE users SET step='null' WHERE user_id='$cid'");
    exit;
}

// To'lovni tekshirish
if(mb_stripos($data,"check_pay=")!==false){
    // Avval botni "qotib qolishdan" saqlash uchun javob beramiz
    bot('answerCallbackQuery',['callback_query_id'=>$qid,'text'=>"🔄 Tekshirilmoqda..."]);

    $parts=explode("=",$data); $amount_c=$parts[1]; $order_c=$parts[2];

    // payments jadvalidan tekshirish (status.php ga murojaat)
    $ch = curl_init("https://$sub_domen/status.php?amount=$amount_c&order=$order_c");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $res = curl_exec($ch);
    curl_close($ch);

    $json = json_decode($res, true);

    if(($json['result']['status']??'')==="paid"){
        mysqli_query($connect,"UPDATE users SET balance=balance+$amount_c, deposit=deposit+$amount_c WHERE user_id='$cid'");
        bot('editMessageText',['chat_id'=>$cid,'message_id'=>$mid,'text'=>"✅ <b>To'lov tasdiqlandi!</b>\n\n💵 <b>".number_format($amount_c,0,'.',' ')."</b> so'm hisobingizga qo'shildi!",'parse_mode'=>'html','reply_markup'=>json_encode(['inline_keyboard'=>[]])]);
    }else{
        bot('editMessageText',['chat_id'=>$cid,'message_id'=>$mid,'text'=>"⏳ <b>To'lov hali kelmagan!</b>\n\n5 daqiqa ichida to'lang va qayta tekshiring.",'parse_mode'=>'html','reply_markup'=>json_encode(['inline_keyboard'=>[
            [['text'=>"🔄 Qayta tekshirish",'callback_data'=>"check_pay=$amount_c=$order_c"]],
            [['text'=>"❌ Bekor qilish",'callback_data'=>"cancel_by_order=$order_c"]],
        ]])]);
    }
}

// Bekor qilish
if(mb_stripos($data,"cancelpay=")!==false){
    $id=explode("=",$data)[1];
    bot('SendMessage',['chat_id'=>$cid,'text'=>"🤔 <b>Bekor qilmoqchimisiz?</b>",'parse_mode'=>'html','reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"✅ Ha",'callback_data'=>"cancelpayy=$id"],['text'=>"❌ Yo'q",'callback_data'=>"delete_data"]]]])]);
}
if(mb_stripos($data,"cancelpayy=")!==false){
    $id=explode("=",$data)[1];
    mysqli_query($connect,"DELETE FROM payments WHERE id='$id' AND user_id='$cid'");
    bot('DeleteMessage',['chat_id'=>$cid,'message_id'=>$mid]);
    bot('SendMessage',['chat_id'=>$cid,'text'=>"❌ <b>Bekor qilindi!</b>",'parse_mode'=>'html','reply_markup'=>$m]);
}
if($data=="delete_data") bot('DeleteMessage',['chat_id'=>$cid,'message_id'=>$mid]);

// Order boyicha bekor qilish
if(mb_stripos($data,"cancel_by_order=")!==false){
    $order_c=explode("=",$data)[1];
    mysqli_query($connect,"DELETE FROM payments WHERE used_order='".mysqli_real_escape_string($connect,$order_c)."' AND user_id='$cid'");
    bot("editMessageText",["chat_id"=>$cid,"message_id"=>$mid,"text"=>"❌ <b>Tolov bekor qilindi!</b>","parse_mode"=>"html","reply_markup"=>json_encode(["inline_keyboard"=>[]])]);
}

// API Docs
if($text=="📖 API Hujjatlar"){
    bot('sendMessage',['chat_id'=>$cid,'text'=>"<b>API:</b> https://$sub_domen/api",'disable_web_page_preview'=>true,'parse_mode'=>'html','reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"📁 API Docs",'url'=>"https://$sub_domen/docs.html"]]]])]);
    exit;
}

// Kassalarim
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

// Kassa ko'rish
if(mb_stripos($data,"kassa_set=")!==false){
    $id=explode("=",$data)[1];
    $rew=mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM shops WHERE id='$id'"));
    $nomi=base64_decode($rew['shop_name']);
    $shop_id=$rew['shop_id']; $shop_key=$rew['shop_key'];
    $address=$rew['shop_address']; $status=$rew['status'];
    $shop_balance=$rew['shop_balance'];
    $month_status=$rew['month_status']??"Toʻlanmagan!";
    $over=$rew['over_day']??"0";
    $shop_info=base64_decode($rew['shop_info']);
    $phone=$rew['phone']??null;
    $card_num=$rew['card_number']??null;
    $card_bank=$rew['card_bank']??null;
    $webhook_url=$rew['webhook_url']??null;
    $kb=['inline_keyboard'=>[]]; $confirm_text="";

    if($status=="waiting"){
        $icon="🔄 Kutilmoqda...";
        $kb['inline_keyboard'][]=[['text'=>"⏪ Ortga",'callback_data'=>"Kassalarim"]];
    }elseif($status=="confirm"){
        $card_info=$card_num?"<code>$card_num</code>".($card_bank?" ($card_bank)":""):"Kiritilmagan";
        $wh_info=$webhook_url?"<code>$webhook_url</code>":" Kiritilmagan";
        $confirm_text="\n📆 Oylik toʻlovga: <b>$over</b> kun\n🔎 Holat: <b>$month_status</b>\n📞 Raqam: <code>".($phone?:"Yo'q")."</code>\n💳 Karta: $card_info\n🔗 Webhook: $wh_info\n🏦 Aylanma: <b>$shop_balance</b> so'm\n";
        $need_pay=(empty($month_status)||$month_status=="Toʻlanmagan!");
        if($need_pay){
            $icon="📛 Toʻlanmagan!";
            $kb['inline_keyboard'][]=[['text'=>"💵 Oylik toʻlov",'callback_data'=>"kassa_payment=$shop_id=$id"]];
        }else{
            if(empty($phone)){
                $icon="📛 Hisob ulanmagan!";
                $kb['inline_keyboard'][]=[['text'=>"⚠️ Keyni yangilash",'callback_data'=>"new_key=$shop_id"]];
                $kb['inline_keyboard'][]=[['text'=>"🔗 Kassa ulash",'callback_data'=>"req_connect=$shop_id=$cid"]];
            }else{
                $icon="✅ Faol!";
                $kb['inline_keyboard'][]=[['text'=>"⚠️ Keyni yangilash",'callback_data'=>"new_key=$shop_id"]];
                $kb['inline_keyboard'][]=[['text'=>"💳 Karta kiritish",'callback_data'=>"set_card=$shop_id=$id"]];
                $kb['inline_keyboard'][]=[['text'=>"🔗 Webhook kiritish",'callback_data'=>"set_webhook=$shop_id=$id"]];
                $kb['inline_keyboard'][]=[['text'=>"🗑️ Kassani o'chirish",'callback_data'=>"del_kassa=$shop_id=$id"]];
                $kb['inline_keyboard'][]=[['text'=>"💰 Toʻlovlar tarixi",'callback_data'=>"kassa_history=$shop_id=$id"]];
            }
        }
        $kb['inline_keyboard'][]=[['text'=>"⏪ Ortga",'callback_data'=>"Kassalarim"]];
    }elseif($status=="canceled"){
        $icon="⛔ Bekor qilingan!";
        $kb['inline_keyboard'][]=[['text'=>"⏪ Ortga",'callback_data'=>"Kassalarim"]];
    }

    bot('editMessageText',['chat_id'=>$cid,'message_id'=>$mid,'text'=>"<b>$nomi ($icon)</b>\n$confirm_text\n🆔 Shop ID: <code>$shop_id</code>\n🔑 Shop Key: <code>$shop_key</code>\n🔗 Manzil: <b>$address</b>\n📖 Ma'lumot: $shop_info",'parse_mode'=>'html','reply_markup'=>json_encode($kb)]);
}

// Karta kiritish
if(mb_stripos($data,"set_card=")!==false){
    $parts=explode("=",$data); $shop_id_c=$parts[1]; $set_id_c=$parts[2];
    mysqli_query($connect,"UPDATE users SET step='set_card_num=$shop_id_c=$set_id_c' WHERE user_id='$cid'");
    bot('editMessageText',['chat_id'=>$cid,'message_id'=>$mid,'text'=>"💳 <b>16 xonali karta raqamini kiriting:</b>\n\nMasalan: 8600 1234 5678 9012",'parse_mode'=>'html']);
}

if(mb_stripos($step,"set_card_num=")!==false){
    $parts=explode("=",$step); $shop_id_c=$parts[1]; $set_id_c=$parts[2];
    $card=preg_replace('/\s+/','',$text);
    if(!preg_match('/^\d{16}$/',$card)){
        bot('sendMessage',['chat_id'=>$cid,'text'=>"❌ <b>Noto'g'ri format!</b>\n\n16 ta raqam kiriting.",'parse_mode'=>'html']);
        exit;
    }
    mysqli_query($connect,"UPDATE users SET step='set_card_bank=$shop_id_c=$set_id_c=$card' WHERE user_id='$cid'");
    bot('sendMessage',['chat_id'=>$cid,'text'=>"✅ Karta: <code>$card</code>\n\n🏦 <b>Bank turini tanlang:</b>",'parse_mode'=>'html','reply_markup'=>json_encode(['inline_keyboard'=>[
        [['text'=>"🟢 Humo",'callback_data'=>"card_bank=Humo=$shop_id_c=$set_id_c=$card"]],
        [['text'=>"🔵 UzCard",'callback_data'=>"card_bank=UzCard=$shop_id_c=$set_id_c=$card"]],
        [['text'=>"🟡 Visa",'callback_data'=>"card_bank=Visa=$shop_id_c=$set_id_c=$card"]],
        [['text'=>"🔴 MasterCard",'callback_data'=>"card_bank=MasterCard=$shop_id_c=$set_id_c=$card"]],
    ]])]);
}

if(mb_stripos($data,"card_bank=")!==false){
    $parts=explode("=",$data); $bank=$parts[1]; $shop_id_c=$parts[2]; $set_id_c=$parts[3]; $card=$parts[4];
    mysqli_query($connect,"UPDATE shops SET card_number='$card', card_bank='$bank' WHERE shop_id='$shop_id_c'");
    mysqli_query($connect,"UPDATE users SET step='null' WHERE user_id='$cid'");
    $masked=substr($card,0,4)." **** **** ".substr($card,-4);
    bot('editMessageText',['chat_id'=>$cid,'message_id'=>$mid,'text'=>"✅ <b>Karta saqlandi!</b>\n\n💳 $masked\n🏦 Bank: $bank",'parse_mode'=>'html','reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"⏪ Ortga",'callback_data'=>"kassa_set=$set_id_c"]]]])]);
}

// Webhook kiritish
if(mb_stripos($data,"set_webhook=")!==false){
    $parts=explode("=",$data); $shop_id_w=$parts[1]; $set_id_w=$parts[2];
    mysqli_query($connect,"UPDATE users SET step='set_webhook=$shop_id_w=$set_id_w' WHERE user_id='$cid'");
    bot('editMessageText',['chat_id'=>$cid,'message_id'=>$mid,'text'=>"🔗 <b>Webhook URL ni kiriting:</b>\n\nMasalan: https://sizning-sayt.uz/webhook\n\n<i>To'lov bo'lganda bot shu manzilga POST so'rov yuboradi.</i>",'parse_mode'=>'html']);
}

if(mb_stripos($step,"set_webhook=")!==false){
    $parts=explode("=",$step); $shop_id_w=$parts[1]; $set_id_w=$parts[2];
    if($text=="⏪ Ortga"){ mysqli_query($connect,"UPDATE users SET step='null' WHERE user_id='$cid'"); exit; }
    if(!filter_var($text,FILTER_VALIDATE_URL)){
        bot('sendMessage',['chat_id'=>$cid,'text'=>"❌ <b>Noto'g'ri URL!</b>\n\nhttps:// bilan boshlanishi kerak.",'parse_mode'=>'html']);
        exit;
    }
    $wh_esc=mysqli_real_escape_string($connect,$text);
    mysqli_query($connect,"UPDATE shops SET webhook_url='$wh_esc' WHERE shop_id='$shop_id_w'");
    mysqli_query($connect,"UPDATE users SET step='null' WHERE user_id='$cid'");
    bot('sendMessage',['chat_id'=>$cid,'text'=>"✅ <b>Webhook saqlandi!</b>\n\n<code>$text</code>",'parse_mode'=>'html','reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"⏪ Ortga",'callback_data'=>"kassa_set=$set_id_w"]]]])]);
}

// Kassani o'chirish
if(mb_stripos($data,"del_kassa=")!==false){
    $parts=explode("=",$data); $shop_id_d=$parts[1]; $set_id_d=$parts[2];
    bot('editMessageText',['chat_id'=>$cid,'message_id'=>$mid,'text'=>"🗑️ <b>Kassani o'chirmoqchimisiz?</b>\n\n⚠️ Bu amalni ortga qaytarib bo'lmaydi!",'parse_mode'=>'html','reply_markup'=>json_encode(['inline_keyboard'=>[
        [['text'=>"✅ Ha, o'chir",'callback_data'=>"del_kassa_yes=$shop_id_d"]],
        [['text'=>"❌ Yo'q",'callback_data'=>"kassa_set=$set_id_d"]],
    ]])]);
}
if(mb_stripos($data,"del_kassa_yes=")!==false){
    $shop_id_d=explode("=",$data)[1];
    mysqli_query($connect,"DELETE FROM shops WHERE shop_id='$shop_id_d' AND user_id='$cid'");
    bot('editMessageText',['chat_id'=>$cid,'message_id'=>$mid,'text'=>"🗑️ <b>Kassa o'chirildi!</b>",'parse_mode'=>'html','reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"⏪ Kassalarim",'callback_data'=>"Kassalarim"]]]])]);
}

// Foydalanuvchi "Kassa ulash" tugmasi
if(mb_stripos($data,"req_connect=")!==false){
    $parts=explode("=",$data); $shop_id_r=$parts[1]; $user_id_r=$parts[2];
    bot('sendMessage',['chat_id'=>$administrator,'text'=>"📨 <b>Kassa ulash so'rovi!</b>\n\n👤 Foydalanuvchi ID: <code>$user_id_r</code>\n🏪 Kassa ID: <code>$shop_id_r</code>",'parse_mode'=>'html','reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"🔗 Ulash",'callback_data'=>"admin_connect=$shop_id_r=$user_id_r"]]]])]);
    bot('answerCallbackQuery',['callback_query_id'=>$qid,'text'=>"✅ So'rov adminga yuborildi!",'show_alert'=>true]);
    bot('sendMessage',['chat_id'=>$cid,'text'=>"📨 <b>Kassa ulash uchun Administratorga murojaat qiling</b>\n\n👤 Sizning ID: <code>$cid</code>\n📧 Kassa uchun e-mail so'raladi, va sizga tushuntiriladi hamda ulab beriladi.\n\n👨‍💻 Admin: @Matvafaevv",'parse_mode'=>'html']);
}

// Admin paneldan kassa ulash
if($text=="🔗 Kassa ulash"&&in_array($cid,$admin)){
    mysqli_query($connect,"UPDATE users SET step='admin_connect_step' WHERE user_id='$cid'");
    bot('sendMessage',['chat_id'=>$cid,'text'=>"👤 <b>Foydalanuvchi ID ni kiriting:</b>",'parse_mode'=>'html','reply_markup'=>json_encode(['resize_keyboard'=>true,'keyboard'=>[[['text'=>"🗄️ Boshqaruv"]]]])]);
}
if($step=="admin_connect_step"&&in_array($cid,$admin)){
    if($text=="🗄️ Boshqaruv"){ mysqli_query($connect,"UPDATE users SET step='null' WHERE user_id='$cid'"); exit; }
    $target=intval($text);
    $shops_r=mysqli_query($connect,"SELECT * FROM shops WHERE user_id='$target'");
    if(mysqli_num_rows($shops_r)==0){
        bot('sendMessage',['chat_id'=>$cid,'text'=>"❌ Bu foydalanuvchida kassa topilmadi!",'reply_markup'=>$panel]);
        mysqli_query($connect,"UPDATE users SET step='null' WHERE user_id='$cid'"); exit;
    }
    $key2=[];
    while($sh=mysqli_fetch_assoc($shops_r)) $key2[]=[['text'=>"🏪 ".base64_decode($sh['shop_name']),'callback_data'=>"do_connect=".$sh['shop_id']."=$target"]];
    mysqli_query($connect,"UPDATE users SET step='null' WHERE user_id='$cid'");
    bot('sendMessage',['chat_id'=>$cid,'text'=>"✅ <b>Kassani tanlang:</b>",'parse_mode'=>'html','reply_markup'=>json_encode(['inline_keyboard'=>$key2])]);
}
if(mb_stripos($data,"admin_connect=")!==false){
    $parts=explode("=",$data); $shop_id_ac=$parts[1]; $user_id_ac=$parts[2];
    $shops_r=mysqli_query($connect,"SELECT * FROM shops WHERE shop_id='$shop_id_ac'");
    $key2=[];
    while($sh=mysqli_fetch_assoc($shops_r)) $key2[]=[['text'=>"🏪 ".base64_decode($sh['shop_name']),'callback_data'=>"do_connect=$shop_id_ac=$user_id_ac"]];
    bot('editMessageText',['chat_id'=>$cid,'message_id'=>$mid,'text'=>"✅ <b>Kassani tanlang:</b>",'parse_mode'=>'html','reply_markup'=>json_encode(['inline_keyboard'=>$key2])]);
}
if(mb_stripos($data,"do_connect=")!==false){
    $parts=explode("=",$data); $shop_id_dc=$parts[1]; $user_id_dc=$parts[2];
    mysqli_query($connect,"UPDATE users SET step='do_connect_phone=$shop_id_dc=$user_id_dc' WHERE user_id='$cid'");
    bot('editMessageText',['chat_id'=>$cid,'message_id'=>$mid,'text'=>"📞 <b>Telefon raqamini kiriting:</b>\n\nMasalan: 998901234567",'parse_mode'=>'html']);
}
if(mb_stripos($step,"do_connect_phone=")!==false&&in_array($cid,$admin)){
    $parts=explode("=",$step); $shop_id_dc=$parts[1]; $user_id_dc=$parts[2];
    $phone_num=preg_replace('/[^0-9]/','',trim($text));
    mysqli_query($connect,"UPDATE shops SET phone='$phone_num', month_status='Toʻlandi', over_day=30 WHERE shop_id='$shop_id_dc'");
    mysqli_query($connect,"UPDATE users SET step='null' WHERE user_id='$cid'");
    bot('sendMessage',['chat_id'=>$cid,'text'=>"✅ <b>Kassa ulandi!</b>\n\n🏪 Kassa: <code>$shop_id_dc</code>\n📞 Raqam: <code>$phone_num</code>",'parse_mode'=>'html','reply_markup'=>$panel]);
    bot('sendMessage',['chat_id'=>$user_id_dc,'text'=>"✅ <b>Kassangiz ulandi! To'lovlar avtomatik qabul qilinadi.</b>",'parse_mode'=>'html']);
}

// To'lovlar tarixi
if(mb_stripos($data,"kassa_history=")!==false){
    $parts=explode("=",$data); $set_id=$parts[2];
    $res=mysqli_query($connect,"SELECT * FROM payments WHERE status='used' ORDER BY created_at DESC LIMIT 20");
    if(mysqli_num_rows($res)>0){
        $list=""; $i=0;
        while($row=mysqli_fetch_assoc($res)){
            $i++;
            $list.="<b>$i</b>. 💵 ".$row['amount']." so'm\n🏦 ".($row['merchant']?:"Noma'lum")."\n📆 ".($row['date']?:$row['created_at'])."\n\n";
        }
        bot('editMessageText',['chat_id'=>$cid,'message_id'=>$mid,'text'=>"✅ <b>Tasdiqlangan toʻlovlar:</b>\n\n$list",'parse_mode'=>'html','reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"⏪ Ortga",'callback_data'=>"kassa_set=$set_id"]]]])]);
    }else{
        bot('answerCallbackQuery',['callback_query_id'=>$qid,'text'=>"❌ To'lovlar topilmadi!",'show_alert'=>true]);
    }
}

// Oylik to'lov — admin 0, user 20000
if(mb_stripos($data,"kassa_payment=")!==false){
    $parts=explode("=",$data); $shop_id_p=$parts[1]; $set_id_p=$parts[2];
    $rew=mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM users WHERE user_id='$cid'"));
    $bal=$rew['balance'];
    $req=in_array($cid,$admin)?0:20000;
    if($bal>=$req){
        mysqli_query($connect,"UPDATE users SET balance=".($bal-$req)." WHERE user_id='$cid'");
        mysqli_query($connect,"UPDATE shops SET over_day=30, month_status='Toʻlandi' WHERE id='$set_id_p'");
        bot('editMessageText',['chat_id'=>$cid,'message_id'=>$mid,'text'=>"✅ <b>Oylik toʻlov qilindi!</b>",'parse_mode'=>'html','reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"⏪ Ortga",'callback_data'=>"kassa_set=$set_id_p"]]]])]);
    }else{
        bot('answerCallbackQuery',['callback_query_id'=>$qid,'text'=>"⚠ Hisobda yetarli mablagʻ yoʻq. Kerak: 20,000 soʻm",'show_alert'=>true]);
    }
}

// Key yangilash
if(mb_stripos($data,"new_key=")!==false){
    $id=explode("=",$data)[1]; $k=generate();
    mysqli_query($connect,"UPDATE shops SET shop_key='$k' WHERE shop_id='$id'");
    bot('editMessageText',['chat_id'=>$cid,'message_id'=>$mid,'text'=>"✅ <b>Kalit yangilandi!</b> <code>$k</code>",'parse_mode'=>'HTML']);
}

// Kassa qo'shish
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
        bot('sendmessage',['chat_id'=>$cid,'text'=>"✅ <b>Adminга yuborildi! Kuting.</b>",'parse_mode'=>'html','reply_markup'=>$m]);
        mysqli_query($connect,"UPDATE users SET step='null' WHERE user_id='$cid'");
        bot('sendmessage',['chat_id'=>$administrator,'text'=>"✅ <b>Yangi kassa!</b>\n\n🆔 ID: $sid\n🔑 Key: $skey\n🛍️ Nom: <b>".base64_decode($name)."</b>\n🔗 Manzil: $address\n📖 Haqida: <b>$text</b>\n\n📅 $sana ⏰ $soat\n👤 User: <code>$cid</code>",'parse_mode'=>'html','reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"✅ Tasdiqlash",'callback_data'=>"confirm=$sid"]],[['text'=>"⛔ Bekor qilish",'callback_data'=>"canceled=$sid"]]]])]);
        exit;
    }
}

if(mb_stripos($data,"confirm=")!==false){
    $id=explode("=",$data)[1];
    $rew=mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM shops WHERE shop_id='$id'"));
    bot('editMessageText',['chat_id'=>$cid,'message_id'=>$mid,'text'=>"✅ <b>Tasdiqlandi! #$id</b>",'parse_mode'=>'html']);
    bot('sendmessage',['chat_id'=>$rew['user_id'],'text'=>"✅ <b>Kassangiz tasdiqlandi! #$id</b>",'parse_mode'=>'html','reply_markup'=>$m]);
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

// Admin panel
if($text=="🗄️ Boshqaruv"||$text=="/panel"){
    if(in_array($cid,$admin)){
        mysqli_query($connect,"UPDATE users SET step='null' WHERE user_id='$cid'");
        bot('sendMessage',['chat_id'=>$cid,'text'=>"<b>Administrator paneli!</b>",'parse_mode'=>'html','reply_markup'=>$panel]);
        exit;
    }
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
    if(!mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM `send`"))){
        bot('SendMessage',['chat_id'=>$cid,'text'=>"<b>Xabarni kiriting:</b>",'parse_mode'=>'html','reply_markup'=>json_encode(['resize_keyboard'=>true,'keyboard'=>[[['text'=>"🗄️ Boshqaruv"]]]])]);
        mysqli_query($connect,"UPDATE users SET step='send' WHERE user_id='$cid'");
    }else{
        bot('sendMessage',['chat_id'=>$cid,'text'=>"Hozirda yuborish davom etmoqda!"]);
    }
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
}
if($data=="socialnetwork"){
    bot('deleteMessage',['chat_id'=>$cid,'message_id'=>$mid]);
    bot('sendMessage',['chat_id'=>$cid,'text'=>"<b>Havola uchun nom:</b>",'parse_mode'=>'html','reply_markup'=>json_encode(['resize_keyboard'=>true,'keyboard'=>[[['text'=>"🗄️ Boshqaruv"]]]])]);
    mysqli_query($connect,"UPDATE users SET step='socialnetwork_step1' WHERE user_id='$cid'");
}
if($step=="socialnetwork_step1"){
    mysqli_query($connect,"UPDATE users SET step='socialnetwork_step2', action='".mysqli_real_escape_string($connect,$text)."' WHERE user_id='$cid'");
    bot('sendMessage',['chat_id'=>$cid,'text'=>"✅ Qabul!\n\nHavolani kiriting:",'parse_mode'=>'html']);
}
if($step=="socialnetwork_step2"){
    $nr=mysqli_fetch_assoc(mysqli_query($connect,"SELECT action FROM users WHERE user_id='$cid'"));
    mysqli_query($connect,"INSERT INTO `channels` (type,link,title,channelID) VALUES('social','".mysqli_real_escape_string($connect,$text)."','".base64_encode($nr['action'])."','')");
    bot('sendMessage',['chat_id'=>$cid,'text'=>"✅ <b>Kanal qo'shildi!</b>",'parse_mode'=>'html','reply_markup'=>$panel]);
    mysqli_query($connect,"UPDATE users SET step='null' WHERE user_id='$cid'");
}
if(mb_stripos($data,"request-")!==false){
    $type=explode("-",$data)[1];
    mysqli_query($connect,"UPDATE users SET step='qosh', action='$type' WHERE user_id='$cid'");
    bot('sendMessage',['chat_id'=>$cid,'text'=>"<b>Kanaldan \"forward\" xabar yuboring:</b>",'parse_mode'=>'html','reply_markup'=>json_encode(['resize_keyboard'=>true,'keyboard'=>[[['text'=>"🗄️ Boshqaruv"]]]])]);
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
    }else{
        bot('sendMessage',['chat_id'=>$cid,'text'=>"<b>Kanal yo'q!</b>",'parse_mode'=>'html']);
    }
}
if(stripos($data,"delchan=")!==false){
    $ex=explode("=",$data)[1];
    $connect->query("DELETE FROM channels WHERE channelID='$ex'");
    bot('editMessageText',['chat_id'=>$cid,'message_id'=>$mid,'text'=>"✅ <b>Kanal oʻchirildi!</b>",'parse_mode'=>'html']);
}
?>
