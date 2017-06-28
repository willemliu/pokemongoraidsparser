<?php
/**
 * Turn on all error reporting.
 */
error_reporting(E_ALL ^ (E_STRICT | E_DEPRECATED | E_NOTICE));
ini_set('display_errors', 1);
include_once ('./lib/DB.php');
$file = basename(__FILE__);
$city = (strcasecmp(basename(__FILE__, '.php'), 'index') == 0) ? 'Amsterdam' : ucfirst(basename(__FILE__, '.php'));
$db = new DB();
$dbh = $db->connect();

$method = $_SERVER['REQUEST_METHOD'];
switch ($method) {
  case 'POST':
    switch ($_REQUEST['fn']) {
      case 'joinRaid':
        joinRaid($_REQUEST);
        break;
      case 'addRaidData':
        addRaidData($_REQUEST);
        exit(0);
        break;
      case 'addPokemon':
        addPokemon($_REQUEST);
        break;
      case 'stats':
        if(logCommand($_REQUEST)) {
          echo stats($_REQUEST);
          exit(0);
        } else {
          $msg['msg'] = 'Stats already requested. Ignoring command.';
          echo json_encode($msg);
          exit(0);
        }
        break;
    }
    break;
  case 'GET':
  case 'PUT':
  case 'HEAD':
  case 'DELETE':
  case 'OPTIONS':
  default:
    break;
}

function addRaidData($request) {    
  global $dbh;
  if(isset($request['start']) && isset($request['end']) && strlen($request['start']) > 0 && strlen($request['end']) > 0 && $request['start'] != 'null' && $request['end'] != 'null') {
    try {
      if ($dbh->inTransaction() === false) {
        $dbh->beginTransaction();
      }
      $address = getAddressFromDirection($request['direction']);

      $query = "INSERT IGNORE INTO raids2
                               (gym, lvl, start, end, pokemon, direction, team) VALUES (:gym, :lvl, :start, :end, :pokemon, :direction, :address, :team)";
      if(isset($request['boss']) && strlen($request['boss']) > 0 && $request['boss'] != 'null') {
        $query = "INSERT INTO raids2
                               (gym, lvl, start, end, pokemon, direction, address, team, gymhuntr_boss) VALUES (:gym, :lvl, :start, :end, :pokemon, :direction, :address, :team, 1)
                               ON DUPLICATE KEY UPDATE pokemon=:pokemon, gym=:gym, address=:address, team=:team, gymhuntr_boss=1";
      } else if(isset($address) && strlen($address) > 0) {
        $query = "INSERT INTO raids2
                               (gym, lvl, start, end, pokemon, direction, address, team) VALUES (:gym, :lvl, :start, :end, :pokemon, :direction, :address, :team)
                               ON DUPLICATE KEY UPDATE gym=:gym, address=:address, team=:team";
      }
      $stmt = $dbh->prepare($query);
      $tz_object = new DateTimeZone('Europe/Amsterdam');
      $today = new DateTime();
      $today->setTimezone($tz_object);
      $today->setTime($request['hours'], $request['minutes']);
      if(strlen($request['gym']) === 0) {
        $request['gym'] = '??';
      }
      $stmt->bindParam(":gym", $request['gym'], PDO::PARAM_STR);
      $stmt->bindParam(":lvl", $request['lvl'], PDO::PARAM_INT);
      $stmt->bindParam(":start", $request['start'], PDO::PARAM_STR);
      $stmt->bindParam(":end", $request['end'], PDO::PARAM_STR);
      $stmt->bindParam(":pokemon", $request['boss'], PDO::PARAM_STR);
      $stmt->bindParam(":direction", $request['direction'], PDO::PARAM_STR);
      $stmt->bindParam(":address", $address, PDO::PARAM_STR);
      $stmt->bindParam(":team", $request['team'], PDO::PARAM_STR);
      $stmt->execute();
      $dbh->commit();
    }
    catch(PDOException $e) {
        echo $e . PHP_EOL;
    }
  }
  exit(0);
}

