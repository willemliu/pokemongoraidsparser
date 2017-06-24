<?php
/**
 * Turn on all error reporting.
 */
error_reporting(E_ALL ^ (E_STRICT | E_DEPRECATED | E_NOTICE));
ini_set('display_errors', 1);
include_once ('./lib/DB.php');

$db = new DB();
$dbh = $db->connect();

$method = $_SERVER['REQUEST_METHOD'];
switch ($method) {
  case 'POST':
    switch ($_REQUEST['fn']) {
      case 'joinRaid':
        joinRaid($_REQUEST);
        break;
      case 'addRaid':
        if(logCommand($_REQUEST)) {
          addRaid($_REQUEST);
        } else {
          echo 'Raid already added. Ignoring command.';
        }
        break;
      case 'addPokemon':
        addPokemon($_REQUEST);
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

function addRaid($request) {
  global $dbh;
  try {
    if ($dbh->inTransaction() === false) {
      $dbh->beginTransaction();
    }
    $stmt = $dbh->prepare("INSERT INTO raids
                             (lvl, datetime, location, gym) VALUES (:lvl, :datetime, :location, :gym) 
                             ON DUPLICATE KEY UPDATE gym=:gym");
    $tz_object = new DateTimeZone('Europe/Amsterdam');
    $today = new DateTime();
    $today->setTimezone($tz_object);
    $today->setTime($request['hours'], $request['minutes']);
    $stmt->bindParam(":lvl", $request['lvl'], PDO::PARAM_INT);
    $stmt->bindParam(":datetime", $today->format('Y-m-d H:i:s'), PDO::PARAM_STR);
    $stmt->bindParam(":location", $request['location'], PDO::PARAM_STR);
    $stmt->bindParam(":gym", $request['gym'], PDO::PARAM_STR);
    $stmt->execute();
    $dbh->commit();
  }
  catch(PDOException $e) {
      echo $e . PHP_EOL;
  }
  exit(0);
}

function addPokemon($request) {
  global $dbh;
  try {
    if ($dbh->inTransaction() === false) {
      $dbh->beginTransaction();
    }
    $stmt = $dbh->prepare("UPDATE raids SET pokemon=:pokemon WHERE id=:id");
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
  try {
    if ($dbh->inTransaction() === false) {
      $dbh->beginTransaction();
    }
    $tz_object = new DateTimeZone('Europe/Amsterdam');
    $today = new DateTime();
    $today->setTimezone($tz_object);
    $times = explode(':', $request['time']);
    $today->setTime(intval($times[0]), intval($times[1]));

    $stmt = $dbh->prepare("INSERT INTO command_log 
                             (command) VALUES (:command)");
    $command = $today->format('Y-m-d H:i:s') . $request['string'];
    $stmt->bindParam(":command", $command, PDO::PARAM_STR);
    $stmt->execute();
    return $dbh->commit();
  }
  catch(PDOException $e) {
    exit(1);
  }
}

echo "<!doctype html>
  <html>
    <head>
      <meta charset='utf-8' />
      <title>Pokemon Go Team Instinct Amsterdam raids</title>
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
          padding: 1rem;
          box-sizing: border-box;
          margin: .5rem
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
          margin-bottom: 1rem;
        }
        
        input[name='pokemonBossName'] {
          width: 100%;
          box-sizing: border-box;
        }
        
        #username {
          min-width: 400px;
        } 
        time { 
          display: block; 
          font-size: .8rem;
          margin: 0 0 1rem 0;
        }
        fieldset {
          margin: 1rem 0;
        }
      </style>
    </head>
    <body>
      <h1>Pokemon Go Team Instinct Amsterdam raids</h1>
      <p>Usage in Whatsapp Raid group: /raid,&lt;lvl&gt;,&lt;time&gt;,&lt;location&gt;[,gym]<br/>
      Example: /raid,2,1430,Amstelstation<br>
      Example2: /raid,2,1430,Jacob Bontiusplaats 1,Roest</p>
      <label>Username: <input type='text' name='username' placeholder='Type your Pokemon Go name' value='' id='username' /></label>

      <div class='raids'>
";

$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$stmt = $dbh->prepare("SELECT * FROM raids r
                              WHERE datetime > DATE_ADD(NOW(), INTERVAL 1 HOUR)
                              ORDER BY r.datetime DESC");
$stmt->execute();
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
  $htmlLocation = str_replace("'", "&#39;", $row['location']);
  $htmlPokemon = str_replace("'", "&#39;", $row['pokemon']);
  echo "<form class='raid lvl{$row['lvl']}' method='POST' action='/'>";
  
  echo "
  <time class='countdown'></time>
  <h2>[{$row['lvl']}] 
    <a href='https://maps.google.com/?q={$htmlLocation}' target='_blank'>{$row['location']}</a>
  </h2>
  <input type='text' name='pokemonBossName' data-raid-id='{$row['id']}' placeholder='Pokemon raid boss name' value='{$htmlPokemon}' />
  ";
  if(isset($row['gym']) && strlen($row['gym']) > 0) {
    echo "<div class='gym'>Gym: {$row['gym']}</div>";
  }
  echo "<time datetime='{$row['datetime']}'>{$row['datetime']}</time>";
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
    var pokemonsEl = document.querySelectorAll('[name=\'pokemonBossName\']');
    for(let idx in pokemonsEl) {
      if(pokemonsEl.hasOwnProperty(idx)) {
        pokemonsEl[idx].addEventListener('change', function() {
          var formData = new FormData();
          formData.append('fn', 'addPokemon');
          formData.append('pokemon', this.value);
          formData.append('id', this.getAttribute('data-raid-id'));
          fetch('index.php', {
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
    var timer;

    function showRemaining(end, el) {
      var now = new Date();
      var distance = end - now;
      if (distance < 0) {
        clearInterval(timer);
        document.getElementById('countdown').innerHTML = 'EXPIRED!';
        return;
      }
      var days = Math.floor(distance / _day);
      var hours = Math.floor((distance % _day) / _hour);
      var minutes = Math.floor((distance % _hour) / _minute);
      var seconds = Math.floor((distance % _minute) / _second);

      el.innerHTML = days + 'd ' + hours + 'h ' + minutes + 'm ' + seconds + 's';
    }
    
    setInterval(function() {
      var raids = document.querySelectorAll('.raid');
      for(var idx in raids) {
        if(raids.hasOwnProperty(idx)) {
          var raid = raids[idx];
          var dateTime = new Date(raid.querySelector('time[datetime]'));
          showRemaining(dateTime, raid.querySelector('time.countdown'));
        }
      }
    }, 2000);
  </script>
";
echo "</div></body></html>";
