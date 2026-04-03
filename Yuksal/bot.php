<?php

$sub_domen = "tgbots.uz";

require (__DIR__ . "/../config.php");

$administrator = "10";
$admin = array($administrator,$admins);


function bot($method,$datas=[]){
$ch = curl_init("https://api.telegram.org/bot". API_KEY ."/". $method);
curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
curl_setopt($ch,CURLOPT_POSTFIELDS,$datas);
$res = curl_exec($ch);
return json_decode($res);
}

function getAdmin($chat){
$url = "https://api.telegram.org/bot".API_KEY."/getChatAdministrators?chat_id=@".$chat;
$result = file_get_contents($url);
$result = json_decode ($result);
return $result->ok;
}

function generate(){
$arr = array('A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','R','S','T','U','V','X','Y','Z','1','2','3','4','5','6','7','8','9','0');
$pass = "";
for($i = 0; $i < 10; $i++){
$index = rand(0, count($arr) - 1);
$pass .= $arr[$index];
}
return $pass;
}

function joinchat($id){
global $connect, $administrator;
$result = $connect->query("SELECT * FROM `channels`");
if($result->num_rows > 0 and $id != $administrator){
$no_subs = 0;
$button = [];
while ($row = $result->fetch_assoc()){
$type = $row['type'];
$link = $row['link'];
$channelID = $row['channelID'];
$title = $row['title'];
$gettitle = bot('getchat', ['chat_id' => $channelID])->result->title;
if($type == "lock" or $type == "request"){
if($type == "request"){
$check = $connect->query("SELECT * FROM `requests` WHERE id = '$id' AND chat_id = '$channelID'");
if($check->num_rows > 0){
$button[] = ['text' => "✅ $gettitle", 'url' => $link];
}else{
$button[] = ['text' => "❌ $gettitle", 'url' => $link];
$no_subs++;
}
} elseif($type == "lock"){
$check = bot('getChatMember', ['chat_id' => $channelID, 'user_id' => $id])->result->status;
if($check == "left"){
$button[] = ['text' => "❌ $gettitle", 'url' => $link];
$no_subs++;
}else{
$button[] = ['text' => "✅ $gettitle", 'url' => $link];
}
}
} elseif($type == "social"){
$button[] = ['text' => base64_decode($title), 'url' => $link];
}
}
if($no_subs > 0){
$button[] = ['text' => "🔄 Tekshirish", 'callback_data' => "result"];
$keyboard2 = array_chunk($button, 1);
$keyboard = json_encode([
 'inline_keyboard' => $keyboard2,
]);
bot('sendMessage', [
'chat_id' => $id,
'text' => "⛔ Botdan foydalanish uchun, quyidagi kanallarga obuna bo'ling:",
'parse_mode' => 'html',
'reply_markup' => $keyboard
]);
exit;
} else return true;
} else return true;
}

function del($dir){
$ffs = scandir($dir);
foreach($ffs as $ff){
if($ff !='.' and $ff !='..'){
if(file_exists("$dir/$ff")){
unlink("$dir/$ff");
rmdir($dir);
}

if(is_dir($dir.'/'.$ff)){
del($dir.'/'.$ff);
rmdir($dir);
} 
}
rmdir($dir);
}
}

$update = json_decode(file_get_contents('php://input'));
$message = $update->message;
$callback = $update->callback_query;
$bot = bot('getme',['bot'])->result->username;

if(isset($message)){
$contact = $message->contact;
$number = $contact->phone_number;
$cid = $message->chat->id;
$Tc = $message->chat->type;
$text = $message->text;
$mid = $message->message_id;
$from_id = $message->from->id;
$name = $message->from->first_name;
$last = $message->from->last_name;
$photo = $message->photo;
$photo_id = $photo->file_id;
$photo_name = $photo->file_name;
$video = $message->video;
$file_id = $video->file_id;
$file_name = $video->file_name;
$file_size = $video->file_size;
$size = $file_size/1000;
$dtype = $video->mime_type;
$audio = $message->audio->file_id;
$voice = $message->voice->file_id;
$sticker = $message->sticker->file_id;
$video_note = $message->video_note->file_id;
$animation = $message->animation->file_id;
$caption = $message->caption;
}

if(isset($callback)){
$data = $callback->data;
$qid = $callback->id;
$cid = $callback->message->chat->id;
$Tc = $callback->message->chat->type;
$mid = $callback->message->message_id;
$from_id = $callback->from->id;
$name = $callback->from->first_name;
$last = $callback->from->last_name;
}

$botdel = $update->my_chat_member->new_chat_member;
$botdel_id = $update->my_chat_member->from->id;
$userstatus = $botdel->status;

$chat_join_request = $update->chat_join_request;
$join_chat_id = $chat_join_request->chat->id;
$join_user_id = $chat_join_request->from->id;

$new_key = generate();

//users
$res = mysqli_query($connect,"SELECT*FROM users WHERE user_id = $cid");
while($a = mysqli_fetch_assoc($res)){
$uid = $a['id'];
$user_id = $a['user_id'];
$balance = $a['balance'];
$payment = $a['deposit'];
$regdate = $a['date'];
$keys = $a['apikey'];
$step = $a['step'];
}


if(isset($message)){
$result = mysqli_query($connect,"SELECT * FROM users WHERE user_id = $cid");
$rew = mysqli_fetch_assoc($result);
if($rew){
}else{
mysqli_query($connect,"INSERT INTO users(`user_id`,`balance`, `deposit`,`date`, `time`,`action`) VALUES ('$cid','0','0','$sana', '$soat','member')");
}
}

$menu=json_encode([
'resize_keyboard'=>true,
'keyboard'=>[
[['text'=>"🏪 Kassalarim"]],
[['text'=>"💵 Hisobim"],['text'=>"💳 To'ldirish"]],
[['text'=>"📕 Qoʻllanma"],['text'=>"📖 API Hujjatlar"]],
]]);

$menu_p=json_encode([
'resize_keyboard'=>true,
'keyboard'=>[
[['text'=>"🏪 Kassalarim"]],
[['text'=>"💵 Hisobim"],['text'=>"💳 To'ldirish"]],
[['text'=>"📕 Qoʻllanma"],['text'=>"📖 API Hujjatlar"]],
[['text'=>"🗄️ Boshqaruv"]],
]]);

$panel=json_encode([
'resize_keyboard'=>true,
'keyboard'=>[
[['text'=>"📊 Statistika"],['text'=>"📢 Kanallar"]],
[['text'=>"🗑️ Kanal o'chirish"]],
[['text'=>"👤 Foydalanuvchi"],['text'=>"📨 Xabar yuborish"]],
[['text'=>"⏪ Ortga"]],
]]);

$back=json_encode([
'resize_keyboard'=>true,
'keyboard'=>[
[['text'=>"⏪ Ortga"]],
]]);

if(in_array($cid,$admin)){
$m=$menu_p;
}else{
$m=$menu;
}

if($data == "result"){
bot('DeleteMessage',[
'chat_id'=>$cid,
'message_id'=>$mid,
]);
if(joinchat($cid) == true){
bot('SendMessage',[
'chat_id'=>$cid,
'text'=>"✅ <b>Kanallarga obuna bo'lganingiz tasdiqlandi!</b>",
'parse_mode'=>'html',
'reply_markup'=>$m
]);
exit;
}
}

