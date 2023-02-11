<?php
/**
 * Created by PhpStorm.
 * User: anhvnit
 * Date: 10/21/18
 * Time: 12:05
 */
require_once 'protect.php';

global $op_in_kitchen_screen;
$op_in_kitchen_screen = true;
$base_dir = dirname(dirname(dirname(dirname(__DIR__))));

if(!file_exists($base_dir.'/wp-load.php'))
{
    $sampe_paths = array(
        dirname(__DIR__),
        '/opt/bitnami/apps/wordpress/htdocs',
        '/opt/bitnami/wordpress'
    );
    foreach($sampe_paths as $s)
    {
        if(file_exists($s.'/wp-load.php')){
            $base_dir = $s;
        }
    }
}   
/** UPDATE YOUR CUSTOM WORDPRESS DIR AT HERE */

# $base_dir = 'ENTER_YOUR_WORDPRESS_BASE_PATH'; // enter your custom wordpress base dir and uncomment    

/** END */

$wordpress_load = $base_dir.'/wp-load.php';
if(!file_exists($wordpress_load))
{
    ?>
    <h2>No wordpress base dir found. </h2>
    <p>Please goto <b><?php echo __FILE__ ; ?></b> , find the line</p>
    <pre>
    # $base_dir = 'ENTER_YOUR_WORDPRESS_BASE_PATH'; // enter your custom wordpress base dir and uncomment  
    </pre>
    and replace with your new wordpress patch + uncomment (remove "#"). And try again!
    <pre>
    $base_dir = 'ENTER_YOUR_WORDPRESS_BASE_PATH'; // enter your custom wordpress base dir and uncomment  
    </pre>
    <?php
    
    exit;
}
require_once ($base_dir.'/wp-load.php');
global $op_table;
global $op_woo;
if(!defined('OPENPOS_DIR'))
{
    echo __('Please intall and active openpos');
    exit;
}

$id = isset($_GET['id']) ? esc_attr($_GET['id']) : 0;
$grid_type = isset($_GET['display']) ? esc_attr($_GET['display']) : 'items';
$kitchen_url =  OPENPOS_URL.'/kitchen/'; 
#$kitchen_url =  'https://your_domain.com/kitchen/'; // uncomment change this to your custom url


if(isset($_REQUEST['action']) && $_REQUEST['action'] == 'clear_data')
{
    $id = isset($_REQUEST['warehouse']) ? intval($_REQUEST['warehouse']) : 0;
    $op_table->clear_takeaway($id);
    exit;
}
if(isset($_REQUEST['action']) && $_REQUEST['action'] == 'update_ready')
{
    $id_strs = isset($_REQUEST['id']) ? explode(',',$_REQUEST['id']) : array();
    foreach($id_strs as $id_str)
    {
        $tmp = explode('-',$id_str);
        if(count($tmp) >= 2)
        {
            $table_id = $tmp[1]; //end($tmp);
            $item_id = $tmp[0];
            $table_type = isset($tmp[2]) ? $tmp[2]: 'dine_in';
            $table_data = $op_table->bill_screen_data($table_id,$table_type);
            $ver = $table_data['ver'];
            $online_ver = $table_data['online_ver'];
            if($online_ver > $ver)
            {
                $ver = $online_ver;
            }
            $table_data['ver'] = $ver + 10;
            $table_data['online_ver'] = $ver + 20;
            $items = array();
            foreach($table_data['items'] as $item)
            {
                if($item['id'] == $item_id)
                {
                    $item['done'] = 'ready';
                }
                $items[] = $item;
            }
            $table_data['items'] = $items;
            $op_table->update_table_bill_screen($table_id,$table_data,$table_type);

        }
    }
    //$id_str = isset($_REQUEST['id']) ? $_REQUEST['id'] : '';
    do_action( 'openpos_kitchen_update_ready_after',$id_strs, $op_table  );
    echo json_encode(array());exit;
}
if(isset($_REQUEST['action']) && $_REQUEST['action'] == 'custom_action')
{
    $custom_action = isset($_REQUEST['custom_action']) ? $_REQUEST['custom_action'] : '';
    $data = array();
    if($custom_action)
    {
        $data = $op_table->kitchen_custom_action($custom_action);
    }
    
    echo json_encode($data);exit;
}


