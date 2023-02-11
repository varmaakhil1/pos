<?php
/**
 * Created by PhpStorm.
 * User: anhvnit
 * Date: 10/21/18
 * Time: 12:05
 */
require_once 'protect.php';
global $op_in_bill_screen;
$op_in_bill_screen = true;
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
require_once ($wordpress_load);

global $op_register;
$id = esc_attr($_GET['id']);
$register = $op_register->get((int)$id);

#Protect\with('form.php', 'my_password'); //uncomment if you want protect your kitchen screen
?>
<?php if(!empty($register)):  ?>
<html lang="en" style="height: calc(100% - 0px);">
<head>
    <meta charset="utf-8">
    <title><?php echo __( 'Bill Screen', 'openpos' ); ?> - <?php echo $register['name']; ?></title>
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <script>
        var data_url = '<?php echo $op_register->bill_screen_file_url($register['id']); ?>';
        var data_template= <?php echo json_encode(array('template' => $op_register->bill_template()));?>;
        
        var lang_obj = {
            'label_cashier': '<?php echo __('Cashier','openpos'); ?>',
            'label_products': '<?php echo __('Products','openpos'); ?>',
            'label_product': '<?php echo __('Product','openpos'); ?>',
            'label_price': '<?php echo __('Price','openpos'); ?>',
            'label_qty': '<?php echo __('Qty','openpos'); ?>',
            'label_total': '<?php echo __('Total','openpos'); ?>',
            'label_grand_total': '<?php echo __('Grand Total','openpos'); ?>'
        };
        var bill_frequency_time = 1000;
    </script>
    <?php
    $handes = array(
        'openpos.bill.style'
    );
    wp_print_styles($handes);
    ?>

</head>
<body>
<div  id="bill-content"></div>

<?php
$handes = array(
    'openpos.bill.script'
);
wp_print_scripts($handes);
?>

</body>
</html>
<?php else: ?>
    <h1> <?php echo __('Opppos !!!!','openpos'); ?></h1>
<?php endif; ?>