if($text == "/start" or $text == "⏪ Ortga"){
if(joinchat($cid) == true){
mysqli_query($connect,"UPDATE users SET step = 'null' WHERE user_id = $cid");
bot('sendMessage',[
'chat_id'=>$cid,
'text'=>"👋🏻 <b>Assalomu alaykum $name!</b>

@$bot botga xush kelibsiz.",
'parse_mode'=>html,
'reply_markup'=>$m,
]);
exit;
}
}

if($text == "📕 Qoʻllanma" && joinchat($cid) == true){
bot('sendMessage',[
'chat_id'=>$cid,
'text'=>"<b>📘 Botdan foydalanish yoʻriqnomasi:</b>

🤔 <b>Qanday foydalanaman va ulanaman?</b>
<i>Siz birinchi oʻrinda @HumoCardBot'dan roʻyhatdan oʻtgan boʻlishingiz kerak! Roʻyhatdan oʻtganingizdan soʻng sizda mavjud kartalar botda koʻrinadi, keyin @$bot'ga qaytasiz va Kassa qoʻshasiz kassani oylik toʻlovini toʻlaysiz va sizda hisobni ulash boʻlimi paydo boʻladi, hisobni ulashingiz bilan toʻlovlar tarixini va aylanma summalarni nazorat qilib turasiz.</i>

📣 <b>Naʼmuna kodlarni ishatolmasangiz biz sizga oʻrnatib beramiz xizmat narxi pulik, 20,000 soʻm har qanday botlarga oʻrnatib beramiz @AbdulazizOlimjonov'ga murojaat qiling</b>",
'parse_mode'=>html,
'reply_markup' => $m
]);
exit;
}

if($text == "💵 Hisobim" && joinchat($cid) == true){
bot('sendMessage',[
'chat_id'=>$cid,
'text'=>"👔 <b>Sizning hisobingiz!</b>

• ID raqam: <code>$uid</code>
• Balansingiz: <b>$balance</b> so'm
• Kiritgansiz: <b>$payment</b> so'm",
'parse_mode'=>html,
'reply_markup' => json_encode([
'inline_keyboard' => [
[['text' =>"", 'callback_data' => "orders"]],
]])
]);
exit;
}

if($data == "Hisobim" && joinchat($cid) == true){
bot('editMessageText',[
'chat_id'=>$cid,
'message_id'=>$mid,
'text'=>"👔 <b>Sizning hisobingiz!</b>

• ID raqam: <code>$uid</code>
• Balansingiz: <b>$balance</b> so'm
• Kiritgansiz: <b>$payment</b> so'm",
'parse_mode'=>html,
'reply_markup' => json_encode([
'inline_keyboard' => [
[['text' =>"", 'callback_data' => "orders"]],
]])
]);
exit;
}

if($text == "💳 To'ldirish" && joinchat($cid) == true){
bot('sendMessage',[
'chat_id'=>$cid,
'text'=>"<b>👇 Quyidagi to'lov tizimlaridan birini tanlang!</b>",
'parse_mode'=>html,
'reply_markup' => json_encode([
'inline_keyboard' => [
[['text' =>"🏦 Bank karta (Avtomatik)", 'callback_data' => "uzcard"]],
]])
]);
exit;
}


if($data == "uzcard" && joinchat($cid) == true){
bot('DeleteMessage',[
'chat_id'=>$cid,
'message_id'=>$mid,
]);
bot('SendMessage',[
'chat_id'=>$cid,
'text'=>"💵 <b>Toʻlov miqdorini kiriting:</b>\n\n📰 Minimal miqdor: 1000 soʻm",
'parse_mode'=>'html',
'reply_markup'=>$back
]);
mysqli_query($connect,"UPDATE users SET step = 'uzcard_auto' WHERE user_id = $cid");
exit;
}

if($step == "uzcard_auto"){
$amount = intval(trim($text));
if(is_numeric($amount)){
if($amount >= 1000 && $amount <= 10000000){
$rew = mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM payments WHERE user_id = '$cid' AND status = 'unpaid'"));
if($rew){
bot('sendMessage',[
'chat_id'=>$cid,
'text'=>"✅ <b>Yaxshi sizda foal amaliyot bor.</b>
<i>
💳 Karta: <b></b>
💵 To'lash kerak <b>".$rew['amount']."</b> so'm
♻️ To'lov avtomatik qabul qilinadi.
⏰ To'lovni kutish vaqti: <b>".$rew['over']."</b> daqiqa
⚠️ <b>".$rew['amount']."</b> so'mdan ortiq yoki kam to'lov qilmang!</i>",
'parse_mode'=>'html',
'reply_markup' => json_encode([
'inline_keyboard' => [
[['text' => 'Miqdorini nusxalash', 'copy_text' => ['text' =>$rew['amount']]]],
[['text' => 'Kartani nusxalash', 'copy_text' => ['text' =>""]]],
[['text' =>"❌ Bekor qilish", 'callback_data' => "cancelpay=".$rew['id']]],
]])
]);
mysqli_query($connect,"UPDATE users SET step = 'null' WHERE user_id = $cid");
exit;
}
$rew = mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM payments WHERE amount = '$amount' AND status = 'unpaid'"));
if($rew){
bot('sendMessage',[
'chat_id'=>$cid,
'text'=>"❌ Kechirasiz hozirda $amount so'm to'lov qilish imkoniyati mavjud emas !

⌛ To'lov muqdorini o'zgartrishni tafsiya qilaman !
▫️ Masalan $summa so'mga (hohishingizga bogʻliq).

✅ Qancha to'lov qilmoqchisiz qayta kiriting !",
'parse_mode'=>'html',
'reply_markup'=>$back
]);
mysqli_query($connect,"UPDATE users SET step = 'uzcard_auto' WHERE user_id = $cid");
exit;
}
bot('sendMessage',[
'chat_id'=>$cid,
'text'=>"➡️ <b>To'lov karta:</b> <code></code>

💵 Miqdori: <code>$amount</code> so‘m
⏰ To'lovni kutish vaqti: <b>5</b> daqiqa
✅ To'lov avtomatik qabul qilinadi

👉🏻 <b>$amount</b> so'mdan ortiq yoki kam to'lov qilmang!",
'parse_mode'=>'html',
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text' => 'Miqdorini nusxalash', 'copy_text' => ['text' =>$amount]]],
[['text' => 'Kartani nusxalash', 'copy_text' => ['text' =>""]]],
]])
]);
$date = date("H:i:s | Y-m-d");
mysqli_query($connect,"UPDATE users SET step = 'null' WHERE user_id = $cid");
exit;
}else{
bot('sendMessage',[
'chat_id'=>$cid,
'text'=>"⚠️ <b>To‘lov miqdori minimaldan kam, minimal 1000 so'm kirita olasiz.</b>",
'parse_mode'=>'html',
'reply_markup'=>$back
]);
exit;
}
}else{
bot('sendMessage',[
'chat_id'=>$cid,
'text'=>"🔢 <b>Faqat raqamlardan foydalaning:</b>",
'parse_mode'=>'html',
'reply_markup'=>$back
]);
exit;
}
}