if(isset($_REQUEST['action']) && $_REQUEST['action'] == 'get_data')
{
    $warehouse_id = isset($_REQUEST['id']) ? $_REQUEST['id'] : -1;
    $view_type = isset($_REQUEST['type']) ? $_REQUEST['type'] : 'all';
    $display = isset($_REQUEST['display']) ? $_REQUEST['display'] : 'items';
    $result = array();
    $result_formated = array();
    $total = 0;
    if($warehouse_id >= 0)
    {
        $off_tables = $op_table->tables((int)$warehouse_id);
        $takeaway_tables = $op_table->takeawayTables((int)$warehouse_id);
        $tables = array_merge($off_tables,$takeaway_tables);

        foreach($tables as $table)
        {

            $table_type = isset($table['dine_type'])? $table['dine_type'] :'dine_in';
           
            $table_data = $op_table->bill_screen_data($table['id'],$table_type);
            
            
            if(isset($table_data['parent']) && $table_data['parent'] == 0 && isset($table_data['items'])  && count($table_data['items']) > 0)
            {
                $items = $table_data['items'];
                $formatted_items = array();
                $is_full_serverd = true;
                $last_order_timestamp = 0;
                
                foreach($items as $key => $item)
                {
                    $id = 1 * $item['id'];

                    if($id > $last_order_timestamp)
                    {
                        $last_order_timestamp = $id;
                    }

                    if($view_type != 'all')
                    {
                        $product_id = isset($item['product_id']) ? $item['product_id'] : 0;
                        if(!$product_id)
                        {
                            continue;
                        }
                        if(!$op_woo->check_product_kitchen_op_type($view_type,$product_id)){
                            continue;
                        }
                    }

                    if(isset($item['done']) && ($item['done'] == 'done' || $item['done'] == 'done_all'))
                    {
                        if($display == 'items')
                        {
                            continue;
                        }
                        
                    }else{
                        $is_full_serverd = false;
                    }

                    
                    $timestamp = (int)($item['id'] / 1000);
                    if(isset($item['order_time']) && $item['order_time'] > 100)
                    {
                        $timestamp = (int)($item['order_time'] / 1000);
                    }

                    $order_timestamp = $timestamp  * 1000;

                    $timestamp += wc_timezone_offset();

                    $order_time = '--:--';
                    if($timestamp)
                    {
                        $order_time = date('d-m-y  h:i',$timestamp);
                    }
                    $dish_id = $id.'-'.$table['id'];
                    if($table_type && $table_type != 'dine_in')
                    {
                        $dish_id.= '-'.$table_type;
                    }
                    $item_note = $item['sub_name'];
                    if(isset($table_data['note']) && $table_data['note'])
                    {
                        $item_note .= ' '.$table_data['note'];
                    }
                    
                    $tmp = array(
                        'id' => $dish_id,
                        'local_id' => $id ,
                        'priority' => 1,
                        'item' => $item['name'],
                        'seller_name' => $item['seller_name'] ? $item['seller_name'] : '',
                        'qty' => $item['qty'],
                        'table' => $table['name'],
                        'order_time' => $order_time,
                        'order_timestamp' => $order_timestamp,
                        'note' => $item_note,
                        'dining' => isset($item['dining']) ? $item['dining'] : '',
                        'done' => isset($item['done']) ? $item['done'] : '',
                        'allow_action' => array()
                    );
                    $dish_data = apply_filters('op_kitchen_dish_item_data',$tmp,$table_data,$item);
                    if($dish_data && !empty($dish_data) )
                    {
                        if($display != 'orders')
                        {
                            $result[$id] =  $dish_data;
                        }else{
                            $formatted_items[] = $dish_data;
                        }
                        
                        $total++;
                    }
                    
                }
                if($display == 'orders' && !empty($formatted_items) && !$is_full_serverd)
                {
                    $table_data['items'] = $formatted_items;
                    $table_data['allow_action'] = array();
                    $table_data['dining'] = '';
                    $table_data['order_timestamp'] = isset($table_data['created_at_time']) && $table_data['created_at_time'] > 100 ? $table_data['created_at_time'] : $last_order_timestamp;
                    if($last_order_timestamp)
                    {
                        if(isset($result[$last_order_timestamp]))
                        {
                            $last_order_timestamp = $last_order_timestamp + rand(1,10);
                        }
                        $result[$last_order_timestamp] = apply_filters('op_kitchen_dish_table_data',$table_data);
                    }else{
                        $result[] = apply_filters('op_kitchen_dish_table_data',$table_data);
                    }
                    
                }
            }
        }


    }
    
    if(!empty($result))
    {
      
      if($display == 'orders')
      {
        $keys = array_keys($result);
        sort($keys);
        //$result_formated = $result;
        $i = 1;
       
        foreach($keys as  $r)
        {
            $result_formated[] = $result[$r];
        }
      }else{
        $i = 1;
        $keys = array_keys($result);
        $min_key = min($keys);
        
        foreach($result as  $r)
        {
        	$key = 1*$r['order_timestamp'] - $min_key;
            if(isset($r['local_id']) && $r['local_id'])
            {
            	$key = 1*$r['local_id'] - $min_key;
            }
            
            $r['priority'] = round($i / $total,2) * 100;
            $result_formated[$key] = $r;

            $i++;
        }
        $result_formated = $result_formated;
      }
       
    }
    echo json_encode($result_formated);exit;

}


