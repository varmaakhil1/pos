<?php
    global $op_in_customer_screen;
    $op_in_customer_screen = true;
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
        # $base_dir = 'ENTER_YOUR_WORDPRESS_BASE_PATH'; // enter your custom wordpress base dir - the  and uncomment  
        </pre>
        and replace with your new wordpress patch + uncomment (remove "#"). And try again!
        <pre>
        $base_dir = 'ENTER_YOUR_WORDPRESS_BASE_PATH'; // enter your custom wordpress base dir and uncomment  
        </pre>
        <?php
        
        exit;
    }
    require_once ($wordpress_load);
    global $op_table;
    $key = isset($_REQUEST['key']) ? esc_attr($_REQUEST['key']) : '';
    $message = __('Please wait, your request in processing.....','openpos');
    $lang = array(
      'label_item' => __('Item','openpos'),
      'label_qty' => __('Qty','openpos'),
      'label_total' => __('Total','openpos'),
      'msg_table_empty' => __('Table is empty!','openpos'),
      'msg_table_confirm' => __('Please contact to waiter if those items do not exist in your table.','openpos'),
    );
?>


<head>
    <meta charset="utf-8">
    <title><?php echo __('Verification','openpos'); ?></title>
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- <link rel="icon" type="image/x-icon" href="favicon.ico"> -->
    <script>
        var data_url = '<?php echo admin_url('admin-ajax.php'); ?>';
        var verify_key = '<?php echo $key; ?>';
        var data_template= <?php echo json_encode(array('lang' => $lang,'template' => $op_table->verify_template()));?>;
    </script>
    <?php
    $handes = array(
        'openpos.customer.style'
    );
    wp_print_styles($handes);
    ?>
</head>
<body>
<main>
  <h1 class="visually-hidden"><?php echo __('Verify data','openpos'); ?></h1>

  <div class="px-4 py-5 my-5 text-center">
    <!-- <img class="d-block mx-auto mb-4" src="/docs/5.0/assets/brand/bootstrap-logo.svg" alt="" width="72" height="57"> -->
    <h1 class="display-5 fw-bold"><?php echo __('Verify data','openpos'); ?></h1>
    <div class="col-lg-6 mx-auto">
      <p class="lead mb-4" id="op-message"><?php echo $message; ?></p>
      <div class="d-grid gap-2 d-sm-flex justify-content-sm-center" >
        <a style="display:none;" id="op-customer-confirm-btn"  href="javascript:void(0);" class="btn btn-primary btn-lg px-4 gap-3"><?php echo __('Confirmed','openpos'); ?></a>
      </div>
    </div>
  </div>
</main>


<?php
$handes = array(
    'openpos.customer.script'
);
wp_print_scripts($handes);
?>

</body>
</html>