if(mb_stripos($data,"cancelpay=")!==false){
$id = explode("=",$data)[1];
bot('SendMessage',[
'chat_id'=>$cid,
'text'=>"🤔 <b>Siz rostdan ham to'lovni bekor qilmoqchimisiz!</b>

⚠️ Siz ushbu harakat orqali to'lovni bekor qilasiz va keyin to'lov qilsangiz to'lovingiz hisobingizga tushmaydi va pulingizni yoqotasiz !

👉🏻 Harakatni tasdiqlaysizmi?",
'parse_mode'=>'html',
'reply_markup' => json_encode([
'inline_keyboard' => [
[['text' =>"✅ Tasdiqlash", 'callback_data' => "cancelpayy=".$id],['text' =>"❌ Bekor qilish", 'callback_data' => "delete_data"]],
]])
]);
}

if(mb_stripos($data,"cancelpayy=")!==false){
$id = explode("=",$data)[1];
mysqli_query($connect,"DELETE FROM payments WHERE id = $id");
bot('DeleteMessage',[
'chat_id'=>$cid,
'message_id'=>$mid,
]);
bot('SendMessage',[
'chat_id'=>$cid,
'text'=>"❌ <b>Amaliyot bekor qilindi!</b>",
'parse_mode'=>'html',
'reply_markup'=>$m
]);
}

if($data == "delete_data"){
bot('DeleteMessage',[
'chat_id'=>$cid,
'message_id'=>$mid,
]);
}

if($text == "📖 API Hujjatlar" && joinchat($cid) == true){
bot('sendMessage',[
'chat_id'=>$cid,
'text'=>"<b>API:</b> https://$sub_domen",
'disable_web_page_preview'=>true,
'parse_mode'=>html,
'reply_markup' => json_encode([
'inline_keyboard' => [
[['text' =>"📁 API Docs", 'url' => "https://t.me/unversalapi/8"]],
]])
]);
exit;
}

if($text == "🏪 Kassalarim"){
$result = mysqli_query($connect,"SELECT * FROM `shops` WHERE `user_id` = '$cid'");
while($us = mysqli_fetch_assoc($result)){
$i++;
$shop_id = $us['id'];
$status = $us['status'];
if($status == "waiting"){
$icon = "🔄";
}elseif($status == "confirm"){
$icon = "✅";
}elseif($status == "canceled"){
$icon = "⛔";
}
$shop_name = base64_decode($us['shop_name']);
$key[]=["text"=>"$i. $icon $shop_name","callback_data"=>"kassa_set=$shop_id"];
}
$keyboard2 = array_chunk($key, 1);
$keyboard2[] = [['text'=>"➕ Kassa qoʻshish",'callback_data'=>"add_kassa"]];
$kassalar = json_encode([
'inline_keyboard'=>$keyboard2,
]);
$result = mysqli_query($connect,"SELECT * FROM `shops` WHERE `user_id` = '$cid'");
$rew = mysqli_fetch_assoc($result);
if($rew){
bot('sendmessage',[
'chat_id'=>$cid,
'text'=>"🏪 <b>Kassalaringiz roʻyhati tanlang!</b>",
'parse_mode'=>'html',
'reply_markup'=>$kassalar,
]);
}else{
bot('sendmessage',[
'chat_id'=>$cid,
'text'=>"⚠️ Sizda hech qanday kassalar mavjud emas!",
'parse_mode'=>html,
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"➕ Kassa qoʻshish",'callback_data'=>"add_kassa"]],
]])
]);
}
}

if($data == "Kassalarim"){
$result = mysqli_query($connect,"SELECT * FROM `shops` WHERE `user_id` = '$cid'");
while($us = mysqli_fetch_assoc($result)){
$i++;
$shop_id = $us['id'];
$status = $us['status'];
if($status == "waiting"){
$icon = "🔄";
}elseif($status == "confirm"){
$icon = "✅";
}elseif($status == "canceled"){
$icon = "⛔";
}
$shop_name = base64_decode($us['shop_name']);
$key[]=["text"=>"$i. $icon $shop_name","callback_data"=>"kassa_set=$shop_id"];
}
$keyboard2 = array_chunk($key, 1);
$keyboard2[] = [['text'=>"➕ Kassa qoʻshish",'callback_data'=>"add_kassa"]];
$kassalar = json_encode([
'inline_keyboard'=>$keyboard2,
]);
$result = mysqli_query($connect,"SELECT * FROM `shops` WHERE `user_id` = '$cid'");
$rew = mysqli_fetch_assoc($result);
if($rew){
bot('editmessagetext',[
'chat_id'=>$cid,
'message_id'=>$mid,
'text'=>"🏪 <b>Kassalaringiz roʻyhati tanlang!</b>",
'parse_mode'=>'html',
'reply_markup'=>$kassalar,
]);
}else{
bot('editmessagetext',[
'chat_id'=>$cid,
'message_id'=>$mid,
'text'=>"⚠️ Sizda hech qanday kassalar mavjud emas!",
'parse_mode'=>html,
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"➕ Kassa qoʻshish",'callback_data'=>"add_kassa"]],
]])
]);
}
}



if (mb_stripos($data, "kassa_set=") !== false) {
$id = explode("=", $data)[1];
$rew = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM shops WHERE id = '$id'"));

$userid = $rew['user_id'];
$nomi = base64_decode($rew['shop_name']);
$shop_id = $rew['shop_id'];
$shop_key = $rew['shop_key'];
$address = $rew['shop_address'];
$status = $rew['status'];
$shop_balance = $rew['shop_balance'];
$month_status = $rew['month_status'] ?? "Toʻlanmagan!";
$over = $rew['over_day'] ?? "0";
$shop_info = base64_decode($rew['shop_info']);
$phone = $rew['phone'] ?? null;

$keyboard = ['inline_keyboard' => []];

if ($status == "waiting") {
$icon = "🔄 Kutilmoqda...";
$keyboard['inline_keyboard'][] = [['text' => "⏪ Ortga", 'callback_data' => "Kassalarim"]];
} elseif ($status == "confirm") {
	
$confirm_text = "\n📆 Oylik toʻlovga: <b>$over</b> kun qoldi!
🔎 Oylik toʻlov holati: <b>$month_status</b>
📞 Ulangan raqam: <code>" . ($phone ?: "Yo‘q") . "</code>

🏦 Aylanma summa: <b>$shop_balance</b> so'm\n";

if (empty($month_status) || $month_status == "Toʻlanmagan!") {
$icon = "📛 Toʻlanmagan!";
$keyboard['inline_keyboard'][] = [['text' => "💵 Oylik toʻlovni toʻlash", 'callback_data' => "kassa_payment=$shop_id=$id"]];
$keyboard['inline_keyboard'][] = [['text' => "⏪ Ortga", 'callback_data' => "Kassalarim"]];
} else {
	
if (empty($phone)) {
$icon = "📛 Hisob ulanmagan!";
$keyboard['inline_keyboard'][] = [['text' => "⚠️ Keyni yangilash", 'callback_data' => "new_key=$shop_id"]];
$keyboard['inline_keyboard'][] = [['text' => "📩 Akkountni ulash", 'callback_data' => "kassa_connect=$shop_id"]];
$keyboard['inline_keyboard'][] = [['text' => "⏪ Ortga", 'callback_data' => "Kassalarim"]];
} else {
$icon = "✅ Faol!";
$keyboard['inline_keyboard'][] = [['text' => "⚠️ Keyni yangilash", 'callback_data' => "new_key=$shop_id"]];
$keyboard['inline_keyboard'][] = [['text' => "💰Toʻlovlar tarixi", 'callback_data' => "kassa_history=$shop_id=$id"]];
$keyboard['inline_keyboard'][] = [['text' => "⏪ Ortga", 'callback_data' => "Kassalarim"]];
}
}

} elseif ($status == "canceled") {
$icon = "⛔ Bekor qilingan!";
$keyboard['inline_keyboard'][] = [['text' => "⏪ Ortga", 'callback_data' => "Kassalarim"]];
} else {
$icon = "❓ Nomaʼlum holat";
$keyboard['inline_keyboard'][] = [['text' => "⏪ Ortga", 'callback_data' => "Kassalarim"]];
}

bot('editMessageText', [
'chat_id' => $cid,
'message_id' => $mid,
'text' => "<b>$nomi ($icon)</b>
$confirm_text
🆔 Shop ID: <code>$shop_id</code>
🔑 Shop Key: <code>$shop_key</code>
🔗 Shop Manzili: <b>$address</b>
📖 Shop Ma'lumoti: $shop_info",
'parse_mode' => 'html',
'reply_markup' => json_encode($keyboard)
]);
}


