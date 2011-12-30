jQuery('document').ready(function($) {
    //customize the options here to pass the necessary data to the server, defaults based on wp Twenty Eleven
    $('#commentform').binnoCommentMentionsSubmit();
});

(function( $ ){
  $.fn.binnoCommentMentionsSubmit = function(options, callback) {
                var mentionsResponseData = false;
                var settings = $.extend( {
                    'author': '#author',
                    'email' : '#email',
                    'url'   : '#url',
                    'comment': '#comment'                   
                  }, options);          
    $(settings.comment).mentionsInput({ 
            onDataRequest:function (mode, query, callback) {
              if (mentionsResponseData)
              {
                filteredData = _.filter(mentionsResponseData, function(item) { return item.name.toLowerCase().indexOf(query.toLowerCase()) > -1 });
                callback.call(this, filteredData);
                return;
              }
              var data = { security : binnoMentionsParams.binnoMentionsNonce, action : 'mentions_get_users_as_json'};
              $.post(binnoMentionsParams.ajaxurl, data, function(response) {
                console.log('got users from server');
                mentionsResponseData = $.parseJSON(response);
                filteredData = _.filter(mentionsResponseData, function(item) { return item.name.toLowerCase().indexOf(query.toLowerCase()) > -1 });
                callback.call(this, filteredData);
              });
            }
        
          });
    $(this).live('submit', function(evt) {
                evt.preventDefault();
                var mentions = '';
                $(settings.comment).mentionsInput('getMentions', function(data) {
                    $(data).each(function(i, v) {
                            mentions += '&mentions[]=' + v.id;
                        })
                    });
                if ($(this).find(settings.email).length && $(this).find(settings.author).length)
                {
                    if ($(this).find(settings.email).val() == '' || $(this).find(settings.author).val() == '')
                    {
                        alert('Both name and email are required');
                        return;
                    }
                    var pattern = new RegExp(/^(("[\w-+\s]+")|([\w-+]+(?:\.[\w-+]+)*)|("[\w-+\s]+")([\w-+]+(?:\.[\w-+]+)*))(@((?:[\w-+]+\.)*\w[\w-+]{0,66})\.([a-z]{2,6}(?:\.[a-z]{2})?)$)|(@\[?((25[0-5]\.|2[0-4][0-9]\.|1[0-9]{2}\.|[0-9]{1,2}\.))((25[0-5]|2[0-4][0-9]|1[0-9]{2}|[0-9]{1,2})\.){2}(25[0-5]|2[0-4][0-9]|1[0-9]{2}|[0-9]{1,2})\]?$)/i);
		    if (!pattern.test($(this).find(settings.email).val())) { alert('Please enter a valid email address.'); return;}
			
                }
                var data = {
                    'action' : 'binno_comment_mentions',
                    'security': binnoMentionsParams.binnoMentionsNonce,
                    'author': $(this).find(settings.author).val(),
                    'email' : $(this).find(settings.email).val(),
                    'url'   : $(this).find(settings.url).val()
                }
                if (!binnoMentionsParams.loggedIn)
                {
                    $.cookie('comment_author_' + binnoMentionsParams.cookieHash, data.author, {expires : 30000000, path: binnoMentionsParams.cookiePath});
                    $.cookie('comment_author_email_' + binnoMentionsParams.cookieHash, data.email, {expires : 30000000, path: binnoMentionsParams.cookiePath});
                    $.cookie('comment_author_url_' + binnoMentionsParams.cookieHash, data.url, {expires : 30000000, path: binnoMentionsParams.cookiePath});
                }
                var result = $.post(binnoMentionsParams.ajaxurl, 'action='+data.action+'&security='+data.security+'&'+$(this).serialize()+mentions, function(response){
                  if (typeof(callback) == 'function') callback(response);
                  else window.location.reload();
                  });
                return result;
            });
        };
})( jQuery );