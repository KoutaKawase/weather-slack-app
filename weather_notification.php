<?php
use Cmfcmf\OpenWeatherMap;
use Cmfcmf\OpenWeatherMap\Exception as OWMException;
use \Nyholm\Psr7\Factory\Psr17Factory;
use Http\Adapter\Guzzle6\Client as GuzzleAdapter;
use Yosymfony\Toml\Toml;
use \Curl\Curl;

//必要なファイル読み込み
require "./vendor/autoload.php";

function createOWMobject(string $owmApiKey): object {
    //オブジェクト生成に必要なオブジェクトを生成
    $httpRequestFactory = new Psr17Factory();
    $httpClient = GuzzleAdapter::createWithConfig([]);
    $owm = new OpenWeatherMap($owmApiKey, $httpClient, $httpRequestFactory);
    return $owm;
}

function getWeather(object $owm, string $location, string $unit, string $lang): object {
    try {
        $weather = $owm->getWeather($location, $unit, $lang);
        return $weather;
    } catch(OWMException $e) {
        print 'OpenWeatherMap exception: ' . $e->getMessage() . ' (Code ' . $e->getCode() . ').';
    } catch(\Exception $e) {
        print 'General exception: ' . $e->getMessage() . ' (Code ' . $e->getCode() . ').';
    }
}

function createWeatherMessage(object $weather, string $location): string {
    //数字と曜日を対応させるための曜日配列
    $week = ["日曜日", "月曜日", "火曜日", "水曜日", "木曜日", "金曜日", "土曜日"];
    //草津を漢字に
    $location = "草津";
    //現在の曜日取得
    $weekNumber = date("N");
    $nowWeek = $week[$weekNumber];
    //現在時刻取得
    $now = date("現在は Y年m月d日 {$nowWeek} G時i分s秒 です。");
    $weatherMessage = $now . "{$location}の気温は" . $weather->temperature . "です。";
    return $weatherMessage;
}

function notificationToSlack(string $message, string $url): void {
    $curl = new Curl();
    //ヘッダー設定
    $curl->setHeader('Content-Type', 'application/json');
    //送信
    $curl->post($url, $message);
    //レスポンスを表示
    print("RESPONSE: " . strtoupper($curl->response) . "\n");
}

function Main(): void {
    $weatherMessage = null;
    $owm = null;
    $weather = null;
    //設定ファイル読み込み
    $configs = Toml::ParseFile('./config.toml');
    //OpenWeatherMapオブジェクト生成
    $owm = createOWMobject($configs["openWeatherMapApiKey"]);
    //天気取得
    $weather = getWeather($owm, $configs["location"], $configs["unit"], $configs["language"]);
    //天気からメッセージを生成しJSON形式にする
    $weatherMessage = json_encode(["text" => createWeatherMessage($weather, $configs["location"])]);
    //curlでslackに送信
    notificationToSlack($weatherMessage, $configs["slackUrl"]);
}

Main();