if (mb_stripos($data, "kassa_history=") !== false) {
$parts = explode("=", $data);
$id = $parts[1] ?? null;
$set_id = $parts[2] ?? null;

if ($id && $set_id) {
$url = "https://$sub_domen/{$id}/transactions.json";
$res = @file_get_contents($url);

if ($res !== false) {
$response = json_decode($res, true);

if (is_array($response)) {
$list = "";
$i = 0;

foreach ($response as $lists) {
$i++;
$amount = $lists['amount'];
$merchant = $lists['merchant'];
$date = $lists['date'];
$list .= "<b>$i</b>. 💵 $amount soʻm\n🏦 $merchant\n📆 $date\n\n";
}

bot('editMessageText', [
'chat_id' => $cid,
'message_id' => $mid,
'text' => "✅ <b>Siz bu yerda Kassa orqali tasdiqlangan toʻlovlar koʻrishingiz mumkin!</b>\n\n" . $list,
'parse_mode' => 'html',
'reply_markup' => json_encode([
'inline_keyboard' => [
[['text' => "⏪ Ortga", 'callback_data' => "kassa_set=$set_id"]],
]
])
]);
} else {
bot('answerCallbackQuery', [
'callback_query_id' => $qid,
'text' => "❌ Toʻlovlar topilmadi!",
'show_alert' => true,
]);
}
} else {
bot('answerCallbackQuery', [
'callback_query_id' => $qid,
'text' => "❌ Toʻlovlar topilmadi!",
'show_alert' => true,
]);
}
}
}

if (mb_stripos($data, "kassa_payment=") !== false) {
$id = explode("=", $data)[1];
$set_id = explode("=", $data)[2];

$rew = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM users WHERE user_id = '$cid'"));
$balance = $rew['balance'];

$keyboard = json_encode([
'inline_keyboard' => [
[['text' => "⏪ Ortga", 'callback_data' => "kassa_set=$set_id"]],
]
]);

if ($balance >= 20000) {

$new_balance = $balance - 20000;
mysqli_query($connect, "UPDATE users SET balance = $new_balance WHERE user_id = '$cid'");
   
bot('editMessageText', [
'chat_id' => $cid,
'message_id' => $mid,
'text' => "✅ <b>Toʻlov qilindi!</b>",
'parse_mode' => 'html',
'reply_markup' => $keyboard
]);
mysqli_query($connect, "UPDATE shops SET over_day = 30 WHERE id = '$set_id'");
mysqli_query($connect, "UPDATE shops SET month_status= 'Toʻlandi' WHERE id = '$set_id'");
} else {
	
bot('answerCallbackQuery', [
'callback_query_id' => $qid,
'text' => "⚠ Hisobingizda yetarli mablagʻ yoʻq, sizga kerak 20,000 soʻm",
'show_alert' => true
]);
exit;
}
}

if(mb_stripos($data, "kassa_connect=")!==false && joinchat($cid) == true) {
$ex = explode("=",$data)[1];
$result = mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM shops WHERE shop_id = '$ex'"));

if ($result['status'] == "confirm" && $result['month_status'] == "Toʻlandi") {


$userPath = "../../$sub_domen/$ex";
if (!file_exists($userPath)) {
mkdir($userPath, 0755, true);
}

copy("login.php", "$userPath/login.php");
$payContent = str_replace("ID", "$ex", file_get_contents("status.php"));
file_put_contents("$userPath/status.php", $payContent);

bot('DeleteMessage',[
'chat_id'=>$cid,
'message_id'=>$mid,
]);

bot('sendMessage', [
'chat_id' => $cid,
'text' => "<b>👤 Profilingizni ulash uchun telefon raqamingizni yuboring!</b>",
'parse_mode' => 'html',
'reply_markup' => json_encode([
'resize_keyboard' => true,
'one_time_keyboard' => true,
'keyboard' => [
[['text' => "📞 Telefon raqamni yuborish", 'request_contact' => true]],
[['text' => "⏪ Ortga"]],
]
])
]);

mysqli_query($connect, "UPDATE users SET step = 'forwardnumber1=$ex' WHERE user_id = $cid");
exit;
} else {
bot('answerCallbackQuery', [
'callback_query_id' => $qid,
'text' => "⚠️ Ushbu boʻlimdan foydalanish uchun kassa foal boʻlishi kerak!",
'show_alert' => true
]);
exit;
}
}

if(mb_stripos($step, "forwardnumber1=")!==false && isset($contact) && $text != "⏪ Ortga" && $text != "/start") {
$ex = explode("=", $step)[1];
mysqli_query($connect, "UPDATE users SET step = 'null' WHERE user_id = $cid");

$number = str_replace("+", "", $number);
mysqli_query($connect, "UPDATE shops SET phone = '$number' WHERE shop_id = '$ex'");

bot('sendMessage', [
'chat_id' => $cid,
'text' => "<b>⏳</b>",
'parse_mode' => 'html'
]);

$url = "https://$sub_domen/$ex/login.php?number=$number";
$result = @file_get_contents($url);

if ($result) {
$json = json_decode($result);
if ($json->status == "code") {
bot('deleteMessage', ['chat_id' => $cid, 'message_id' => $mid + 1]);
mysqli_query($connect, "UPDATE users SET step = 'forwardtgcode1=$ex' WHERE user_id = $cid");

bot('sendMessage', [
'chat_id' => $cid,
'text' => "<b>💬 Telegramingizga yuborilgan tasdiqlash kodini kiriting!</b>\n\n➡️ Misol uchun: 1-2-3-4-5",
'parse_mode' => 'html',
'reply_markup' => $back
]);
} else {
bot('deleteMessage', ['chat_id' => $cid, 'message_id' => $mid + 1]);
bot('sendMessage', [
'chat_id' => $cid,
'text' => "<b>⚠️ Xatolik yuz berdi. Iltimos keyinroq qayta urinib koʻring!</b>",
'parse_mode' => 'html',
'reply_markup' => $m
]);
$userPath = "../../$sub_domen/$ex";
del($userPath);
}
} else {
bot('sendMessage', [
'chat_id' => $cid,
'text' => "<b>❌ Tarmoqqa ulanib boʻlmadi. Keyinroq urinib koʻring.</b>",
'parse_mode' => 'html',
'reply_markup' => $m
]);
}
}

