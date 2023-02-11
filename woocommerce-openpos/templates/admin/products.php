<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
?>
<div class="wrap">
    <div id="wrap-loading">
        <div class="lds-ellipsis"><div></div><div></div><div></div><div></div></div>
    </div>
    <h1 class="wp-heading-inline"><?php echo __( 'POS Products', 'openpos' ); ?></h1>
    <form id="op-product-list"  onsubmit="return false;">
        <input type="hidden" name="action" value="admin_openpos_update_product_grid">
        <table id="grid-selection" class="table table-condensed table-hover table-striped op-product-grid">
            <thead>
            <tr>
                <th data-column-id="id" data-identifier="true" data-type="numeric"><?php echo __( 'ID', 'openpos' ); ?></th>
                <th data-column-id="barcode" data-identifier="true" data-type="numeric"><?php echo __( 'Barcode', 'openpos' ); ?></th>
                <th data-column-id="product_thumb" data-sortable="false"><?php echo __( 'Thumbnail', 'openpos' ); ?></th>
                <th data-column-id="post_title" data-sortable="false"><?php echo __( 'Product Name', 'openpos' ); ?></th>
                <th data-column-id="formatted_price" data-sortable="false"><?php echo __( 'Price', 'openpos' ); ?></th>
                <th data-column-id="action"  data-sortable="false"><?php echo __( 'Action', 'openpos' ); ?></th>
            </tr>
            </thead>
        </table>
    </form>
    <br class="clear">
</div>


<script type="text/javascript">
     
     var op_selected_products = new Array();
    (function($) {
        "use strict";
       var grid = $("#grid-selection").bootgrid({
            ajax: true,
            post: function ()
            {
                /* To accumulate custom parameter with the request object */
                return {
                    action: "op_products"
                };
            },
            url: "<?php echo admin_url( 'admin-ajax.php' ); ?>",
            selection: true,
            rowCount: [<?php echo implode(',',apply_filters('op_product_row_count',array(10, 25, 50))); ?>, -1],
            multiSelect: true,
            formatters: {
                "link": function(column, row)
                {
                    return "<a href=\"#\">" + column.id + ": " + row.id + "</a>";
                },
                "price": function(column,row){

                    return row.formatted_price;
                }
            },
            templates: {
                header: "<div id=\"{{ctx.id}}\" class=\"{{css.header}}\"><div class=\"row\"><div class=\"col-sm-12 actionBar\"><span class=\"bar-select-counter\">0</span><p class=\"{{css.search}}\"></p><p class=\"{{css.actions}}\"></p><button type=\"button\" class=\"btn vna-action btn-default\" data-action=\"save\"><span class=\" icon glyphicon glyphicon-floppy-save\"></span></button><button type=\"button\" class=\"btn vna-action btn-default\" data-action=\"print\"><span class=\" icon glyphicon glyphicon-barcode\"></span></button><?php echo apply_filters('op_barcode_grid_buttons',''); ?></div></div></div>"
            },
            labels: {
                    all: "<?php echo __( 'All', 'openpos' ); ?>",
                    infos: "<?php echo __( 'Showing {{ctx.start}} to {{ctx.end}} of {{ctx.total}} entries', 'openpos' ); ?>",
                    loading: "<?php echo __( 'Loading...', 'openpos' ); ?>",
                    noResults: "<?php echo __( 'No results found!', 'openpos' ); ?>",
                    refresh: "<?php echo __( 'Refresh', 'openpos' ); ?>",
                    search: "<?php echo __( 'Search', 'openpos' ); ?>"
                }
        }).on("initialized.rs.jquery.bootgrid",function(){

            console.log('initialized');

        }).on("loaded.rs.jquery.bootgrid",function(e, ){
            var selected = $("#grid-selection").find('input[type="checkbox"]');
            if(op_selected_products.length > 0)
            {
               
                var selected_checkbox = [];
                for(var i =0; i< selected.length; i++)
                {
                    var row_id = $(selected[i]).val();
                    if(row_id != 'all')
                    {
                        row_id = 1 * row_id;
                    }
                    
                    if(op_selected_products.indexOf(row_id) > -1)
                    {
                        selected_checkbox.push(row_id);
                    }
                }
                $("#grid-selection").bootgrid("select", selected_checkbox);
                
            }
            $('.bar-select-counter').html(op_selected_products.length);
           
        }).on("selected.rs.jquery.bootgrid", function(e, rows)
        {
            
            var rowIds = [];
            for (var i = 0; i < rows.length; i++)
            {
                var row_id = rows[i].id;
                rowIds.push(row_id);
                if($('input[name="barcode['+rows[i].id+']"]'))
                {
                    $('input[name="barcode['+rows[i].id+']"]').prop('disabled',false);
                }
                if($('input[name="qty['+rows[i].id+']"]'))
                {
                    $('input[name="qty['+rows[i].id+']"]').prop('disabled',false);
                }
                if(op_selected_products.indexOf(row_id) < 0)
                {
                    op_selected_products.push(row_id);
                }
                $('.bar-select-counter').html(op_selected_products.length);
            }

        }).on("deselected.rs.jquery.bootgrid", function(e, rows)
        {
            var rowIds = [];
            for (var i = 0; i < rows.length; i++)
            {
                var row_id = rows[i].id;
                rowIds.push(row_id);
                if($('input[name="barcode['+rows[i].id+']"]'))
                {
                    $('input[name="barcode['+rows[i].id+']"]').prop('disabled',true);
                }
                if($('input[name="qty['+rows[i].id+']"]'))
                {
                    $('input[name="qty['+rows[i].id+']"]').prop('disabled',true);
                }
                let index = op_selected_products.indexOf(row_id);
                if (index > -1 ) {
                    op_selected_products.splice(index, 1);
                }
               
                $('.bar-select-counter').html(op_selected_products.length);
            }
            
        });

        

        $('.vna-action').click(function(){
            var selected = $("#grid-selection").find('input[type="checkbox"]:checked');
            var action = $(this).data('action');
            
            if(op_selected_products.length == 0)
            {
                alert('<?php echo __( 'Please choose row to continue.', 'openpos' ); ?>');
            }else{
                if(action == 'print')
                {
                   var rows = op_selected_products;
                   
                   var url = "<?php echo admin_url( 'admin-ajax.php?action=print_barcode&is_print=1&product_id=' ); ?>"+rows.join(',');
                   window.open(url);
                }else {
                    $.ajax({
                        url: openpos_admin.ajax_url,
                        type: 'post',
                        dataType: 'json',
                        data: {action: 'admin_openpos_update_product_grid',data:$('form#op-product-list').serialize()},
                        beforeSend:function(){
                            $('body').addClass('op_loading');
                        },
                        success:function(data){
                           
                            $('body').removeClass('op_loading');
                        }
                    })
                }


            }

        });
    })( jQuery );
</script>

<style>
    .action-row a{
        display: block;
        padding: 3px 4px;
        text-decoration: none;
        border: solid 1px #ccc;
        text-align: center;
        margin: 5px;
    }
    .op-product-grid td{
        vertical-align: middle!important;
    }
    .bootgrid-header .actionBar {
        position: relative;
    }
    .bar-select-counter {
        color: #fff;
        font-weight: bold;
        position: absolute;
        right: 3px;
        top: -15px;
        background: red;
        padding: 1px 5px;
        font-size: 10px;
        border-radius: 5px;
    }
</style>
<?php do_action('op_admin_barcode_grid_after'); ?>