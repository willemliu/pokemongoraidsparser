var lvlRegex = /Raid\sLevel:\s(\d)/gim;
var startRegex = /Raid.start:.(\d{4})-(\d{1,2})-(\d{1,2}).(\d{1,2}):(\d{1,2}):(\d{1,2})/gim;
var startRegex2 = /Raid.start:.(\d{1,2})-(\d{1,2})-(\d{4}).(\d{1,2}):(\d{1,2}):(\d{1,2})/gim;
var endRegex = /Raid.end:.(\d{4})-(\d{1,2})-(\d{1,2}).(\d{1,2}):(\d{1,2}):(\d{1,2})/gim;
var endRegex2 = /Raid.end:.(\d{1,2})-(\d{1,2})-(\d{4}).(\d{1,2}):(\d{1,2}):(\d{1,2})/gim;
var bossRegex = /Raid\sboss:\s(.+)\sCP:/gim;

chrome.storage.sync.get({
  enable: true,
}, function(items) {
  console.log('Pokemon Go raid parser enabled', items.enable);
  if(items.enable) {
    setInterval(function() {
      start();    
    }, 60000);
  }
  setTimeout(function() {
    start();    
  }, 10000);
});

function start() {
  console.debug('Parsing');
  jQuery('.dots:not(:contains(....))').closest('.leaflet-marker-icon').hide();
  jQuery('.dots:contains(....)').closest('.leaflet-marker-icon').show();
  
  var timeout = 0;
  jQuery('.dots:contains(....),.dots:contains(.....)').each(function() {
    setTimeout(jQuery.proxy(parseData, this), timeout);
    timeout += 4000;
  });

}

function parseData() {
  jQuery(this).closest('.leaflet-marker-icon').click();
  setTimeout(function() {
    var gym = jQuery(".sweet-alert > h2")
        .clone()    //clone the element
        .children() //select all the children
        .remove()   //remove all the children
        .end()  //again go back to selected element
        .text();
    var txt = jQuery('.sweet-alert > p').html();
    console.debug(txt);
    var lvl = lvlRegex.exec(txt);
    lvl = (lvl && lvl.length === 2)?lvl[1]:'4';
    var start = startRegex.exec(txt);
    if(start == null || start == undefined) {
      start = startRegex2.exec(txt);
      console.log("Reparse start:", txt, start);
      if(start) {
        start = [
          start[0],
          start[3] + "-" + start[2] + "-" + start[1] + " " + start[4] + ":" + start[5] + ":" + start[6]
        ];
      }
    } else {
      start = [
        start[0],
        start[1] + "-" + start[2] + "-" + start[3] + " " + start[4] + ":" + start[5] + ":" + start[6]
      ];
    }
    console.log("START:", start);
    start = (start && start.length === 2)?start[1]:'';
    console.log("START2:", start);
    var end = endRegex.exec(txt);
    if(end == null || end == undefined) {
      end = endRegex2.exec(txt);
      console.log("Reparse end:", txt, end);
      if(end) {
        end = [
          end[0],
          end[3] + "-" + end[2] + "-" + end[1] + " " + end[4] + ":" + end[5] + ":" + end[6]
        ];
      }
    } else {
      end = [
        end[0],
        end[1] + "-" + end[2] + "-" + end[3] + " " + end[4] + ":" + end[5] + ":" + end[6]
      ];
    }
    console.log("END:", end);
    end = (end && end.length === 2)?end[1]:'';
    console.log("END2:", end);
    var boss = bossRegex.exec(txt);
    boss = (boss && boss.length === 2) ? boss[1] : '';
    var direction = jQuery('.sweet-alert > p > .popupfoot > a.button:first-child').attr('href');
    var team = jQuery('.sweet-alert > p > .gym_team').text();
    addLvl4Raid(gym, lvl, start, end, boss, direction, team);
    jQuery('.sweet-alert, .sweet-overlay').remove();
  }, 2000);
}

function addLvl4Raid(gym, lvl, start, end, boss, direction, team) {
  console.debug(gym, lvl, start, end, boss, direction, team);
  var formData = new FormData();
  formData.append('fn', 'addRaidData');
  formData.append('gym', gym);
  formData.append('lvl', lvl);
  formData.append('start', start);
  formData.append('end', end);
  formData.append('boss', boss);
  formData.append('direction', direction);
  formData.append('team', team);
  fetch('https://pogo.moviesom.com/index.php', {
    method: 'POST',
    body: formData
  });
}