function getAddressFromDirection($direction) {
    $link_array = explode('/',$direction);
    $latlng = end($link_array);
    $ch = curl_init();

    $headers  = array(
      "Accept: text/html, application/xhtml+xml, */*",
      "Accept-Language: en-US,en;q=0.8,zh-Hans-CN;q=0.5,zh-Hans;q=0.3"
    );

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_URL, "https://maps.googleapis.com/maps/api/geocode/json?latlng={$latlng}&key=AIzaSyB_6vjPIExLAZgxk4vLVjnN5b8yEMIv03s");
    curl_setopt($ch, CURLOPT_POST, false);
    curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate');
    curl_setopt($ch, CURLOPT_TIMEOUT, 5000);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4 );
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.3; WOW64; Trident/7.0; rv:11.0) like Gecko");
    $result = curl_exec($ch);
    curl_getinfo($ch);
    curl_close($ch);
    $resultArray = json_decode($result, true);
    foreach($resultArray['results'] as $res) {
        return $res['formatted_address'];
    }
    return null;
}

function addPokemon($request) {
  global $dbh;
  try {
    if ($dbh->inTransaction() === false) {
      $dbh->beginTransaction();
    }
    $stmt = $dbh->prepare("UPDATE raids2 SET pokemon=:pokemon, gymhuntr_boss=0 WHERE id=:id");
    $stmt->bindParam(":pokemon", $request['pokemon'], PDO::PARAM_STR);
    $stmt->bindParam(":id", $request['id'], PDO::PARAM_INT);
    $stmt->execute();
    $dbh->commit();
  }
  catch(PDOException $e) {
      echo $e . PHP_EOL;
  }
  exit(0);
}

function joinRaid($request) {
  global $dbh;
  try {
    if ($dbh->inTransaction() === false) {
      $dbh->beginTransaction();
    }
    $stmt = $dbh->prepare("INSERT IGNORE INTO users 
                             (raid_id, username) VALUES (:raid_id, :username)");
    $stmt->bindParam(":raid_id", $request['id'], PDO::PARAM_INT);
    $stmt->bindParam(":username", $request['username'], PDO::PARAM_STR);
    $stmt->execute();
    $dbh->commit();
  }
  catch(PDOException $e) {
      echo $e . PHP_EOL;
  }
}

function logCommand($request) {
  global $dbh;
  $result = false;
  try {
    if ($dbh->inTransaction() === false) {
      $dbh->beginTransaction();
    }
    $tz_object = new DateTimeZone('Europe/Amsterdam');
    $today = new DateTime();
    $today->setTimezone($tz_object);
    $times = explode(':', $request['msgTime']);
    $today->setTime(intval($times[0]), intval($times[1]));
    $command = $today->format('Y-m-d H:i:s') . $request['string'];

    $stmt = $dbh->prepare("SELECT id FROM command_log
                             WHERE command=:command");
    $stmt->bindParam(":command", $command, PDO::PARAM_STR);
    $stmt->execute();

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      return $result;
    }
    $stmt = $dbh->prepare("INSERT INTO command_log 
                             (command) VALUES (:command)");
    $stmt->bindParam(":command", $command, PDO::PARAM_STR);
    $stmt->execute();
    
    $result = $dbh->commit();
  }
  catch(PDOException $e) {
    echo $e . PHP_EOL;
  }
  return $result;
}

function stats($request) {
  global $dbh;
  $result = false;
  try {
    if ($dbh->inTransaction() === false) {
      $dbh->beginTransaction();
    }

    $stmt = $dbh->prepare("SELECT COUNT(*) raid_count, username FROM willim_pokemongo.users WHERE username=:username GROUP BY username ORDER BY raid_count DESC");
    $stmt->bindParam(":username", $request['username'], PDO::PARAM_STR);
    $stmt->execute();

    $results = [];
    $results['user'] = [];
    $results['user']['raid_count'] = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      $results['user']['username'] = $row['username'];
      $results['user']['raid_count'] = $row['raid_count'];
    }
    $result = $dbh->commit();
    return json_encode($results);
  }
  catch(PDOException $e) {
    echo $e . PHP_EOL;
  }
}

echo "<!doctype html>
  <html>
    <head>
      <meta charset='utf-8' />
      <meta http-equiv='refresh' content='60'>
      <title>Pokemon Go Amsterdam raids</title>
      <meta name='viewport' content='initial-scale=1, maximum-scale=1, minimum-scale=1, width=device-width, user-scalable=no' />
      <style>
        html {
          font-family: tahoma, arial;
        }
        .raids {
          display: flex;
          flex-wrap: wrap;
          align-items: center;
          justify-content: center;
        }
        .raid {
          flex: 1 1 auto;
          border: 3px solid yellow;
          border-radius: 5px;
          padding: .5rem;
          box-sizing: border-box;
          margin: .1rem
        }
        .countdownStart {
          display: inline;
        }
        .countdownStart:contains('Raid opened!') {
          color: green;
        }
        .countdownEnd {
          display: inline;
          float: right;
        }
        .countdownEnd:contains('Raid ended!') {
          color: red;
        }
        .lvl1 {
          border-color: green;
        }
        .lvl2 {
          border-color: yellow;
        }
        .lvl3 {
          border-color: orange;
        }
        .lvl4 {
          border-color: red;
        }
        .lvl5 {
          border-color: purple;
        }
        
        input[type='text'] {
          padding: 0.5rem 1rem;
          border-radius: 5px;
          border-color: lightgray;
          margin-bottom: .5rem;
          box-sizing: border-box;
        }
        
        #username {
          min-width: 400px;
        }
        h1 {
          margin: .5rem 0;
          font-size: 1rem;
        } 
        h2 {
          margin: .5rem 0;
          font-size: .8rem;
        }
        .boss {
          color: green;
        }
        .address {
            font-size: .7rem;
        }
        h3 {
          margin: .5rem 0;
        } 
        time { 
          display: block; 
          font-size: .8rem;
          margin: 0 0 5.rem 0;
        }
        fieldset {
          margin: .5rem 0;
        }
        .team-logo {
          width: 48px;
          height: 48px;
          display: inline-block;
          float: right;
        }
        .team-Mystic {
          color: blue;
          background: url(img/mystic48.png) no-repeat center center;
        }
        .team-Instinct {
          color: yellow;
          background: url(img/instinct48.png) no-repeat center center;
        }
        .team-Valor {
          color: red;
          background: url(img/valor48.png) no-repeat center center;
        }
      </style>
    </head>
    <body>
      <header>
        <a href='{$file}' title='{$city}'>{$city}</a>
        <a href='purmerend.php' title='Purmerend'>Purmerend</a>
      </header>
      <h1>Pokemon Go Amsterdam raids</h1>
      <label>Username: <input type='text' name='username' placeholder='Type your Pokemon Go name' value='' id='username' autocomplete='off' /></label>

      <div class='raids'>
