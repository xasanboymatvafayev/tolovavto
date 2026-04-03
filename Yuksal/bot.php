<?php

$sub_domen = "tolovavto-production.up.railway.app";

require (__DIR__ . "/../config.php");

$administrator = getenv('ADMIN_ID') ?: "6365371142";
$admin = array($administrator);

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

// users
$res = mysqli_query($connect,"SELECT * FROM users WHERE user_id = $cid");
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
'parse_mode'=>'html',
'reply_markup'=>$m,
]);
exit;
}
}

if($text == "📕 Qoʻllanma" && joinchat($cid) == true){
bot('sendMessage',[
'chat_id'=>$cid,
'text'=>"<b>📘 Botdan foydalanish yoʻriqnomasi:</b>

🤔 <b>Qanday foydalanaman?</b>
<i>Avval kassa qoʻshib admindan tasdiqlatasiz. Kassangiz tasdiqlangach oylik toʻlovni toʻlaysiz va hisobingizni ulaysiz. Hisobni ulashingiz bilan toʻlovlar avtomatik qabul qilinadi.</i>

📣 <b>Yordam kerak bo'lsa adminga murojaat qiling!</b>",
'parse_mode'=>'html',
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
'parse_mode'=>'html',
'reply_markup' => json_encode([
'inline_keyboard' => [
[['text' =>"🔄 Yangilash", 'callback_data' => "Hisobim"]],
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
'parse_mode'=>'html',
'reply_markup' => json_encode([
'inline_keyboard' => [
[['text' =>"🔄 Yangilash", 'callback_data' => "Hisobim"]],
]])
]);
exit;
}

if($text == "💳 To'ldirish" && joinchat($cid) == true){
bot('sendMessage',[
'chat_id'=>$cid,
'text'=>"<b>👇 Quyidagi to'lov tizimlaridan birini tanlang!</b>",
'parse_mode'=>'html',
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
$rew = mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM payments WHERE user_id = '$cid' AND status = 'pending'"));
if($rew){
bot('sendMessage',[
'chat_id'=>$cid,
'text'=>"✅ <b>Sizda faol amaliyot bor.</b>
<i>
💵 To'lash kerak <b>".$rew['amount']."</b> so'm
♻️ To'lov avtomatik qabul qilinadi.
⚠️ <b>".$rew['amount']."</b> so'mdan ortiq yoki kam to'lov qilmang!</i>",
'parse_mode'=>'html',
'reply_markup' => json_encode([
'inline_keyboard' => [
[['text' => 'Miqdorini nusxalash', 'copy_text' => ['text' =>$rew['amount']]]],
[['text' =>"❌ Bekor qilish", 'callback_data' => "cancelpay=".$rew['id']]],
]])
]);
mysqli_query($connect,"UPDATE users SET step = 'null' WHERE user_id = $cid");
exit;
}
$check = mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM payments WHERE amount = '$amount' AND status = 'pending'"));
if($check){
bot('sendMessage',[
'chat_id'=>$cid,
'text'=>"❌ Kechirasiz hozirda $amount so'm to'lov qilish imkoniyati mavjud emas!

⌛ To'lov miqdorini o'zgartirishni tavsiya qilaman!

✅ Qancha to'lov qilmoqchisiz qayta kiriting!",
'parse_mode'=>'html',
'reply_markup'=>$back
]);
mysqli_query($connect,"UPDATE users SET step = 'uzcard_auto' WHERE user_id = $cid");
exit;
}
// Yangi payment yaratish
$order = generate();
mysqli_query($connect,"INSERT INTO payments (message_id, amount, status, used_order, created_at) VALUES ('user_$cid\_$order', '$amount', 'pending', '$order', NOW())");
bot('sendMessage',[
'chat_id'=>$cid,
'text'=>"💳 <b>To'lov ma'lumotlari:</b>

➡️ Karta raqam: <code>5614 6835 8227 9246</code>
💵 Miqdori: <code>$amount</code> so'm
⏰ To'lovni kutish vaqti: <b>30</b> daqiqa
✅ To'lov avtomatik qabul qilinadi

👉🏻 <b>$amount</b> so'mdan ortiq yoki kam to'lov qilmang!",
'parse_mode'=>'html',
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text' => 'Miqdorini nusxalash', 'copy_text' => ['text' =>$amount]]],
]])
]);
mysqli_query($connect,"UPDATE users SET step = 'null' WHERE user_id = $cid");
exit;
}else{
bot('sendMessage',[
'chat_id'=>$cid,
'text'=>"⚠️ <b>To'lov miqdori minimaldan kam, minimal 1000 so'm.</b>",
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
'text'=>"🤔 <b>Siz rostdan ham to'lovni bekor qilmoqchimisiz?</b>

⚠️ Bekor qilsangiz pul yo'qotilishi mumkin!

👉🏻 Harakatni tasdiqlaysizmi?",
'parse_mode'=>'html',
'reply_markup' => json_encode([
'inline_keyboard' => [
[['text' =>"✅ Tasdiqlash", 'callback_data' => "cancelpayy=$id"],['text' =>"❌ Yo'q", 'callback_data' => "delete_data"]],
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
'text'=>"<b>API:</b> https://$sub_domen/api",
'disable_web_page_preview'=>true,
'parse_mode'=>'html',
'reply_markup' => json_encode([
'inline_keyboard' => [
[['text' =>"📁 API Docs", 'url' => "https://$sub_domen/docs.html"]],
]])
]);
exit;
}

if($text == "🏪 Kassalarim"){
$result = mysqli_query($connect,"SELECT * FROM `shops` WHERE `user_id` = '$cid'");
$i = 0;
$key = [];
while($us = mysqli_fetch_assoc($result)){
$i++;
$shop_id = $us['id'];
$status = $us['status'];
if($status == "waiting") $icon = "🔄";
elseif($status == "confirm") $icon = "✅";
elseif($status == "canceled") $icon = "⛔";
$shop_name = base64_decode($us['shop_name']);
$key[]=[["text"=>"$i. $icon $shop_name","callback_data"=>"kassa_set=$shop_id"]];
}
$key[] = [['text'=>"➕ Kassa qoʻshish",'callback_data'=>"add_kassa"]];
$kassalar = json_encode(['inline_keyboard'=>$key]);
$result = mysqli_query($connect,"SELECT * FROM `shops` WHERE `user_id` = '$cid'");
$rew = mysqli_fetch_assoc($result);
if($rew){
bot('sendmessage',[
'chat_id'=>$cid,
'text'=>"🏪 <b>Kassalaringiz roʻyhati:</b>",
'parse_mode'=>'html',
'reply_markup'=>$kassalar,
]);
}else{
bot('sendmessage',[
'chat_id'=>$cid,
'text'=>"⚠️ Sizda hech qanday kassalar mavjud emas!",
'parse_mode'=>'html',
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"➕ Kassa qoʻshish",'callback_data'=>"add_kassa"]],
]])
]);
}
}

