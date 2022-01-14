<?php

require __DIR__ . "/boot.php";

$account_sid = $_ENV["TWILIO_ACCOUNT_SID"];
$auth_token = $_ENV["TWILIO_AUTH_TOKEN"];
$twilio_number = $_ENV["TWILIO_PHONE_NUMBER"];
$sms_to_number = $_ENV["SMS_SEND_TO"];
$ikea_article_number = $_ENV["IKEA_ARTICLE_NUMBER"];
$ikea_api_token = $_ENV['IKEA_API_TOKEN'];
$twilio_client = getTwilioClient($account_sid, $auth_token);
$client = new \GuzzleHttp\Client();

// -----------------------------------------------------------------

$jsonStores = getIkeaStores($client);
$jsonAvailabily = getIkeaAvailability($client, $ikea_article_number, $ikea_api_token);
$found = processIkeaAvailability($jsonAvailabily->availabilities);

$storesAvailableForCashCarry = matchIkeaStoresAgainstAvailability($jsonStores, $found['availableForCashCarry']);
$availableForCashCarry = count($storesAvailableForCashCarry) > 0;
printResults($availableForCashCarry, $storesAvailableForCashCarry, "Purchase in store");

$storesAvailableForClickCollect = matchIkeaStoresAgainstAvailability($jsonStores, $found['availableForClickCollect']);
$availableForClickCollect = count($storesAvailableForClickCollect) > 0;
printResults($availableForClickCollect, $storesAvailableForClickCollect, "Purchase online and pick-up in store");

$availableForHomeDelivery = count($found['availableForHomeDelivery']) > 0;
if($availableForHomeDelivery)
  echo "\n[x] Purchase online for delivery\n-----------------------------------------\n\n";

if(!$availableForCashCarry && !$availableForClickCollect && !$availableForHomeDelivery){
  echo "\n[x] Nothing yet! :'(\n\n";
  exit;
}

sendTextMessage($twilio_client, $twilio_number, $sms_to_number, "Stuff is available!");