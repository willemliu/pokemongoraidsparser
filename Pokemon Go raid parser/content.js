const regexp = /^(\/raid),(\d),(\d\d):*(\d\d),(.*),(.*)$/gim;

chrome.storage.sync.get({
  enable: true,
}, function(items) {
  console.log('Pokemon Go raid parser enabled', items.enable);
  if(items.enable) {
    setInterval(function() {
      var d = new Date();
      let messages = document.querySelectorAll('.msg');
      for(let idx in messages) {
        if(messages.hasOwnProperty(idx)) {
          let message = messages[idx];
          let msgText = message.querySelector('.selectable-text');
          if(msgText) {
            const txt = getTextContentExceptScript(msgText);
            let matches = regexp.exec(txt);
            if(matches && matches.length >== 6) {
              console.log(matches[0]);
              let currentTime = parseInt(d.getHours()-2 + '' + d.getMinutes());
              let raidTime = parseInt(matches[3] + '' + matches[4]);
              if(raidTime > currentTime) {
                let formData = new FormData();
                formData.append('fn', 'addRaid');
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
          }
        }
      }
    }, 10000);
  }
});


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