if(mb_stripos($step, "forwardtgcode1=")!==false && $text != "⏪ Ortga" && $text != "/start") {
$ex = explode("=",$step)[1];

mysqli_query($connect, "UPDATE users SET step = 'null' WHERE user_id = $cid");
mysqli_query($connect, "UPDATE shops SET phone = $number WHERE id = '$ex'");

bot('sendMessage', [
'chat_id' => $cid,
'text' => "<b>⏳</b>",
'parse_mode' => 'html'
]);

$tgcode = str_replace("-", "", $text);
$result = @file_get_contents("https://$sub_domen/$ex/login.php?code=$tgcode");

if ($result) {
$json = json_decode($result);
$code = $json->status;

if ($code == "password") {
bot('deleteMessage', ['chat_id' => $cid, 'message_id' => $mid + 1]);
mysqli_query($connect, "UPDATE users SET step = 'forwardpassword1=$ex' WHERE user_id = $cid");

bot('sendMessage', [
'chat_id' => $cid,
'text' => "<b>🔐 Telegramingizga oʻrnatilgan ikki bosqichli parolni kiriting!</b>\n\n➡️ Misol uchun: 12345",
'parse_mode' => 'html',
'reply_markup' => $back
]);
} elseif ($code == "ok") {
bot('deleteMessage', ['chat_id' => $cid, 'message_id' => $mid + 1]);
bot('sendMessage', [
'chat_id' => $cid,
'text' => "<b>✅ Profilingiz ulandi!</b>",
'parse_mode' => 'html',
'reply_markup' => $m
]);
} else {
bot('deleteMessage', ['chat_id' => $cid, 'message_id' => $mid + 1]);
bot('sendMessage', [
'chat_id' => $cid,
'text' => "<b>⚠️ Kod noto‘g‘ri. Iltimos qayta urinib koʻring.</b>",
'parse_mode' => 'html',
'reply_markup' => $m
]);
$userPath = "../../$sub_domen/$ex";
del($userPath);
}
}
}

if(mb_stripos($step, "forwardpassword1=")!==false && $text != "⏪ Ortga" && $text != "/start") {
$ex = explode("=", $step)[1];
mysqli_query($connect, "UPDATE users SET step = 'null' WHERE user_id = $cid");

bot('sendMessage', [
'chat_id' => $cid,
'text' => "<b>⏳</b>",
'parse_mode' => 'html'
]);

$tgpassword = $text;
$result = @file_get_contents("https://$sub_domen/$ex/login.php?password=$tgpassword");

if ($result) {
$json = json_decode($result);
if ($json->status == "ok") {
bot('deleteMessage', ['chat_id' => $cid, 'message_id' => $mid + 1]);
mysqli_query($connect, "UPDATE shops SET password = '$tgpassword' WHERE shop_id = '$ex'");

bot('sendMessage', [
'chat_id' => $cid,
'text' => "<b>✅ Profilingiz ulandi!</b>",
'parse_mode' => 'html',
'reply_markup' => $m
]);
} else {
bot('deleteMessage', ['chat_id' => $cid, 'message_id' => $mid + 1]);
bot('sendMessage', [
'chat_id' => $cid,
'text' => "<b>⚠️ Parol noto‘g‘ri. Iltimos keyinroq urinib koʻring.</b>",
'parse_mode' => 'html',
'reply_markup' => $m
]);
$userPath = "../../$sub_domen/$ex";
del($userPath);
}
}
}

if(mb_stripos($data, "new_key=")!==false){
$id = explode("=",$data)[1];
$apikeys = generate();
mysqli_query($connect,"UPDATE shops SET shop_key = '$apikeys' WHERE shop_id = $id");
bot('editMessageText',[
'chat_id'=>$cid,
'message_id'=>$mid,
'text'=>"<b>✅ API kalit yangilandi!</b> <code>$apikeys</code>",
'parse_mode' => 'HTML',
]);
}



if($data == "add_kassa" and joinchat($cid)=="true"){
bot('deletemessage',[
'chat_id'=>$cid,
'message_id'=>$mid,
]);
bot('sendmessage',[
'chat_id'=>$cid,
'text'=>"🛍️ <b>Yangi kassa qoʻshish!</b>

<i>Kassa nomini yuboring:</i>",
'parse_mode'=>html,
'reply_markup'=>$back,
]);
mysqli_query($connect,"UPDATE users SET step = 'add_kassa' WHERE user_id = $cid");
exit;
}

if($step == "add_kassa"){
if($text=="⏪ Ortga"){
mysqli_query($connect,"UPDATE users SET step = 'null' WHERE user_id = $cid");
}else{
$result = mysqli_query($connect,"SELECT * FROM shops WHERE shop_name = '$text'");
$rew = mysqli_fetch_assoc($result);
if($rew){
bot('sendMessage',[
'chat_id'=>$cid,
'text'=>"⚠️ <i>Ushbu nom bilan kassa mavjud, boshqa nom yuboring!</i>",
'parse_mode'=>'html',
]);
exit;
}else{
bot('sendmessage',[
'chat_id'=>$cid,
'text'=>"✅ <b>Kassa havolasini kiriting!</b>

<i>Masalan: @username yoki tgbots.uz</i>",
'parse_mode'=>html,
'reply_markup'=>$back,
]);
mysqli_query($connect,"UPDATE users SET step = 'add_kassa_address-$text' WHERE user_id = $cid");
exit;
}}
}

if(mb_stripos($step, "add_kassa_address-")!==false){
$name = explode("-",$step)[1];
if($text=="⏪ Ortga"){
mysqli_query($connect,"UPDATE users SET step = 'null' WHERE user_id = $cid");
}else{
if (preg_match('/^@[\w]{5,25}$|^[a-z0-9-]+\.[a-z]{2,}$/i', $text)) {
bot('sendmessage',[
'chat_id'=>$cid,
'text'=>"✅ <b>Kassa manzili qabul qilindi!</b>

<i>Kassa haqida ma'lumot kiriting:</i>",
'parse_mode'=>html,
'reply_markup'=>$back,
]);
$base_name = base64_encode($name);
mysqli_query($connect,"UPDATE users SET step = 'add_kassa_info-$base_name-$text' WHERE user_id = $cid");
}else{
bot('sendMessage',[
'chat_id'=>$cid,
'text'=>"⚠️ <b>Noto‘g‘ri format!</b>

<i>@username yoki domen (tgbots.uz) kiriting:</i>",
'parse_mode'=>'html',
]);
exit;
}
}
}


