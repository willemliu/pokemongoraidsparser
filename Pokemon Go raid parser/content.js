const raidExp = /^(\/raid),\s*(\d),\s*(\d\d):*(\d\d),\s*(.*)/gim;
const raidExp2 = /^(\/raid),\s*(\d),\s*(\d\d):*(\d\d),\s*(.*),\s*(.*)/gim;
const statsExp = /^(\/stats),(.*)$/gim;

chrome.storage.sync.get({
  enable: true,
}, function(items) {
  console.log('Pokemon Go raid parser enabled', items.enable);
  if(items.enable) {
    setInterval(function() {
      let messages = document.querySelectorAll('.msg');
      console.debug('Parsing', messages.length);
      for(let idx in messages) {
        if(messages.hasOwnProperty(idx)) {
          let message = messages[idx];
          let msgText = message.querySelector('.selectable-text');
          if(msgText) {
            const txt = getTextContentExceptScript(msgText);
            let time = '';
            if(msgText.parentNode) {
              time = (msgText.parentNode && msgText.parentNode.parentNode && msgText.parentNode.parentNode.querySelector('.message-datetime')) ? msgText.parentNode.parentNode.querySelector('.message-datetime').innerHTML: '';
            }
            let matches = [];
            if(txt.match(raidExp2)) {
              matches = raidExp2.exec(txt);
            } else {
              matches = raidExp.exec(txt);
            }
            if(matches && matches.length >= 6) {
              addRaid(time, matches);
            }
            matches = statsExp.exec(txt);
            if(matches && matches.length > 0) {
              console.debug('Stats');
              stats(time, matches);
            }
          }
        }
      }
    }, 10000);
  }
});


function addRaid(time, matches) {
  console.log(time, matches[0]);
  var d = new Date();
  let currentTime = parseInt(d.getHours()-2 + '' + d.getMinutes());
  let raidTime = parseInt(matches[3] + '' + matches[4]);
  if(raidTime > currentTime) {
    let formData = new FormData();
    formData.append('fn', 'addRaid');
    formData.append('msgTime', time);
    formData.append('string', matches[0]);
    formData.append('command', matches[1]);
    formData.append('lvl', matches[2]);
    formData.append('hours', matches[3]);
    formData.append('minutes', matches[4]);
    formData.append('location', matches[5]);
    if(matches.length > 6) {
      formData.append('gym', matches[6]);
    }
    fetch('https://pogo.moviesom.com/index.php', {
      method: 'POST',
      body: formData
    });
  }
}

function stats(time, matches) {
  let formData = new FormData();
  formData.append('fn', 'stats');
  formData.append('msgTime', time);
  formData.append('string', matches[0]);
  formData.append('command', matches[1]);
  formData.append('username', matches[2]);
  fetch('https://pogo.moviesom.com/index.php', {
    method: 'POST',
    body: formData
  })
  .then((resp) => resp.json())
  .then((data) => {
    console.log(data);
    if(data.user) {
      console.log(data.user.username, data.user.raid_count);
    }
  });
}

function getTextContentExceptScript(element) {
  var text= [];
  for (var i= 0, n= element.childNodes.length; i<n; i++) {
    var child= element.childNodes[i];
    if (child.nodeType===1)
      text.push(getTextContentExceptScript(child));
    else if (child.nodeType===3)
      text.push(child.data);
  }
  return text.join(' ');
}