(function($) {

    var last_data_str = '';
    /* Get into full screen */
    function GoInFullscreen(element) {
        screenfull.request();
    }

    /* Get out of full screen */
    function GoOutFullscreen() {
        screenfull.request();
    }

    /* Is currently in full screen or not */
    function IsFullScreenCurrently() {
        if (screenfull.enabled) {
            return false;
        }
        // If no element is in full-screen

        return true;
    }

    function getDataInit(callback){
        var time_data_url = data_url + '?t='+ Date.now();
        $.ajax({
            url : time_data_url,
            type: 'get',
            dataType: 'json',
            success: function(response){
                
                response.lang = lang_obj;
                
                var template = ejs.compile(data_template['template'], {});
                var html = template(response);
                if(last_data_str.length == 0 || last_data_str != html)
                {
                    last_data_str = html;
                   
                    $('#bill-content').html(html);
                }
                
                callback();
            },
            error: function(){
                callback();
            }
        });
    }
    function getData(){
        getDataInit(function(){
            setTimeout(function() {
                getData();
            }, bill_frequency_time);
        });
    }
    $(document).ready(function(){
        var noSleep = new NoSleep();
        getData();

        $(document).on('click','#go-button', function() {

            if ($('body').hasClass('on-full')) {
                $('body').removeClass('on-full');
                noSleep.disable();
                screenfull.exit();
            } else {

                $('body').addClass('on-full');
                noSleep.enable();
                screenfull.request();
            }
        });

    });



}(jQuery));