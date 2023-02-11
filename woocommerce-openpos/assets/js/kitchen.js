(function ($) {
    $.extend({
        playSound: function () {
            return $(
                '<audio class="sound-player" autoplay="autoplay" style="display:none;">'
                + '<source src="' + arguments[0] + '" />'
                + '<embed src="' + arguments[0] + '" hidden="true" autostart="true" loop="false"/>'
                + '</audio>'
            ).appendTo('body');
        },
        stopSound: function () {
            $(".sound-player").remove();
        }
    });
})(jQuery);

(function($) {
    var total_item = 0;
    var view_type = 'items';
    let client_time_offset = new Date().getTimezoneOffset();
    let last_html_str = '';
    function getDataInit(callback){
        var time_data_url = data_url + '?t='+ Date.now();
        
        if($('body').hasClass('processing'))
        {
            callback();
        }else {
            
            $.ajax({
                url : time_data_url,
                type: 'get',
                dataType: 'json',
                //data: $('#kitchen-form').serialize()+'&action=get_data&client_time_offset='+client_time_offset,
                beforeSend:function(){
                    $('body').addClass('processing');
                    
                },
                success: function(response){
                    //$('#kitchen-table-body').empty();
                    
                    var list_html = '';    
                    var _index = 1;
                    let selected_view_type = $('input[name="display"]').val();
                    let selected_area = $('select[name="type"]').val();

                    
                   
                    let data_response = [];
                    if(selected_view_type == 'items' && response['items'])
                    {
                        data_response = response['items'][selected_area];
                    }
                    if(selected_view_type == 'orders' && response['orders'])
                    {
                        data_response = response['orders'][selected_area];
                    }
                    
                    for(var i in data_response)
                    {
                        
                        var template = ejs.compile(data_template['template'], {});
                        var row_data = data_response[i];

                        let order_time = row_data['order_timestamp'];
                        row_data['time_ago'] = $.timeago(order_time);

                        row_data['index'] = _index;
                        var in_process = readied_items.indexOf(row_data['id']);
                        
                        if(in_process >= 0)
                        {
                            row_data['done'] = 'ready';
                        }
                        var html = template(row_data);
                        list_html += html;
                        _index++;
                    }
                    if(_index > total_item)
                    {
                        $('body').trigger('new-dish-come');
                    }
                    total_item = _index;
                    if(last_html_str == '' || last_html_str != list_html)
                    {
                        $('#kitchen-table-body').html(list_html);
                        last_html_str = list_html;
                    }
                    
                    
                    $('body').removeClass('processing');
                    callback();
                },
                error: function(){
                    $('body').removeClass('processing');
                    callback();
                }
            });
        }

    }
    function getData(){
        getDataInit(function(){

            setTimeout(function() {
                getData();
            }, kitchen_frequency_time);

        });
    }

    $(document).ready(function(){

        $('select[name="type"]').on('change',function(){
            //window.location.href = $(this).val();
            $('form#kitchen-form').submit();
        });

        getData();

        $(document).on('click','.item-action-click',function(){
            var current = $(this);
            var ready_id = $(this).data('id');
            var ready_action = $(this).data('action');

            var time_data_url = kitchen_action_url + '?t='+ Date.now();
            let client_time_offset = new Date().getTimezoneOffset();
            $.ajax({
                url : time_data_url,
                type: 'post',
                dataType: 'json',
                data: {action: 'custom_action',custom_action: ready_action,id: ready_id, type: kitchen_type,client_time_offset: client_time_offset},
                beforeSend:function(){
                    $('body').addClass('processing');
                    current.hide();
                },
                success: function(response){
                    $('body').removeClass('processing');
                    if(ready_action != 'delete')
                    {
                        current.show();
                    }
                },
                error: function(){
                    $('body').removeClass('processing');
                    if(ready_action != 'delete')
                    {
                        current.show();
                    }
                }
            });

        })

        $(document).on('click','.is_cook_ready',function(){
            var current = $(this);
            var ready_id = $(this).data('id');
            var time_data_url = kitchen_action_url + '?t='+ Date.now();
            let client_time_offset = new Date().getTimezoneOffset();
            $.ajax({
                url : time_data_url,
                type: 'post',
                dataType: 'json',
                data: {action: 'update_ready',id: ready_id, type: kitchen_type,client_time_offset: client_time_offset},
                beforeSend:function(){
                    $('body').addClass('processing');
                    current.hide();
                },
                success: function(response){
                    $('body').removeClass('processing');
                    readied_items.push(ready_id);

                },
                error: function(){
                    $('body').removeClass('processing');
                }
            });
        });

        $(document).on('click','#refresh-kitchen',function(){
            if(confirm('Flush all abandoned data. Are you sure ?')){
                var time_data_url = kitchen_action_url + '?t='+ Date.now();
                $.ajax({
                    url : time_data_url,
                    type: 'post',
                    dataType: 'json',
                    data: {action: 'clear_data',warehouse: data_warehouse_id,type: kitchen_type},
                    beforeSend:function(){
                        $('body').addClass('processing');
                    },
                    success: function(response){
                        $('body').removeClass('processing');
                    },
                    error: function(){
                        $('body').removeClass('processing');
                    }
                });
            }
            
        });
        $(document).on('click','.grid-view',function(){
            view_type = $(this).data('id');
            $('input[name="display"]').val(view_type);
            $('form#kitchen-form').submit();

        //    $('.grid-view-control .grid-view').removeClass('selected');
        //    $(this).addClass('selected');
           
        //    $('.bill-content-container').hide();
        //    $('#bill-content-'+view_type).show();
        });

    });



}(jQuery));