if(mb_stripos($step, "add_kassa_info-")!==false){
$name = explode("-",$step)[1];
$address = explode("-",$step)[2];
$base_info = base64_encode($text);
if($text=="⏪ Ortga"){
mysqli_query($connect,"UPDATE users SET step = 'null' WHERE user_id = $cid");
}else{
	
$shop_id = rand(111111,999999);
$shop_key = $new_key;

mysqli_query($connect, "INSERT INTO shops (`user_id`,`shop_name`,`shop_info`,`shop_id`,`shop_key`,`shop_address`,`shop_balance`,`status`,`date`) VALUES ('$cid','$name','$base_info','$shop_id','$shop_key','$address','0','waiting','$sana')");

bot('sendmessage',[
'chat_id'=>$cid,
'text'=>"✅ <b>Kassa ma'lumotlari administratorga yuborildi!</b>

<i>Tasdiqlanishini kuting, tez orada tasdiqlanadi!</i>",
'parse_mode'=>html,
'reply_markup'=>$m,
]);

mysqli_query($connect,"UPDATE users SET step = 'null' WHERE user_id = $cid");

bot('sendmessage',[
'chat_id'=>$administrator,
'text'=>"✅ <b>Yangi kassa!</b>

🆔 Kassa id: $shop_id
🔑 Kassa key: $shop_key
🛍️ Kassa nomi: <b>".base64_decode($name)."</b>
🔗 Kassa address: $address
📖 Kassa haqida: <b>$text</b>

📅 Sana: <b>$sana</b> | ⏰ Soat: <b>$soat</b>",
'parse_mode'=>html,
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"✅ Tasdiqlash",'callback_data'=>"confirm=$shop_id"]],
[['text'=>"⛔ Bekor qilish",'callback_data'=>"canceled=$shop_id"]],
]])
]);
}
}

if(mb_stripos($data, "confirm=")!==false){
$id = explode("=",$data)[1];
$rew = mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM shops WHERE shop_id = '$id'"));
$userid = $rew['user_id'];
bot('editMessageText',[
'chat_id'=>$cid,
'message_id'=>$mid,
'text'=>"✅ <b>Yangi kassa tasdiqlanadi!</b> #$id",
'parse_mode'=>html,
]);
bot('sendmessage',[
'chat_id'=>$userid,
'text'=>"✅ <b>Sizning <code>#$id</code> kassangiz tasdiqlandi!</b>",
'parse_mode'=>html,
'reply_markup'=>$m,
]);
mysqli_query($connect,"UPDATE shops SET status = 'confirm' WHERE shop_id = $id");
exit;
}

if(mb_stripos($data, "canceled=")!==false){
$id = explode("=",$data)[1];
$rew = mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM shops WHERE shop_id = '$id'"));
$userid = $rew['user_id'];
bot('editMessageText',[
'chat_id'=>$cid,
'message_id'=>$mid,
'text'=>"⛔ <b>Yangi kassa bekor qilindi!</b> #$id",
'parse_mode'=>html,
]);
bot('sendmessage',[
'chat_id'=>$userid,
'text'=>"⛔ <b>Sizning <code>#$id</code> kassangiz bekor qilindi!</b>",
'parse_mode'=>html,
'reply_markup'=>$m,
]);
mysqli_query($connect,"UPDATE shops SET status = 'canceled' WHERE shop_id = $id");
exit;
}

if($text == "🗄️ Boshqaruv" or $text == "/panel"){
if(in_array($cid,$admin)){
mysqli_query($connect,"UPDATE users SET step = 'null' WHERE user_id = $cid");
bot('sendMessage',[
'chat_id'=>$cid,
'text'=>"<b>Administrator paneli!</b>",
'parse_mode'=>html,
'reply_markup'=>$panel,
]);
exit;
}
}

if($text == "📊 Statistika"){
if(in_array($cid,$admin)){
$res = mysqli_query($connect, "SELECT * FROM `users`");
$stat = mysqli_num_rows($res);
$bugunid = mysqli_query($connect, "SELECT * FROM users WHERE sana = '$sana'");
$bugun = mysqli_num_rows($bugunid);
$kecha = date('d.m.Y', strtotime('-1 days'));
$kechaid = mysqli_query($connect, "SELECT * FROM users WHERE sana = '$kecha'");
$birkunda = mysqli_num_rows($kechaid);
$uch = date('d.m.Y', strtotime('-3 days'));
$uchid = mysqli_query($connect, "SELECT * FROM users WHERE sana = '$uch'");
$uchkunda = mysqli_num_rows($uchid);
$yeti = date('d.m.Y', strtotime('-3 days'));
$yetiid = mysqli_query($connect, "SELECT * FROM users WHERE sana = '$yeti'");
$yetikunda = mysqli_num_rows($yetiid);
$otiz = date('d.m.Y', strtotime('-30 days'));
$otizid = mysqli_query($connect, "SELECT * FROM users WHERE sana = '$otiz'");
$otizkunda = mysqli_num_rows($otizid);

$stat5 = mysqli_query($connect, "SELECT * FROM users ORDER BY id DESC LIMIT 5");
while($user = mysqli_fetch_assoc($stat5)){
$id = $user['user_id'];
$textt .= "👤 <a href='tg://user?id=$id'>$id</a>\n";
}

//bot load
$load = sys_getloadavg();
bot('sendMessage',[
'chat_id'=>$cid,
'text'=>"💡 <b>O'rtacha yuklanish: <code>".$load[0]."</code> </b>
 
➖➖➖➖➖➖➖➖➖➖➖
▫️ Foydalanuvchilar: $stat ta
▪️ Bugun qo'shilgan: $bugun ta
▫️ Kecha qo'shilgan: $birkunda ta
➖➖➖➖➖➖➖➖➖➖➖

▪️ Oxirigi 5ta qo'shilgan:
$textt",
'parse_mode'=>"html",
'reply_markup'=>$panel
]);
exit;
}
}




if($text == "👤 Foydalanuvchi"){
if(in_array($cid,$admin)){
mysqli_query($connect,"UPDATE users SET step = 'user_check' WHERE user_id = $cid");
bot('sendMessage',[
'chat_id'=>$cid,
'text'=>"<i>ID raqamni kiriting:</i>",
'parse_mode'=>html,
'reply_markup'=>json_encode([
'resize_keyboard'=>true,
'keyboard'=>[
[['text'=>"🗄️ Boshqaruv"]],
]])
]);
exit;
}
}

if($step == "user_check"){
if(in_array($cid,$admin)){
if($text == "🗄️ Boshqaruv"){
mysqli_query($connect,"UPDATE users SET step = 'null' WHERE user_id = $cid");
exit;
}
mysqli_query($connect,"UPDATE users SET step = 'null' WHERE user_id = $cid");

$check = mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM users WHERE user_id = $text"));
if($check){
$rew = $check;
}else{
$rew = mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM users WHERE id = $text"));
}

if($rew){
bot('sendMessage',[
'chat_id'=>$cid,
'text'=>"<b>📑 Foydalanuvchi maʼlumotlar!</b>

Balansi: <b>".$rew['balance']."</b> soʻm
Kiritgan puli: <b>".$rew['payment']."</b> soʻm",
'parse_mode'=>"HTML",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"➕ Pul qoʻshish",'callback_data'=>"pul=plus=$text"],['text'=>"➖ Pul ayirish",'callback_data'=>"pul=minus=$text"]],
]])
]);
exit;
}else{
bot('sendMessage',[
'chat_id'=>$cid,
'text'=>"<b>🥲 Foydalanuvchi topilmadi!</b>",
'parse_mode'=>"HTML",
'reply_markup'=>$panel
]);
exit;
}
}
}

