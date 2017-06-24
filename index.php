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
        addRaid($_REQUEST);
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
        
        #username {
          padding: 0.5rem 1rem;
          border-radius: 5px;
          border-color: lightgray;
          min-width: 400px;
          margin-bottom: 1rem;
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
  $htmlLocation = $row['location'];
  $htmlLocation = str_replace("'", "&#39;", $row['location']);
  echo "<form class='raid lvl{$row['lvl']}' method='POST' action='/'>";
  echo "<h2><time datetime='{$row['datetime']}'>{$row['datetime']}</time> - 
  lvl: {$row['lvl']} - 
  <a href='https://maps.google.com/?q={$htmlLocation}' target='_blank'>{$row['location']}</a> -
  Gym: {$row['gym']}
  </h2>";
  $stmt2 = $dbh->prepare("SELECT * FROM users u
                              WHERE raid_id=:raid_id
                              ORDER BY u.added ASC");
  $stmt2->bindParam(":raid_id", $row['id'], PDO::PARAM_STR);
  $stmt2->execute();
  while ($row2 = $stmt2->fetch(PDO::FETCH_ASSOC)) {
    echo "<div class='user'>{$row2['username']}</div>";
  }
  echo "<input type='submit' name='join' value='Join raid' />";
  echo "<input type='hidden' name='id' value='{$row['id']}' />";
  echo "<input type='hidden' name='fn' value='joinRaid' />";
  echo "<input type='hidden' name='username' class='username' value='' />";
  echo "</form>";
}

echo "
  <script>
    var usernameEl = document.getElementById('username');
    usernameEl.addEventListener('keyup', function() {
      var unNodeList = document.querySelectorAll('.username');
      for(let idx in unNodeList) {
        if(unNodeList.hasOwnProperty(idx)) {
          var unEl = unNodeList[idx];
          unEl.value = usernameEl.value;
          localStorage['username'] = usernameEl.value;
          console.debug(usernameEl.value);
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
  </script>
";
echo "</div></body></html>";
