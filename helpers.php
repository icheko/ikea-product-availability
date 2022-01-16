<?php

use Psr\Http\Message\RequestInterface;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\Client;

function addClientHeader($header, $value)
{
    return function (callable $handler) use ($header, $value) {
        return function (
            RequestInterface $request,
            array $options
        ) use ($handler, $header, $value) {
            $request = $request->withHeader($header, $value);
            return $handler($request, $options);
        };
    };
}

function getHttpClient($api_token){
  $stack = new HandlerStack();
  $stack->setHandler(new CurlHandler());
  $stack->push(addClientHeader('x-client-id', $api_token));
  $client = new Client(['handler' => $stack]);
  return $client;
}

function getTwilioClient($account_sid, $auth_token){
  return new \Twilio\Rest\Client($account_sid, $auth_token);
}

function sendTextMessage(\Twilio\Rest\Client $client, string $twilio_number, string $to_number, string $message){
  $client->messages->create(
      $to_number,
      array(
          'from' => $twilio_number,
          'body' => $message
      )
  );
}

function getIkeaAvailability(\GuzzleHttp\Client $client, $article_number){
  $response = $client->request('GET', "https://api.ingka.ikea.com/cia/availabilities/ru/us?itemNos={$article_number}&expand=StoresList,Restocks,SalesLocations", [
    'headers' => [
      'authority'     => 'api.ingka.ikea.com',
      'accept'        => 'application/json;version=2',
      'pragma'        => 'no-cache',
      'cache-control' => 'no-cache',
    ]
  ]);

  if (!$response->getStatusCode() == "200")
    throw new Exception("getIkeaStores: {$response->getBody()}");

  return json_decode($response->getBody());
}

function getIkeaStores(\GuzzleHttp\Client $client){

  $response = $client->request('GET', 'https://www.ikea.com/us/en/meta-data/navigation/stores-detailed.json', [
    'headers' => [
      'pragma'        => 'no-cache',
      'cache-control' => 'no-cache',
    ]
  ]);

  if (!$response->getStatusCode() == "200")
    throw new Exception("getIkeaStores: {$response->getBody()}");

  return json_decode($response->getBody());
}

function processIkeaAvailability($availabilities){
  $found = [
    "availableForCashCarry" => [],
    "availableForClickCollect" => [],
    "availableForHomeDelivery" => [],
  ];

  foreach($availabilities as $availability){
    if(property_exists($availability, 'availableForCashCarry') && $availability->{'availableForCashCarry'} == "1"){
      $found["availableForCashCarry"][] = $availability->{'classUnitKey'};
    }
  
    if(property_exists($availability, 'availableForClickCollect') && $availability->{'availableForClickCollect'} == "1"){
      $found["availableForClickCollect"][] = $availability->{'classUnitKey'};
    }
  
    if(property_exists($availability, 'availableForHomeDelivery') && $availability->{'availableForHomeDelivery'} == "1"){
      //print_r($availability->{'buyingOption'}->{'homeDelivery'});
      $found["availableForHomeDelivery"][] = $availability->{'classUnitKey'};
    }
  }

  return $found;
}

function matchIkeaStoresAgainstAvailability(array $stores, array $storesAvailability){
  $storeNames = [];

  foreach($storesAvailability as $store){
    if($store->{'classUnitType'} != "STO")
      continue;
    
    $storeFriendly = findIkeaStoreById($stores, $store->{'classUnitCode'});
    $storeNames[] = $storeFriendly->{'name'};
  }

  return $storeNames;
}

function findIkeaStoreById(array $stores, string $id){
  foreach($stores as $store){
    if($store->{'id'} == $id){
      return $store;
    }
  }
}

function printResults(bool $available, array $stores, $message){
  if($available){
    echo "\n[x] $message\n-----------------------------------------\n";
    print_r($stores);
  }
}