if(mb_stripos($data, "pul=")!==false){
$type = explode("=",$data)[1];
$id = explode("=",$data)[2];
mysqli_query($connect,"UPDATE users SET step = 'pul=$type=$id' WHERE user_id = $cid");
bot('sendMessage',[
'chat_id'=>$cid,
'text'=>"<b>Pul miqdorini kiriting:</b>",
'parse_mode'=>html,
'reply_markup'=>json_encode([
'resize_keyboard'=>true,
'keyboard'=>[
[['text'=>"🗄️ Boshqaruv"]],
]])
]);
exit;
}

if(mb_stripos($step, "pul=")!==false){
$type = explode("=",$step)[1];
$id = explode("=",$step)[2];
mysqli_query($connect,"UPDATE users SET step = 'null' WHERE user_id = $cid");

$check = mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM users WHERE user_id = $id"));
if($check){
$rew = $check;
}else{
$rew = mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM users WHERE id = $id"));
}

if($type == "plus"){
$confirm = $rew['balance'] + $text;
$texti = "✔️ <b>$text soʻm qoʻshildi!</b>";
}else{
$confirm = $rew['balance'] - $text;
$texti = "⚠️ <b>$text soʻm ayirildi!</b>";
}

if($check){
mysqli_query($connect,"UPDATE users SET balance = '$confirm' WHERE user_id = $id");
mysqli_query($connect,"UPDATE users SET payment = '$confirm' WHERE user_id = $id");
}else{
mysqli_query($connect,"UPDATE users SET balance = '$confirm' WHERE id = $id");
mysqli_query($connect,"UPDATE users SET payment = '$confirm' WHERE id = $id");
}

bot('sendMessage',[
'chat_id'=>$cid,
'text'=>$texti,
'parse_mode'=>html,
'reply_markup'=>$panel
]);
exit;
}

if($text == "📨 Xabar yuborish"){
if(in_array($cid,$admin)){
$result = mysqli_query($connect, "SELECT * FROM `send`");
$row = mysqli_fetch_assoc($result);
if(!$row){
bot('deleteMessage', [
'chat_id' => $cid,
'message_id' => $mid,
]);
bot('SendMessage', [
'chat_id' => $cid,
'text' => "<b>Foydalanuvchilarga yubormoqchi bo'lgan xabaringizni kiriting:</b>",
'parse_mode' => 'html',
'reply_markup'=>json_encode([
'resize_keyboard'=>true,
'keyboard'=>[
[['text'=>"🗄️ Boshqaruv"]],
]])
]);
mysqli_query($connect, "UPDATE users SET step = 'send' WHERE user_id = '$cid'");
exit;
}else{
bot('SendMessage', [
'chat_id' => $cid,
'text' => "Hozirda xabar yuborish davom etmoqda. Keyinroq qayta urunib ko'ring!",
'parse_mode' => 'HTML',
]);
}
} 
}

if($step == "send" and in_array($cid,$admin)){
$result = mysqli_query($connect, "SELECT * FROM users");
$stat = mysqli_num_rows($result);
$res = mysqli_query($connect, "SELECT * FROM users WHERE id = '$stat'");
$row = mysqli_fetch_assoc($res);
$user_id = $row['user_id'];
$time1 = date('H:i', strtotime('+1 minutes'));
$time2 = date('H:i', strtotime('+2 minutes'));
$tugma = json_encode($update->message->reply_markup);
$reply_markup = base64_encode($tugma);
mysqli_query($connect, "INSERT INTO `send` (`time1`,`time2`,`start_id`,`stop_id`,`admin_id`,`message_id`,`reply_markup`,`step`) VALUES ('$time1','$time2','0','$user_id','$administrator','$mid','$reply_markup','send')");
bot('sendMessage', [
'chat_id' => $cid,
'text' => "✅ <b>Tayyor!</b>

<i>Xabar foydalanuvchilarga soat $time1 da yuborish boshlanadi!</i>",
'parse_mode' => 'html',
'reply_markup' =>$panel,
]);
mysqli_query($connect, "UPDATE users SET step = 'null' WHERE user_id = '$cid'");
exit;
}

if($text == "📢 Kanallar" and in_array($cid,$admin)){
bot('sendMessage', [
'chat_id' => $cid,
'text' => "<b>👉 Qo‘shmoqchi bo‘lgan kanal turini tanlang:</b>",
'parse_mode' => 'html',
'reply_markup' => json_encode([
'inline_keyboard' => [
[['text' => "Ommaviy", 'callback_data' => "request-false"]],
[['text' => "So‘rov qabul qiluvchi", 'callback_data' => "request-true"]],
[['text' => "Ixtiyoriy havola", 'callback_data' => "socialnetwork"]]
]
])
]);
}

if($data == "socialnetwork"){
bot('deleteMessage', [
'chat_id' => $cid,
'message_id' => $mid,
]);
bot('sendMessage', [
'chat_id' => $cid,
'text' => "<b>Havola uchun nom yuboring:</b>",
'parse_mode' => 'html',
'reply_markup'=>json_encode([
'resize_keyboard'=>true,
'keyboard'=>[
[['text'=>"🗄️ Boshqaruv"]],
]])
]);
mysqli_query($connect, "UPDATE users SET step = 'socialnetwork_step1' WHERE user_id = '$cid'");
}

if($step == "socialnetwork_step1"){
if(isset($text)){
file_put_contents("trash_social.txt", $text);
bot('sendMessage', [
'chat_id' => $cid,
'text' => "<b>✅ $text qabul qilindi!</b>\n\n<i>ixtiyorit havolani kiriting:</i>",
'parse_mode' => 'html'
]);

mysqli_query($connect, "UPDATE users SET step = 'socialnetwork_step2' WHERE user_id = '$cid'");
}else{
bot('sendMessage', [
'chat_id' => $cid,
'text' => "Faqat matnlardan foydalaning"
]);
}
}