if($data == "Kassalarim"){
$result = mysqli_query($connect,"SELECT * FROM `shops` WHERE `user_id` = '$cid'");
$i = 0;
$key = [];
while($us = mysqli_fetch_assoc($result)){
$i++;
$shop_id = $us['id'];
$status = $us['status'];
if($status == "waiting") $icon = "🔄";
elseif($status == "confirm") $icon = "✅";
elseif($status == "canceled") $icon = "⛔";
$shop_name = base64_decode($us['shop_name']);
$key[]=[["text"=>"$i. $icon $shop_name","callback_data"=>"kassa_set=$shop_id"]];
}
$key[] = [['text'=>"➕ Kassa qoʻshish",'callback_data'=>"add_kassa"]];
$kassalar = json_encode(['inline_keyboard'=>$key]);
$result = mysqli_query($connect,"SELECT * FROM `shops` WHERE `user_id` = '$cid'");
$rew = mysqli_fetch_assoc($result);
if($rew){
bot('editmessagetext',[
'chat_id'=>$cid,
'message_id'=>$mid,
'text'=>"🏪 <b>Kassalaringiz roʻyhati:</b>",
'parse_mode'=>'html',
'reply_markup'=>$kassalar,
]);
}else{
bot('editmessagetext',[
'chat_id'=>$cid,
'message_id'=>$mid,
'text'=>"⚠️ Sizda hech qanday kassalar mavjud emas!",
'parse_mode'=>'html',
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
$confirm_text = "";

if ($status == "waiting") {
$icon = "🔄 Kutilmoqda...";
$keyboard['inline_keyboard'][] = [['text' => "⏪ Ortga", 'callback_data' => "Kassalarim"]];
} elseif ($status == "confirm") {
$confirm_text = "\n📆 Oylik toʻlovga: <b>$over</b> kun qoldi!
🔎 Oylik toʻlov holati: <b>$month_status</b>
📞 Ulangan raqam: <code>" . ($phone ?: "Yo'q") . "</code>

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
$keyboard['inline_keyboard'][] = [['text' => "💰 Toʻlovlar tarixi", 'callback_data' => "kassa_history=$shop_id=$id"]];
$keyboard['inline_keyboard'][] = [['text' => "⏪ Ortga", 'callback_data' => "Kassalarim"]];
}
}
} elseif ($status == "canceled") {
$icon = "⛔ Bekor qilingan!";
$keyboard['inline_keyboard'][] = [['text' => "⏪ Ortga", 'callback_data' => "Kassalarim"]];
}

bot('editMessageText', [
'chat_id' => $cid,
'message_id' => $mid,
'text' => "<b>$nomi ($icon)</b>
$confirm_text
🆔 Shop ID: <code>$shop_id</code>
🔑 Shop Key: <code>$shop_key</code>
🔗 Manzil: <b>$address</b>
📖 Ma'lumot: $shop_info",
'parse_mode' => 'html',
'reply_markup' => json_encode($keyboard)
]);
}

// To'lovlar tarixi — endi DB dan o'qiydi
if (mb_stripos($data, "kassa_history=") !== false) {
$parts = explode("=", $data);
$shop_id_hist = $parts[1] ?? null;
$set_id = $parts[2] ?? null;

if ($shop_id_hist && $set_id) {
$res = mysqli_query($connect, "SELECT * FROM payments WHERE used_order LIKE '%' ORDER BY created_at DESC LIMIT 20");
// shop bilan bog'liq to'lovlarni olish
$shop_res = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM shops WHERE shop_id = '$shop_id_hist'"));
$res = mysqli_query($connect, "SELECT * FROM payments WHERE status = 'used' ORDER BY created_at DESC LIMIT 20");

if (mysqli_num_rows($res) > 0) {
$list = "";
$i = 0;
while ($row = mysqli_fetch_assoc($res)) {
$i++;
$amt = $row['amount'];
$merch = $row['merchant'] ?? 'Noma\'lum';
$dt = $row['date'] ?? $row['created_at'];
$list .= "<b>$i</b>. 💵 $amt so'm\n🏦 $merch\n📆 $dt\n\n";
}

bot('editMessageText', [
'chat_id' => $cid,
'message_id' => $mid,
'text' => "✅ <b>Tasdiqlangan toʻlovlar:</b>\n\n" . $list,
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
}
}

// Kassa ulash — endi MadelineProto o'rniga telefon raqam saqlaydi
if (mb_stripos($data, "kassa_connect=") !== false && joinchat($cid) == true) {
$ex = explode("=", $data)[1];
$result = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM shops WHERE shop_id = '$ex'"));

if ($result['status'] == "confirm" && $result['month_status'] == "Toʻlandi") {
bot('DeleteMessage', [
'chat_id' => $cid,
'message_id' => $mid,
]);

bot('sendMessage', [
'chat_id' => $cid,
'text' => "<b>📞 Telefon raqamingizni yuboring!</b>

<i>Humo/UzCard kartangizga ulangan raqamni yuboring.</i>",
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

mysqli_query($connect, "UPDATE users SET step = 'save_phone=$ex' WHERE user_id = $cid");
exit;
} else {
bot('answerCallbackQuery', [
'callback_query_id' => $qid,
'text' => "⚠️ Kassa faol va oylik toʻlov toʻlangan bo'lishi kerak!",
'show_alert' => true
]);
exit;
}
}

// Telefon raqamni saqlash
if (mb_stripos($step, "save_phone=") !== false && isset($contact) && $text != "⏪ Ortga" && $text != "/start") {
$ex = explode("=", $step)[1];
mysqli_query($connect, "UPDATE users SET step = 'null' WHERE user_id = $cid");

$number = str_replace("+", "", $number);
mysqli_query($connect, "UPDATE shops SET phone = '$number' WHERE shop_id = '$ex'");

bot('sendMessage', [
'chat_id' => $cid,
'text' => "✅ <b>Telefon raqamingiz ulandi!</b>

📞 Raqam: <code>+$number</code>

Endi 4800 ga <b>infoset</b> deb SMS yuboring va emailni ulab qo'ying.",
'parse_mode' => 'html',
'reply_markup' => $m
]);
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
'text' => "✅ <b>Oylik toʻlov qilindi!</b>",
'parse_mode' => 'html',
'reply_markup' => $keyboard
]);
mysqli_query($connect, "UPDATE shops SET over_day = 30 WHERE id = '$set_id'");
mysqli_query($connect, "UPDATE shops SET month_status = 'Toʻlandi' WHERE id = '$set_id'");
} else {
bot('answerCallbackQuery', [
'callback_query_id' => $qid,
'text' => "⚠ Hisobingizda yetarli mablagʻ yoʻq. Kerak: 20,000 soʻm",
'show_alert' => true
]);
exit;
}
}

if (mb_stripos($data, "new_key=") !== false) {
$id = explode("=", $data)[1];
$apikeys = generate();
mysqli_query($connect, "UPDATE shops SET shop_key = '$apikeys' WHERE shop_id = $id");
bot('editMessageText', [
'chat_id' => $cid,
'message_id' => $mid,
'text' => "✅ <b>API kalit yangilandi!</b> <code>$apikeys</code>",
'parse_mode' => 'HTML',
]);
}

if ($data == "add_kassa" && joinchat($cid) == true) {
bot('deletemessage', [
'chat_id' => $cid,
'message_id' => $mid,
]);
bot('sendmessage', [
'chat_id' => $cid,
'text' => "🛍️ <b>Yangi kassa qoʻshish!</b>

<i>Kassa nomini yuboring:</i>",
'parse_mode' => 'html',
'reply_markup' => $back,
]);
mysqli_query($connect, "UPDATE users SET step = 'add_kassa' WHERE user_id = $cid");
exit;
}

if ($step == "add_kassa") {
if ($text == "⏪ Ortga") {
mysqli_query($connect, "UPDATE users SET step = 'null' WHERE user_id = $cid");
} else {
$result = mysqli_query($connect, "SELECT * FROM shops WHERE shop_name = '$text'");
$rew = mysqli_fetch_assoc($result);
if ($rew) {
bot('sendMessage', [
'chat_id' => $cid,
'text' => "⚠️ <i>Ushbu nom bilan kassa mavjud, boshqa nom yuboring!</i>",
'parse_mode' => 'html',
]);
exit;
} else {
bot('sendmessage', [
'chat_id' => $cid,
'text' => "✅ <b>Kassa havolasini kiriting!</b>

<i>Masalan: @username yoki tolovchi.uz</i>",
'parse_mode' => 'html',
'reply_markup' => $back,
]);
mysqli_query($connect, "UPDATE users SET step = 'add_kassa_address-$text' WHERE user_id = $cid");
exit;
}
}
}

if (mb_stripos($step, "add_kassa_address-") !== false) {
$name = explode("-", $step)[1];
if ($text == "⏪ Ortga") {
mysqli_query($connect, "UPDATE users SET step = 'null' WHERE user_id = $cid");
} else {
if (preg_match('/^@[\w]{5,25}$|^[a-z0-9-]+\.[a-z]{2,}$/i', $text)) {
bot('sendmessage', [
'chat_id' => $cid,
'text' => "✅ <b>Kassa manzili qabul qilindi!</b>

<i>Kassa haqida ma'lumot kiriting:</i>",
'parse_mode' => 'html',
'reply_markup' => $back,
]);
$base_name = base64_encode($name);
mysqli_query($connect, "UPDATE users SET step = 'add_kassa_info-$base_name-$text' WHERE user_id = $cid");
} else {
bot('sendMessage', [
'chat_id' => $cid,
'text' => "⚠️ <b>Noto'g'ri format!</b>

<i>@username yoki domen kiriting:</i>",
'parse_mode' => 'html',
]);
exit;
}
}
}

if (mb_stripos($step, "add_kassa_info-") !== false) {
$name = explode("-", $step)[1];
$address = explode("-", $step)[2];
$base_info = base64_encode($text);
if ($text == "⏪ Ortga") {
mysqli_query($connect, "UPDATE users SET step = 'null' WHERE user_id = $cid");
} else {
$shop_id = rand(111111, 999999);
$shop_key = $new_key;

mysqli_query($connect, "INSERT INTO shops (`user_id`,`shop_name`,`shop_info`,`shop_id`,`shop_key`,`shop_address`,`shop_balance`,`status`,`date`) VALUES ('$cid','$name','$base_info','$shop_id','$shop_key','$address','0','waiting','$sana')");

bot('sendmessage', [
'chat_id' => $cid,
'text' => "✅ <b>Kassa ma'lumotlari administratorga yuborildi!</b>

<i>Tasdiqlanishini kuting!</i>",
'parse_mode' => 'html',
'reply_markup' => $m,
]);

mysqli_query($connect, "UPDATE users SET step = 'null' WHERE user_id = $cid");

bot('sendmessage', [
'chat_id' => $administrator,
'text' => "✅ <b>Yangi kassa!</b>

🆔 Kassa id: $shop_id
🔑 Kassa key: $shop_key
🛍️ Kassa nomi: <b>" . base64_decode($name) . "</b>
🔗 Kassa address: $address
📖 Kassa haqida: <b>$text</b>

📅 Sana: <b>$sana</b> | ⏰ Soat: <b>$soat</b>",
'parse_mode' => 'html',
'reply_markup' => json_encode([
'inline_keyboard' => [
[['text' => "✅ Tasdiqlash", 'callback_data' => "confirm=$shop_id"]],
[['text' => "⛔ Bekor qilish", 'callback_data' => "canceled=$shop_id"]],
]])
]);
}
}

if (mb_stripos($data, "confirm=") !== false) {
$id = explode("=", $data)[1];
$rew = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM shops WHERE shop_id = '$id'"));
$userid = $rew['user_id'];
bot('editMessageText', [
'chat_id' => $cid,
'message_id' => $mid,
'text' => "✅ <b>Yangi kassa tasdiqlandi!</b> #$id",
'parse_mode' => 'html',
]);
bot('sendmessage', [
'chat_id' => $userid,
'text' => "✅ <b>Sizning <code>#$id</code> kassangiz tasdiqlandi!</b>",
'parse_mode' => 'html',
'reply_markup' => $m,
]);
mysqli_query($connect, "UPDATE shops SET status = 'confirm' WHERE shop_id = $id");
exit;
}

if (mb_stripos($data, "canceled=") !== false) {
$id = explode("=", $data)[1];
$rew = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM shops WHERE shop_id = '$id'"));
$userid = $rew['user_id'];
bot('editMessageText', [
'chat_id' => $cid,
'message_id' => $mid,
'text' => "⛔ <b>Yangi kassa bekor qilindi!</b> #$id",
'parse_mode' => 'html',
]);
bot('sendmessage', [
'chat_id' => $userid,
'text' => "⛔ <b>Sizning <code>#$id</code> kassangiz bekor qilindi!</b>",
'parse_mode' => 'html',
'reply_markup' => $m,
]);
mysqli_query($connect, "UPDATE shops SET status = 'canceled' WHERE shop_id = $id");
exit;
}

if ($text == "🗄️ Boshqaruv" or $text == "/panel") {
if (in_array($cid, $admin)) {
mysqli_query($connect, "UPDATE users SET step = 'null' WHERE user_id = $cid");
bot('sendMessage', [
'chat_id' => $cid,
'text' => "<b>Administrator paneli!</b>",
'parse_mode' => 'html',
'reply_markup' => $panel,
]);
exit;
}
}

if ($text == "📊 Statistika") {
if (in_array($cid, $admin)) {
$res = mysqli_query($connect, "SELECT * FROM `users`");
$stat = mysqli_num_rows($res);
$bugunid = mysqli_query($connect, "SELECT * FROM users WHERE date = '$sana'");
$bugun = mysqli_num_rows($bugunid);

$stat5 = mysqli_query($connect, "SELECT * FROM users ORDER BY id DESC LIMIT 5");
$textt = "";
while ($user = mysqli_fetch_assoc($stat5)) {
$id = $user['user_id'];
$textt .= "👤 <a href='tg://user?id=$id'>$id</a>\n";
}

bot('sendMessage', [
'chat_id' => $cid,
'text' => "📊 <b>Statistika</b>

▫️ Jami foydalanuvchilar: $stat ta
▪️ Bugun qo'shilgan: $bugun ta

▪️ Oxirgi 5ta:
$textt",
'parse_mode' => "html",
'reply_markup' => $panel
]);
exit;
}
}

if ($text == "👤 Foydalanuvchi") {
if (in_array($cid, $admin)) {
mysqli_query($connect, "UPDATE users SET step = 'user_check' WHERE user_id = $cid");
bot('sendMessage', [
'chat_id' => $cid,
'text' => "<i>ID raqamni kiriting:</i>",
'parse_mode' => 'html',
'reply_markup' => json_encode([
'resize_keyboard' => true,
'keyboard' => [
[['text' => "🗄️ Boshqaruv"]],
]])
]);
exit;
}
}

if ($step == "user_check") {
if (in_array($cid, $admin)) {
if ($text == "🗄️ Boshqaruv") {
mysqli_query($connect, "UPDATE users SET step = 'null' WHERE user_id = $cid");
exit;
}
mysqli_query($connect, "UPDATE users SET step = 'null' WHERE user_id = $cid");
$check = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM users WHERE user_id = $text"));
if (!$check) {
$check = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM users WHERE id = $text"));
}
if ($check) {
$rew = $check;
bot('sendMessage', [
'chat_id' => $cid,
'text' => "<b>📑 Foydalanuvchi maʼlumotlari!</b>

Balansi: <b>" . $rew['balance'] . "</b> soʻm",
'parse_mode' => "HTML",
'reply_markup' => json_encode([
'inline_keyboard' => [
[['text' => "➕ Pul qoʻshish", 'callback_data' => "pul=plus=$text"], ['text' => "➖ Pul ayirish", 'callback_data' => "pul=minus=$text"]],
]])
]);
exit;
} else {
bot('sendMessage', [
'chat_id' => $cid,
'text' => "<b>🥲 Foydalanuvchi topilmadi!</b>",
'parse_mode' => "HTML",
'reply_markup' => $panel
]);
exit;
}
}
}

if (mb_stripos($data, "pul=") !== false) {
$type = explode("=", $data)[1];
$id = explode("=", $data)[2];
mysqli_query($connect, "UPDATE users SET step = 'pul=$type=$id' WHERE user_id = $cid");
bot('sendMessage', [
'chat_id' => $cid,
'text' => "<b>Pul miqdorini kiriting:</b>",
'parse_mode' => 'html',
'reply_markup' => json_encode([
'resize_keyboard' => true,
'keyboard' => [
[['text' => "🗄️ Boshqaruv"]],
]])
]);
exit;
}

if (mb_stripos($step, "pul=") !== false) {
$type = explode("=", $step)[1];
$id = explode("=", $step)[2];
mysqli_query($connect, "UPDATE users SET step = 'null' WHERE user_id = $cid");

$check = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM users WHERE user_id = $id"));
if (!$check) {
$check = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM users WHERE id = $id"));
}

if ($type == "plus") {
$confirm = $check['balance'] + $text;
$texti = "✔️ <b>$text soʻm qoʻshildi!</b>";
} else {
$confirm = $check['balance'] - $text;
$texti = "⚠️ <b>$text soʻm ayirildi!</b>";
}

mysqli_query($connect, "UPDATE users SET balance = '$confirm' WHERE user_id = $id");

bot('sendMessage', [
'chat_id' => $cid,
'text' => $texti,
'parse_mode' => 'html',
'reply_markup' => $panel
]);
exit;
}

if ($text == "📨 Xabar yuborish") {
if (in_array($cid, $admin)) {
$result = mysqli_query($connect, "SELECT * FROM `send`");
$row = mysqli_fetch_assoc($result);
if (!$row) {
bot('SendMessage', [
'chat_id' => $cid,
'text' => "<b>Foydalanuvchilarga yubormoqchi bo'lgan xabaringizni kiriting:</b>",
'parse_mode' => 'html',
'reply_markup' => json_encode([
'resize_keyboard' => true,
'keyboard' => [
[['text' => "🗄️ Boshqaruv"]],
]])
]);
mysqli_query($connect, "UPDATE users SET step = 'send' WHERE user_id = '$cid'");
exit;
} else {
bot('SendMessage', [
'chat_id' => $cid,
'text' => "Hozirda xabar yuborish davom etmoqda. Keyinroq qayta urinib ko'ring!",
'parse_mode' => 'HTML',
]);
}
}
}

if ($step == "send" and in_array($cid, $admin)) {
$result = mysqli_query($connect, "SELECT * FROM users ORDER BY id DESC LIMIT 1");
$row = mysqli_fetch_assoc($result);
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
'reply_markup' => $panel,
]);
mysqli_query($connect, "UPDATE users SET step = 'null' WHERE user_id = '$cid'");
exit;
}

if ($text == "📢 Kanallar" and in_array($cid, $admin)) {
bot('sendMessage', [
'chat_id' => $cid,
'text' => "<b>👉 Qo'shmoqchi bo'lgan kanal turini tanlang:</b>",
'parse_mode' => 'html',
'reply_markup' => json_encode([
'inline_keyboard' => [
[['text' => "Ommaviy", 'callback_data' => "request-false"]],
[['text' => "So'rov qabul qiluvchi", 'callback_data' => "request-true"]],
[['text' => "Ixtiyoriy havola", 'callback_data' => "socialnetwork"]]
]
])
]);
}

if ($data == "socialnetwork") {
bot('deleteMessage', ['chat_id' => $cid, 'message_id' => $mid]);
bot('sendMessage', [
'chat_id' => $cid,
'text' => "<b>Havola uchun nom yuboring:</b>",
'parse_mode' => 'html',
'reply_markup' => json_encode([
'resize_keyboard' => true,
'keyboard' => [[['text' => "🗄️ Boshqaruv"]]]
])
]);
mysqli_query($connect, "UPDATE users SET step = 'socialnetwork_step1' WHERE user_id = '$cid'");
}

if ($step == "socialnetwork_step1") {
if (isset($text)) {
mysqli_query($connect, "UPDATE users SET step = 'socialnetwork_step2', action = '$text' WHERE user_id = '$cid'");
bot('sendMessage', [
'chat_id' => $cid,
'text' => "<b>✅ $text qabul qilindi!</b>\n\n<i>Havolani kiriting:</i>",
'parse_mode' => 'html'
]);
}
}

if ($step == "socialnetwork_step2") {
if (isset($text)) {
$nom_row = mysqli_fetch_assoc(mysqli_query($connect, "SELECT action FROM users WHERE user_id = '$cid'"));
$nom = base64_encode($nom_row['action']);
$sql = "INSERT INTO `channels` (`type`, `link`, `title`, `channelID`) VALUES ('social', '$text', '$nom', '')";
if ($connect->query($sql)) {
bot('sendMessage', [
'chat_id' => $cid,
'text' => "<b>✅ Kanal muvoffaqiyatli qo'shildi</b>",
'parse_mode' => 'html',
'reply_markup' => $panel
]);
mysqli_query($connect, "UPDATE users SET step = 'null' WHERE user_id = '$cid'");
}
}
}

if (mb_stripos($data, "request-") !== false) {
$type = explode("-", $data)[1];
mysqli_query($connect, "UPDATE users SET step = 'qosh', action = '$type' WHERE user_id = '$cid'");
bot('sendMessage', [
'chat_id' => $cid,
'text' => "<b>Endi kanalizdan \"forward\" xabar yuboring:</b>",
'parse_mode' => 'html',
'reply_markup' => json_encode([
'resize_keyboard' => true,
'keyboard' => [[['text' => "🗄️ Boshqaruv"]]]
])
]);
}

if ($step == "qosh" and isset($message->forward_origin)) {
$kanal_id = $message->forward_origin->chat->id;
$type_row = mysqli_fetch_assoc(mysqli_query($connect, "SELECT action FROM users WHERE user_id = '$cid'"));
$type = $type_row['action'];
if ($type == "true") {
$link = bot('createChatInviteLink', ['chat_id' => $kanal_id, 'creates_join_request' => true])->result->invite_link;
$sql = "INSERT INTO `channels` (`channelID`, `link`, `type`) VALUES ('$kanal_id', '$link', 'request')";
} elseif ($type == "false") {
$link = "https://t.me/" . $message->forward_origin->chat->username;
$sql = "INSERT INTO `channels` (`channelID`, `link`, `type`) VALUES ('$kanal_id', '$link', 'lock')";
}
if ($connect->query($sql)) {
bot('sendMessage', [
'chat_id' => $cid,
'text' => "<b>✅ Kanal muvoffaqiyatli qo'shildi</b>",
'parse_mode' => 'html',
'reply_markup' => $panel
]);
} else {
bot('sendMessage', [
'chat_id' => $cid,
'text' => "<b>⚠️ Xatolik:</b> <code>{$connect->error}</code>",
'parse_mode' => 'html',
'reply_markup' => $panel
]);
}
mysqli_query($connect, "UPDATE users SET step = 'null' WHERE user_id = '$cid'");
}

if ($text == "🗑️ Kanal o'chirish") {
$result = $connect->query("SELECT * FROM `channels`");
if ($result->num_rows > 0) {
$button = [];
while ($row = $result->fetch_assoc()) {
$type = $row['type'];
$channelID = $row['channelID'];
if ($type == "lock" or $type == "request") {
$gettitle = bot('getchat', ['chat_id' => $channelID])->result->title;
} else {
$gettitle = base64_decode($row['title']);
}
$button[] = ['text' => "🗑️ " . $gettitle, 'callback_data' => "delchan=" . $channelID];
}
$keyboard2 = array_chunk($button, 1);
$keyboard = json_encode(['inline_keyboard' => $keyboard2]);
bot('sendMessage', [
'chat_id' => $cid,
'text' => "<b>Kerakli kanalni tanlang:</b>",
'parse_mode' => 'html',
'reply_markup' => $keyboard
]);
} else {
bot('sendMessage', [
'chat_id' => $cid,
'text' => "<b>Hech qanday kanal ulanmagan!</b>",
'parse_mode' => 'html'
]);
}
}

if (stripos($data, "delchan=") !== false) {
$ex = explode("=", $data)[1];
$connect->query("DELETE FROM channels WHERE channelID = '$ex'");
bot('editMessageText', [
'chat_id' => $cid,
'message_id' => $mid,
'text' => "<b>✅ Kanal oʻchirildi!</b>",
'parse_mode' => 'html'
]);
}
?>
