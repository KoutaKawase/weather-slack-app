<?php
use Cmfcmf\OpenWeatherMap;
use Cmfcmf\OpenWeatherMap\Exception as OWMException;
use \Nyholm\Psr7\Factory\Psr17Factory;
use Http\Adapter\Guzzle6\Client as GuzzleAdapter;
use Yosymfony\Toml\Toml;
use \Curl\Curl;
use League\Csv\Reader;
use League\Csv\Statement;


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

function getPhrase(): string {
    //名言格納用
    $phrases = [];
    //おまじない
    if (!ini_get("auto_detect_line_endings")) {
        ini_set("auto_detect_line_endings", '1');
    }
    //CSV読み込み
    $csv = Reader::createFromPath('./phrases.csv', 'r');
    //オフセットを設定
    $csv->setHeaderOffset(0);
    //レコードをすべて取得
    $records = $csv->getRecords(["phrase", "name"]);
    //レコードを回し配列に名言メッセを格納していく
    foreach($records as $record) {
        array_push($phrases, $record['phrase'] . " By " . $record['name']);
    }
    //ランダムにキーを取得
    $rand_key = array_rand($phrases);
    //ランダムに名言メッセを取得
    $phrase = $phrases[$rand_key];
    return $phrase;
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
    //名言をランダムに取得
    $phrase = getPhrase();
    //メッセージ作成
    $weatherMessage = <<< EOM
    {$now} {$location}の気温は{$weather->temperature->now}です。
    天候は{$weather->weather}です。
    湿度は{$weather->humidity}。
    風速は{$weather->wind->speed}です。
    今回の名言: 【{$phrase}】
    EOM;
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