";

$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$stmt = $dbh->prepare("SELECT * FROM raids2 r
                              WHERE address LIKE :city AND `end` > DATE_ADD(NOW(), INTERVAL 2 HOUR)
                              ORDER BY r.end DESC");
$cityParam = "%{$city}%";
$stmt->bindParam(":city", $cityParam, PDO::PARAM_STR);
$stmt->execute();
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
  $htmlPokemon = str_replace("'", "&#39;", $row['pokemon']);
  $htmlPokemon = ($htmlPokemon && strcasecmp($htmlPokemon, 'null') != 0) ? $htmlPokemon : "??";
  echo "<form class='raid lvl{$row['lvl']}' method='POST' action='{$file}'>";
  
  echo "
  <time class='countdownStart'>...</time>
  <time class='countdownEnd'>...</time>
  <h2>[{$row['lvl']}] 
    <a href='{$row['direction']}' target='_blank'>{$row['gym']}</a> <span class='team-logo team-{$row['team']}'></span>
  </h2>
  <h3>Boss: <span class='boss'>{$htmlPokemon}</span></h3>
  ";
  if($row['gymhuntr_boss'] == 0) {
  echo "<select class='select-pokemon' data-raid-id='{$row['id']}'>
    <option value=''>Set boss</option>
    <option value='Lapras'>Lapras</option>
    <option value='Blastoise'>Blastoise</option>
    <option value='Snorlax'>Snorlax</option>
    <option value='Venasaur'>Venasaur</option>
    <option value='Charizard'>Charizard</option>
    <option value='Rhydon'>Rhydon</option>
    <option value='Tyranitar'>Tyranitar</option>
    </select>";
  }
  echo "<div class='address'>{$row['address']}</div>";
  echo "<time class='startTime' datetime='{$row['start']}'>Start: {$row['start']}</time>";
  echo "<time class='endTime' datetime='{$row['end']}'>end: {$row['end']}</time>";
  $stmt2 = $dbh->prepare("SELECT * FROM users u
                              WHERE raid_id=:raid_id
                              ORDER BY u.added ASC");
  $stmt2->bindParam(":raid_id", $row['id'], PDO::PARAM_STR);
  $stmt2->execute();
  $first = true;
  while ($row2 = $stmt2->fetch(PDO::FETCH_ASSOC)) {
    if($first) {
      $first = false;
      echo "<fieldset><legend>Raiders:</legend>";
    }
    echo "<div class='user'>{$row2['username']}</div>";
  }
  if(!$first) {
    echo "</fieldset>";
  }
  echo "<input type='submit' name='join' value='Join raid' />";
  echo "<input type='hidden' name='id' value='{$row['id']}' />";
  echo "<input type='hidden' name='fn' value='joinRaid' />";
  echo "<input type='hidden' name='username' class='username' value='' />";
  echo "</form>";
}

echo "
  <script>
    var pokemonsEl = document.querySelectorAll('.select-pokemon');
    for(let idx in pokemonsEl) {
      if(pokemonsEl.hasOwnProperty(idx)) {
        pokemonsEl[idx].addEventListener('change', function() {
          this.parentElement.querySelector('.bossname').innerHTML = this.value;
          var formData = new FormData();
          formData.append('fn', 'addPokemon');
          formData.append('pokemon', this.value);
          formData.append('id', this.getAttribute('data-raid-id'));
          fetch('{$file}', {
            method: 'POST',
            body: formData
          });

        });
      }
    };
    
    var usernameEl = document.getElementById('username');
    usernameEl.addEventListener('keyup', function() {
      var unNodeList = document.querySelectorAll('.username');
      for(let idx in unNodeList) {
        if(unNodeList.hasOwnProperty(idx)) {
          var unEl = unNodeList[idx];
          unEl.value = usernameEl.value;
          localStorage['username'] = usernameEl.value;
        }
      }
    });
    setDefaultName();

    function setDefaultName() {
      var username = localStorage['username'];
      if(username && username.length > 0) {
        usernameEl.value = username;
        triggerEvent(usernameEl, 'keyup');
      }
    }

    function triggerEvent(el, type){
      if ('createEvent' in document) {
        // modern browsers, IE9+
        var e = document.createEvent('HTMLEvents');
        e.initEvent(type, false, true);
        el.dispatchEvent(e);
      } else {
        // IE 8
        var e = document.createEventObject();
        e.eventType = type;
        el.fireEvent('on'+e.eventType, e);
      }
    }
    
    var _second = 1000;
    var _minute = _second * 60;
    var _hour = _minute * 60;
    var _day = _hour * 24;

    function showRemaining(end, el) {
      var now = new Date();
      var distance = end - now;
      if (distance < 0) {
        el.innerHTML = endMsg ? endMsg : 'EXPIRED!';
        return;
      }
      var days = Math.floor(distance / _day);
      var hours = Math.floor((distance % _day) / _hour);
      var minutes = Math.floor((distance % _hour) / _minute);
      var seconds = Math.floor((distance % _minute) / _second);

      el.innerHTML = hours + 'h ' + minutes + 'm ' + seconds + 's';
    }
    
    setInterval(function() {
      var raids = document.querySelectorAll('.raid');
      for(var idx in raids) {
        if(raids.hasOwnProperty(idx)) {
          var raid = raids[idx];
          var closingTime = new Date(raid.querySelector('time.endTime').getAttribute('datetime'));
          showRemaining(closingTime, raid.querySelector('time.countdownEnd'), 'Raid ended!');
          var openTime = new Date(raid.querySelector('time.startTime').getAttribute('datetime'));
          showRemaining(openTime, raid.querySelector('time.countdownStart'), 'Raid opened!');
        }
      }
    }, 2000);
  </script>
";
echo "</div></body></html>";