$kitchen_type = isset($_REQUEST['type']) ? esc_attr($_REQUEST['type']) : 'all';
$all_area = $op_woo->getListRestaurantArea();


$protected_password = apply_filters('op_kitchen_protected_password',false);
if($protected_password && strlen($protected_password) > 4)
{
        Protect\with('form.php', $protected_password); 
}

$temlate_file = apply_filters('kitchen_template_location',OPENPOS_DIR.'templates/kitchen/view.php');

?>
<html lang="en" style="height: calc(100% - 0px);">
<head>
    <meta charset="utf-8">
    <title><?php echo __( 'Kitchen Screen', 'openpos' ); ?></title>
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script>
        var  kitchen_type = '<?php echo $kitchen_type; ?>';
        var data_url = '<?php echo $op_table->kitchen_data_url($id); //echo $kitchen_url.'index.php' ?>';
        var kitchen_action_url = '<?php echo $kitchen_url.'index.php' ?>';
        var readied_items = new Array();
        var on_hover_update = false;
        
        var data_warehouse_id = '<?php echo $id; ?>';
        var current_local_db_version = 0;
        var kitchen_frequency_time = 3000;

        var data_template= <?php echo json_encode(array('template' => $op_table->kitchen_view_template($grid_type)));?>;
    </script>
    <?php
    $handes = array(
        'openpos.kitchen.style'
    );
    wp_print_styles($handes);
    ?>

</head>
<body class="body-<?php echo $grid_type; ?>">
<?php require_once($temlate_file); ?>

<?php
$handes = array(
    'openpos.kitchen.script'
);

wp_print_scripts(apply_filters('openpos_kitchen_footer_js',$handes));
?>

<button id="button-notification" style="display: none;"  type="button"></button>

<script type="text/javascript">

    (function($) {

        $(document).ready(function(){
            $('#button-notification').on('click',function(){
                $.playSound("<?php echo OPENPOS_URL.'/assets/sound/helium.mp3' ?>");
            });
            $('body').on('new-dish-come',function(){
                $('#button-notification').trigger('click');
            })

        });
    }(jQuery));

</script>

<style  type="text/css">
  
</style>
</body>
</html>
