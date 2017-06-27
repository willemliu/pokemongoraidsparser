var lvlRegex = /Raid\sLevel:\s(\d)/gim;
var startRegex = /Raid\sstart:\s(.{18})/gim;
var endRegex = /Raid\send:\s(.{18})/gim;
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
    var that = this;
    setTimeout(function() { parseData(that); }, timeout);
    timeout += 4000;
  });

}

function parseData(el) {
  jQuery(el).closest('.leaflet-marker-icon').click();
  setTimeout(function() {
    var gym = jQuery(".sweet-alert > h2")
        .clone()    //clone the element
        .children() //select all the children
        .remove()   //remove all the children
        .end()  //again go back to selected element
        .text();
    var txt = jQuery('.sweet-alert > p').clone()    //clone the element
        .children() //select all the children
        .remove()   //remove all the children
        .end()  //again go back to selected element
        .text();
    console.debug(txt);
    var lvl = lvlRegex.exec(txt);
    lvl = (lvl && lvl.length === 2)?lvl[1]:'4';
    var start = startRegex.exec(txt);
    start = (start && start.length === 2)?start[1]:'';
    var end = endRegex.exec(txt);
    end = (end && end.length === 2)?end[1]:'';
    var boss = bossRegex.exec(txt);
    boss = (boss && boss.length === 2) ? boss[1] : '';
    var direction = jQuery('.sweet-alert > p > .popupfoot > a.button:first-child').attr('href');
    addLvl4Raid(gym, lvl, start, end, boss, direction);
    setTimeout(function() {jQuery('.sweet-alert .modal-close').click();}, 1000);
  }, 2000);
}

function addLvl4Raid(gym, lvl, start, end, boss, direction) {
  console.debug(gym, lvl, start, end, boss, direction);
  var formData = new FormData();
  formData.append('fn', 'addRaidData');
  formData.append('gym', gym);
  formData.append('lvl', lvl);
  formData.append('start', start);
  formData.append('end', end);
  formData.append('boss', boss);
  formData.append('direction', direction);
  fetch('https://pogo.moviesom.com/index.php', {
    method: 'POST',
    body: formData
  });
}
