<?php
date_default_timezone_set("Asia/Tashkent");

if (!file_exists('madeline.php')) {
copy('https://phar.madelineproto.xyz/madeline.php', 'madeline.php');
}

include 'madeline.php';

use danog\MadelineProto\API;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\AppInfo;

$settings = (new Settings)->setAppInfo((new AppInfo)->setApiId()->setApiHash('));

$MadelineProto = new API('session.madeline', $settings);

if (isset($_GET['number'])) {
try {
$MadelineProto->phoneLogin($_GET['number']);
echo json_encode(['status' => 'code'], JSON_PRETTY_PRINT);
} catch (Exception $e) {
echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_PRETTY_PRINT);
}
exit;
}

if (isset($_GET['code'])) {
try {
$result = $MadelineProto->completePhoneLogin($_GET[code']);
if ($result['_'] === 'account.password') {
echo json_encode(['status' => 'password'], JSON_PRETTY_PRINT);
} else {
echo json_encode(['status' => 'ok'], JSON_PRETTY_PRINT);
}
} catch (Exception $e) {
echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_PRETTY_PRINT);
}
exit;
}

if (isset($_GET['password'])) {
try {
$MadelineProto->complete2faLogin($_GET['password']);
echo json_encode(['status' => 'ok'], JSON_PRETTY_PRINT);
} catch (Exception $e) {
echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_PRETTY_PRINT);
}
exit;
}

if (file_exists(MadelineProto.log")) unlink("MadelineProto.log");  
exit;
}

try {
$me = $MadelineProto->getSelf();
echo json_encode(['status' => 'logged_in', 'user' => $me['username'] ?? 'NoUsername'], JSON_PRETTY_PRINT);
} catch (Exception $e) {
echo json_encode(['status' => 'not_logged_in', 'error' => $e->getMessage()], JSON_PRETTY_PRINT);
}

if (file_exists("MadelineProto.log")) unlink("MadelineProto.log");  

?>