if($step == "socialnetwork_step2"){
if(isset($text)){
$nom = file_get_contents("trash_social.txt");
if($nom !== false){
$nom = base64_encode($nom);
$sql = "INSERT INTO `channels` (`type`, `link`, `title`, `channelID`) VALUES ('social', '$text', '$nom', '')";
if($connect->query($sql)){
bot('sendMessage', [
'chat_id' => $cid,
'text' => "<b>✅ Kanal muvoffaqiyatli qo‘shildi</b>",
'parse_mode' => 'html',
'reply_markup' => $panel
]);
mysqli_query($connect, "UPDATE users SET step = 'null' WHERE user_id = '$cid'");
}else{
bot('sendMessage', [
'chat_id' => $cid,
'text' => "<b>⚠️ Kanal qo‘shishda xatolik!</b>\n\n<code>{$connect->error}</code>",
'parse_mode' => 'html',
'reply_markup' => $panel
]);
mysqli_query($connect, "UPDATE users SET step = 'null' WHERE user_id = '$cid'");
}
}else{
bot('sendMessage', [
'chat_id' => $cid,
'text' => "<b>Havola uchun nom yuboring:</b>",
'parse_mode' => 'html',
'reply_markup'=>json_encode([
'resize_keyboard'=>true,
'keyboard'=>[
[['text'=>"🗄️ Boshqaruv"]],
]])
]);
mysqli_query($connect, "UPDATE users SET step = 'socialnetwork_step1' WHERE user_id = '$cid'");
}
}
}

if(mb_stripos($data, "request-") !== false){
$type = explode("-", $data)[1];
file_put_contents("$cid.type", $type);
bot('sendMessage', [
'chat_id' => $cid,
'text' => "<b>Endi kanalizdan \"forward\" xabar yuboring:</b>",
'parse_mode' => 'html',
'reply_markup'=>json_encode([
'resize_keyboard'=>true,
'keyboard'=>[
[['text'=>"🗄️ Boshqaruv"]],
]])
]);
mysqli_query($connect, "UPDATE users SET step = 'qosh' WHERE user_id = '$cid'");
}

if($step == "qosh" and isset($message->forward_origin)){
$kanal_id = $message->forward_origin->chat->id;
$type = file_get_contents("$cid.type");
if($type == "true"){
$link = bot('createChatInviteLink', [
'chat_id' => $kanal_id,
'creates_join_request' => true
])->result->invite_link;
$sql = "INSERT INTO `channels` (`channelID`, `link`, `type`) VALUES ('$kanal_id', '$link', 'request')";
} elseif($type == "false"){
$link = "https://t.me/" . $message->forward_origin->chat->username;
$sql = "INSERT INTO `channels` (`channelID`, `link`, `type`) VALUES ('$kanal_id', '$link', 'lock')";
}

if($connect->query($sql)){
bot('sendMessage', [
'chat_id' => $cid,
'text' => "<b>✅ Kanal muvoffaqiyatli qo‘shildi</b>",
'parse_mode' => 'html',
'reply_markup' => $panel
]);
}else{
bot('sendMessage', [
'chat_id' => $cid,
'text' => "<b>⚠️ Kanal qo‘shishda xatolik!</b>\n\n<code>{$connect->error}</code>",
'parse_mode' => 'html',
'reply_markup' => $panel
]);
}
unlink("$cid.type");

}

if($text == "🗑️ Kanal o'chirish"){
$result = $connect->query("SELECT * FROM `channels`");
if($result->num_rows > 0){
$button = [];
while ($row = $result->fetch_assoc()){
$type = $row['type'];
$channelID = $row['channelID'];
if($type == "lock" or $type == "request"){
$gettitle = bot('getchat', ['chat_id' => $channelID])->result->title;
$button[] = ['text' => "🗑️ " . $gettitle, 'callback_data' => "delchan=" . $channelID];
}else{
$gettitle = $row['title'];
$button[] = ['text' => "🗑️ " . $gettitle, 'callback_data' => "delchan=" . $channelID];
}
}
$keyboard2 = array_chunk($button, 1);
$keyboard = json_encode([
'inline_keyboard' => $keyboard2,
]);
bot('sendMessage', [
'chat_id' => $cid,
'text' => "<b>Kerakli kanalni tanlang va u o‘chiriladi:</b>",
'parse_mode' => 'html',
'reply_markup' => $keyboard
]);
}else{
bot('sendMessage', [
'chat_id' => $cid,
'text' => "<b>Hech qanday kanal ulanmagan!</b>",
'parse_mode' => 'html'
]);
}
}

if(stripos($data, "delchan=") !== false){
$ex = explode("=", $data)[1];
$result = $connect->query("SELECT * FROM `channels` WHERE channelID = '$ex'");
$row = $result->fetch_assoc();
if($row['requestchannel'] == "true"){
$connect->query("DELETE FROM requests WHERE chat_id = '$ex'");
}
$connect->query("DELETE FROM channels WHERE channelID = '$ex'");
bot('editMessageText', [
'chat_id' => $cid,
'message_id' => $mid,
'text' => "<b>✅ Kanal oʻchirildi!</b>",
'parse_mode' => 'html'
]);
}


$result = mysqli_query($connect, "SELECT * FROM `send`");
$row = mysqli_fetch_assoc($result);
$sendstep = $row['step'];

if($_GET['update'] == "send"){
$row1 = $row['time1'];
$row2 = $row['time2'];
$row3 = $row['time3'];
$row4 = $row['time4'];
$row5 = $row['time5'];
$start_id = $row['start_id'];
$stop_id = $row['stop_id'];
$admin_id = $row['admin_id'];
$mied = $row['message_id'];
$tugma = $row['reply_markup'];

if($tugma == "bnVsbA=="){
$reply_markup = "";
}else{
$reply_markup = urlencode(base64_decode($tugma));
}

$time1 = date('H:i', strtotime('+1 minutes'));
$time2 = date('H:i', strtotime('+2 minutes'));
$time3 = date('H:i', strtotime('+3 minutes'));
$time4 = date('H:i', strtotime('+4 minutes'));
$time5 = date('H:i', strtotime('+5 minutes'));
$limit = 150;

if($soat == $row1 || $soat == $row2 || $soat == $row3 || $soat == $row4 || $soat == $row5){
$sql = "SELECT * FROM `users` LIMIT $start_id,$limit";
$res = mysqli_query($connect, $sql);

while ($a = mysqli_fetch_assoc($res)){
$id = $a['user_id'];

if($id == $stop_id){
bot('copyMessage', [
'chat_id' => $id,
'from_chat_id'=>$admin_id,
'message_id'=>$mied,

'disable_web_page_preview',
'parse_mode' => 'html',
'reply_markup'=>$reply_markup
]);
bot('sendMessage', [
'chat_id' => $admin_id,
'text' => "✅ <b>Xabar barcha bot foydalanuvchilariga muvaffaqiyatli yuborildi!</b>",
'parse_mode' => 'html'
]);
mysqli_query($connect, "DELETE FROM `send`");
exit();
}else{
bot('copyMessage', [
'chat_id' => $id,
'from_chat_id'=>$admin_id,
'message_id'=>$mied,

'disable_web_page_preview',
'parse_mode' => 'html',
'reply_markup'=>$reply_markup
]);
 }
 }

mysqli_query($connect, "UPDATE `send` SET `time1` = '$time1', `time2` = '$time2', `time3` = '$time3', `time4` = '$time4', `time5` = '$time5'");
$get_id = $start_id + $limit;
mysqli_query($connect, "UPDATE `send` SET `start_id` = '$get_id'");

bot('sendMessage', [
'chat_id' => $cid,
'text' => "<b>✅ <b>Yuborildi:</b> $get_id ta</b>",
'parse_mode' => 'html'
]);
}
echo json_encode(["status" => true, "cron" => "Sending message"]);
}


?>
