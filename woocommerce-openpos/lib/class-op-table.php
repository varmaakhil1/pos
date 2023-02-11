<?php
if(!class_exists('OP_Table'))
{
    class OP_Table{
        public $_post_type = '_op_table';
        public $_warehouse_meta_key = '_op_warehouse';
        public $_position_meta_key = '_op_table_position';
        public $_type_meta_key = '_op_table_type';
        public $_cost_meta_key = '_op_table_cost';
        public $_cost_type_meta_key = '_op_table_cost_type';
        public $_seat_meta_key = '_op_table_seat';
        public $_filesystem;
        public $_bill_data_path;
        public $_kitchen_data_path;
        public $_bill_data_path_ready;
        public $_bill_data_path_completed;
        public $_bill_data_path_accepted;
        public $_bill_data_path_deleted;
        public $_base_path;
        public function __construct()
        {
            if(!class_exists('WP_Filesystem_Direct'))
            {
                require_once(ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php');
                require_once(ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php');
            }
            $upload_dir   = wp_upload_dir();
            $this->_filesystem = new WP_Filesystem_Direct(false);
            $this->_base_path =  $upload_dir['basedir'].'/openpos';
            $this->_bill_data_path =  $this->_base_path.'/tables';
            $this->_kitchen_data_path =  $this->_base_path.'/kitchen';

            $this->_bill_data_path_ready =  $this->_base_path.'/ready';
            $this->_bill_data_path_completed =  $this->_base_path.'/completed';
            $this->_bill_data_path_accepted =  $this->_base_path.'/accepted';
            $this->_bill_data_path_deleted =  $this->_base_path.'/deleted';
            add_action( 'init', array($this, 'wp_init') );
            $this->init();
        }
        function wp_init(){
            register_post_type( '_op_table',
                    array(
                        'labels'              => array(
                            'name'                  => __( 'Table', 'openpos' ),
                            'singular_name'         => __( 'Table', 'openpos' )
                        ),
                        'description'         => __( 'This is where you can add new transaction that customers can use in your store.', 'openpos' ),
                        'public'              => false,
                        'show_ui'             => false,
                        'capability_type'     => 'op_report',
                        'map_meta_cap'        => true,
                        'publicly_queryable'  => false,
                        'exclude_from_search' => true,
                        'show_in_menu'        => false,
                        'hierarchical'        => false,
                        'rewrite'             => false,
                        'query_var'           => false,
                        'supports'            => array( 'title','author','content' ),
                        'show_in_nav_menus'   => false,
                        'show_in_admin_bar'   => false
                    )

            );
        }
        function init(){
            $chmod_dir = ( 0755 & ~ umask() );
            if (  defined( 'FS_CHMOD_DIR' ) ) {

                $chmod_dir = FS_CHMOD_DIR;
            }

            // create openpos data directory
            if(!file_exists($this->_base_path))
            {
                $this->_filesystem->mkdir($this->_base_path,$chmod_dir);
            }

            if(!file_exists($this->_bill_data_path))
            {
                $this->_filesystem->mkdir($this->_bill_data_path,$chmod_dir);
            }

            if(!file_exists($this->_bill_data_path_ready))
            {
                $this->_filesystem->mkdir($this->_bill_data_path_ready,$chmod_dir);
            }
            if(!file_exists($this->_bill_data_path_completed))
            {
                $this->_filesystem->mkdir($this->_bill_data_path_completed,$chmod_dir);
            }
            if(!file_exists($this->_bill_data_path_accepted))
            {
                $this->_filesystem->mkdir($this->_bill_data_path_accepted,$chmod_dir);
            }
            if(!file_exists($this->_kitchen_data_path))
            {
                $this->_filesystem->mkdir($this->_kitchen_data_path,$chmod_dir);
            }

            if(!file_exists($this->_bill_data_path_deleted))
            {
                $this->_filesystem->mkdir($this->_bill_data_path_deleted,$chmod_dir);
            }
            add_action('openpos_logout',array($this,'openpos_logout'),20,2);

        }
        function openpos_logout($session_id, $session_data ){
            $warehouse_id = isset($session_data['login_warehouse_id']) ? $session_data['login_warehouse_id'] : -1;
            if($warehouse_id >= 0)
            {
               
                $this->removed_deleted_markup($warehouse_id);
                
            }
        }
        public function get_file_mode(){
            $file_mode = apply_filters('op_file_mode',0755) ;
            return $file_mode;
        }
        public function _updateTableNoPos(){
            //upload all table with no position
            $posts = get_posts([
                'post_type' => $this->_post_type,
                'numberposts' => -1,
                'meta_query' => array(
                    array(
                        'key' => $this->_position_meta_key,
                        'compare' => 'NOT EXISTS' // this should work...
                    ),
                )
            ]);
            foreach ($posts as $post)
            {
                $post_id = $post->ID;
                update_post_meta($post_id,$this->_position_meta_key,0);
            }
        }
        public function tables($warehouse_id = -1,$is_front = false ){
            $result = array();
            if($warehouse_id >= 0)
            {
                $posts = get_posts([
                    'post_type' => $this->_post_type,
                    'post_status' => array('publish'),
                    'numberposts' => -1,
                    'order'     => 'ASC',
                    'meta_key' => $this->_position_meta_key,
                    'orderby'   => 'meta_value_num'
                ]);

                foreach($posts as $p)
                {
                    $tmp = $this->get($p->ID);
                    if($tmp['warehouse'] == $warehouse_id)
                    {
                        $result[] = $tmp;
                    }

                }
            }else{
                $posts = get_posts([
                    'post_type' => $this->_post_type,
                    'post_status' => array('publish','draft'),
                    'numberposts' => -1,
                    'order'     => 'ASC',
                    'meta_key' => $this->_position_meta_key,
                    'orderby'   => 'meta_value_num',
                ]);

                foreach($posts as $p)
                {
                    $result[] = $this->get($p->ID,$is_front);
                }
            }

            return $result;
        }
        public function takeawayJsonTables($warehouse_id = -1 ){
            $result = array();
            if ($handle = opendir( $this->_bill_data_path)) {

                while (false !== ($entry = readdir($handle))) {

                    if ($entry != "." && $entry != ".." && strpos($entry,'takeaway') == 0) {

                        if(strpos($entry,'.json') > 0)
                        {
                            $table_id = str_replace('.json','',$entry);
                            $file_path = $this->_bill_data_path.'/'.$entry;

                            $file_removing_path = $this->generate_removing_file_path($entry,$warehouse_id);
                            
                            if(file_exists($file_removing_path))
                            {
                                continue;
                            }

                            $data = $this->_filesystem->get_contents($file_path);
                           
                            if($data)
                            {
                                $result_table = json_decode($data,true);
                               

                                if(isset($result_table['online_ver']) )
                                {

                                    $result_table['online_ver'] = max($result_table['ver'],$result_table['online_ver']);
                                }
                                if(!isset($result_table['desk']))
                                {
                                    continue;
                                }
                                $desk = $result_table['desk'];

                                if(isset($desk['warehouse_id']))
                                {
                                    
                                    if(  $warehouse_id >= 0 && $desk['warehouse_id'] != $warehouse_id)
                                    {
                                        continue;
                                    }
                                    $result[] =  $result_table;
                                }


                            }
                        }
                    }
                }
                closedir($handle);
            }

            return $result;
        }
        public function takeawayTables($warehouse_id = -1 ){

            $result = array();
            if ($handle = opendir( $this->_bill_data_path)) {

                while (false !== ($entry = readdir($handle))) {

                    if ($entry != "." && $entry != ".." && strpos($entry,'takeaway') == 0) {

                        if(strpos($entry,'.json') > 0)
                        {
                            $table_id = str_replace('.json','',$entry);
                            $file_path = $this->_bill_data_path.'/'.$entry;

                            $file_removing_path = $this->generate_removing_file_path($entry,$warehouse_id);
                            if(file_exists($file_removing_path))
                            {
                                continue;
                            }


                            $data = $this->_filesystem->get_contents($file_path);
                            
                            if($data)
                            {
                                $result_table = json_decode($data,true);
                               
                                if(!isset($result_table['desk']))
                                {
                                    continue;
                                }
                                $desk = $result_table['desk'];
                                

                                if(isset($desk['warehouse_id']))
                                {
                                    if(  $warehouse_id >= 0 && $desk['warehouse_id'] != $warehouse_id)
                                    {
                                        continue;
                                    }
                                    $table_name = $desk['name'];
                                    if(isset($result_table['label']) && $result_table['label'] != null )
                                    {
                                        $table_name = $result_table['label'];
                                    }
                                    $result[] = array(
                                        'id' => $desk['id'],
                                        'name' => $table_name,
                                        'warehouse' => $desk['warehouse_id'],
                                        'position' => 0,
                                        'status' => 'publish',
                                        'dine_type' => 'takeaway',
                                    );
                                }


                            }
                        }
                    }
                }
                closedir($handle);
            }

            return $result;
          

        }
        public function delete($id){
            $post = get_post($id);
            if($post->post_type == $this->_post_type)
            {
                wp_trash_post( $id  );
            }

        }
        public function save($params){

            $id  = 0;
            if(isset($params['id']) && $params['id'] > 0)
            {
                $id = $params['id'];
            }
            $warehouse_id = isset($params['warehouse']) ? $params['warehouse'] : 0;
            $position = isset($params['position']) ? (int)$params['position'] : 0;
            $seat = isset($params['seat']) ? (int)$params['seat'] : 0;

            $type = isset($params['type']) ? $params['type'] : 'default';
            $cost = isset($params['cost']) ? $params['cost'] : 0;
            $cost_type = isset($params['cost_type']) ? $params['cost_type'] : 'hour';
            $args = array(
                'ID' => $id,
                'post_title' => $params['name'],
                'post_type' => $this->_post_type,
                'post_status' => $params['status'],
                'post_parent' => $warehouse_id
            );
            $post_id = wp_insert_post($args);
            if(!is_wp_error($post_id)){


                update_post_meta($post_id,$this->_warehouse_meta_key,$warehouse_id);
                update_post_meta($post_id,$this->_position_meta_key,$position);
                update_post_meta($post_id,$this->_seat_meta_key,$seat);

                update_post_meta($post_id,$this->_type_meta_key,$type);
                update_post_meta($post_id,$this->_cost_meta_key,$cost);
                update_post_meta($post_id,$this->_cost_type_meta_key,$cost_type);

                return $post_id;
            }else{
                //there was an error in the post insertion,
                throw new Exception($post_id->get_error_message()) ;
            }
        }
        public function get($id,$is_front = false)
        {
            $post = get_post($id);
            if(!$post)
            {
                return array();
            }
            if($post->post_type != $this->_post_type)
            {
                return array();
            }
            $name = $post->post_title;
            $warehouse = get_post_meta($id,$this->_warehouse_meta_key,true);
            $position = get_post_meta($id,$this->_position_meta_key,true);
            $seat = get_post_meta($id,$this->_seat_meta_key,true);
            
            $type = get_post_meta($id,$this->_type_meta_key,true);
            $cost_type = get_post_meta($id,$this->_cost_type_meta_key,true);
            $cost = get_post_meta($id,$this->_cost_meta_key,true);

            if(!$cost)
            {
                $cost = 0;
            }
            if(!$cost_type)
            {
                $cost_type = 'hour';
            }
            if(!$type)
            {
                $type = 'default';
            }

            $status = $post->post_status;
            $result = array(
                'id' => $id,
                'name' => $name,
                'warehouse' => $warehouse,
                'position' => (int)$position,
                'seat' => (int)$seat,
                'type' => $type,
                'cost' => $cost,
                'cost_type' => $cost_type,
                'status' => $status
            );
            if($is_front)
            {
                $min_cost = $result['cost'] ;
                switch($result['cost_type'])
                {
                    case 'day':
                        $min_cost = $min_cost / ( 60 * 24 );
                        break;
                    case 'hour':
                        $min_cost = $min_cost / ( 60  );
                        break;
                }
                $result['cost_type'] = 'minute';
                $result['cost'] = 1 * $min_cost;
                
            }
            return  apply_filters('op_table_details',$result,$is_front);;
        }

        public function update_bill_screen($tables_data,$is_purge = false){
            $result = array();
            if(!empty($tables_data))
            {
                $allow_update_kitchen = false;
                $outlet_id = -1;
                $server_time = time();
                foreach($tables_data as $table_key => $table_data)
                {
                    $table_type = isset($table_data['type']) ? $table_data['type'] : '';
                    
                    $table_id = str_replace('desk-','',$table_key);
                    if(strpos($table_id,'takeaway') === false && $table_type == 'guest_takeaway')
                    {
                        if(!isset($table_data['id']) || !$table_data['id'])
                        {
                            $table_data['id'] = $table_id;
                            $table_data['order_number'] = $table_id;
                            $table_data['source'] = 'desk_takeaway';
                            $table_data['source_type'] = 'desk_takeaway';
                            $table_data['type'] = 'takeaway';
                        }
                        $table_id = 'takeaway-'.$table_id;
                    }
                    $current_data = $this->bill_screen_data($table_id);
                    
                    
                    $desk = isset($table_data['desk']) ? $table_data['desk'] : array();
                    
                    if(!empty($desk))
                    {
                        $outlet_id = isset($desk['warehouse']) ? $desk['warehouse'] : -1;
                        if($outlet_id == -1){
                            $outlet_id = isset($desk['warehouse_id']) ? $desk['warehouse_id'] : 0;    
                         }
                    }
                    
                    
                    $allow_update = true;
                    $table_online_version = isset($table_data['system_ver']) ? $table_data['system_ver'] : 0;
                    $current_table_online_version = isset($current_data['system_ver']) ? $current_data['system_ver'] : 0;
                    
                    if($table_online_version < $current_table_online_version)
                    { 

                        throw new Exception(__('There are an other update of this table. Please refresh this table and try again.','openpos'));
                    }
                    
                    
                    
                    
                    if(isset($current_data['ver']) && isset($table_data['ver']))
                    {
                        if($current_data['ver'] >= $table_data['ver']  )
                        {
                            $allow_update = false;
                        }

                    }
                    
                    $_table_data = apply_filters('op_update_table_data',$table_data,$current_data);
                    $_allow_update = apply_filters('op_get_allow_update_table_data',$allow_update,$table_data,$current_data);
                    $result[$table_key] = $current_data;
                    if($_allow_update)
                    {
                       
                        $_table_data['system_ver'] = $server_time;

                        $allow_update_kitchen = true;
                        $register_file = $this->bill_screen_file_path($table_id);
                        if(file_exists($register_file))
                        {
                            $this->_filesystem->delete($register_file);
                        }
                        

                        $file_mode = $this->get_file_mode();
                        $this->_filesystem->put_contents(
                            $register_file,
                            json_encode($_table_data),
                            $file_mode // predefined mode settings for WP files
                        );
                        $result[$table_key] = $_table_data;
                    }
                    if($outlet_id  >= 0 && $allow_update_kitchen )
                    {
                       
                        $this->update_kitchen_data($outlet_id);
                    }

                }
            }
            return $result;
        }
        public function update_table_bill_screen($table_id,$table_data,$table_type = 'dine_in'){ //use for update message guest update from kitchen only
            if($table_type != 'dine_in')
            {
                $table_id = $table_type.'-'.$table_id;
            }
            $register_file = $this->bill_screen_file_path($table_id);
            $file_mode = $this->get_file_mode() ;
            
            if(file_exists($register_file))
            {
                if ( defined( 'FS_CHMOD_FILE' ) ) {
                    $this->_filesystem->put_contents(
                        $register_file,
                        json_encode($table_data)
                    );
                }else{
                    $this->_filesystem->put_contents(
                        $register_file,
                        json_encode($table_data),
                        $file_mode
                    );
                }
                
            }else{
                
                $this->_filesystem->put_contents(
                    $register_file,
                    json_encode($table_data),
                    $file_mode // predefined mode settings for WP files
                );
            }
            $desk = isset($table_data['desk']) ? $table_data['desk'] : array();
                    
            if(!empty($desk))
            {
                $outlet_id = isset($desk['warehouse']) ? $desk['warehouse'] : -1;

                if($outlet_id == -1){
                    $outlet_id = isset($desk['warehouse_id']) ? $desk['warehouse_id'] : 0;    
                 }

                $this->update_kitchen_data($outlet_id);
            }
            
        }
        public function bill_screen_file_path($table_id)
        {
            return $this->_bill_data_path.'/'.$table_id.'.json';
        }
        public function bill_screen_file_url($table_id)
        {
            $upload_dir = wp_upload_dir();
            $url = $upload_dir['baseurl'];
            $url = ltrim($url,'/');
            return $url.'/openpos/tables/'.$table_id.'.json';
        }
        public function bill_screen_data($table_id,$type='dine_in')
        {
            if($type != 'dine_in')
            {
                $table_id = $type.'-'.$table_id;
            }
            $file_path = $this->bill_screen_file_path($table_id);
            
            if(!file_exists($file_path))
            {
                $table_id = 'takeaway-'.$table_id;
                $guest_path = $this->bill_screen_file_path($table_id);
                $data = $this->_filesystem->get_contents($guest_path);
                $result = array();
                if($data)
                {
                    $guest_data = json_decode($data,true);
                    $desk = isset($guest_data['desk']) ? $guest_data['desk'] : array();
                    $desk_type = isset($desk['type']) ? $desk['type'] : '';
                    if($desk_type == 'guest_takeaway')
                    {
                        $result = $guest_data;
                    }
                }

                return $result;

            }else{
                $data = $this->_filesystem->get_contents($file_path);

                $result = array();
                if($data)
                {
                    $result = json_decode($data,true);
                }

                return $result;
            }
        }
        public function tables_version($warehouse_id = -1){
            $result = array();
            if ($handle = opendir( $this->_bill_data_path)) {

                while (false !== ($entry = readdir($handle))) {

                    if ($entry != "." && $entry != "..") {

                        if(strpos($entry,'.json') > 0)
                        {
                            $table_id = str_replace('.json','',$entry);
                            $file_path = $this->_bill_data_path.'/'.$entry;
                            $data = $this->_filesystem->get_contents($file_path);
                            if($data)
                            {
                                $result_table = json_decode($data,true);
                                if($warehouse_id >= 0)
                                {

                                    if( isset($result_table['desk']['warehouse_id']) && $result_table['desk']['warehouse_id'] != $warehouse_id ){
                                        continue;
                                    }
                                }
                               
                                $version = isset($result_table['ver']) ? $result_table['ver'] : 0;
                                $result[$table_id] = $version;
                            }
                        }
                    }
                }
                closedir($handle);
            }
            return $result;
        }
        public function ready_dishes($warehouse_id = -1){
            $result = array();
            if ($handle = opendir( $this->_bill_data_path)) {

                while (false !== ($entry = readdir($handle))) {

                    if ($entry != "." && $entry != "..") {
                        $table_type = 'dine_in';

                        if(strpos($entry,'takeaway') === 0)
                        {
                            $table_type = 'takeaway';
                        }
                        if(strpos($entry,'.json') > 0)
                        {
                            $table_id = str_replace('.json','',$entry);
                            $file_path = $this->_bill_data_path.'/'.$entry;
                            $data = $this->_filesystem->get_contents($file_path);
                            if($data)
                            {
                                $result_table = json_decode($data,true);
                                $desk = isset($result_table['desk']) ? $result_table['desk'] : array();
                                if(isset($desk['warehouse_id']) && $desk['warehouse_id'] != $warehouse_id && $warehouse_id >= 0)
                                {
                                    continue;
                                }
                                $items = isset($result_table['items']) ? $result_table['items'] : array();
                                if(!empty($items))
                                {
                                    $table = isset($result_table['desk']) ? $result_table['desk'] : [];
                                    $table_name = isset($table['name']) ? $table['name'] : '';
                                    $table_id = isset($table['id']) ? $table['id'] : 0;
                                    foreach ($items as $_item)
                                    {
                                        if(isset($_item['done']) && $_item['done'] == 'ready')
                                        {
                                            $item_dinning = isset($_item['dining']) ? $_item['dining'] : $table_type;
                                            if(!$item_dinning)
                                            {
                                                $item_dinning = 'dine_in';
                                            }
                                            $result[] = array(
                                                'id' => $_item['id'],
                                                'table_id' => $table_id,
                                                'table_name' => $table_name,
                                                'table_type' => $table_type,
                                                'item_dinning' => $item_dinning,
                                                'item_name' => $_item['qty'].' x '.$_item['name']
                                            );
                                        }
                                    }

                                }

                            }
                        }
                    }
                }
                closedir($handle);
            }
            return $result;
        }
        function generate_removing_file_path($file_name,$outlet_id = 0){
            $process_name = 'removing_';
            $process_name .= $file_name;
            if($outlet_id < 0)
            {
                $outlet_id = 0;
            }
            $chmod_dir = $this->get_file_mode();
            if(!file_exists($this->_bill_data_path_deleted.'/'.$outlet_id))
            {
                $this->_filesystem->mkdir($this->_bill_data_path_deleted.'/'.$outlet_id,$chmod_dir);
            }
            return $this->_bill_data_path_deleted.'/'.$outlet_id.'/'.$process_name;
        }
        public function removeJsonTable($table_id,$force = false){

            $file_path = $this->bill_screen_file_path($table_id);
           
            if($force)
            {
                $file_path = $this->_bill_data_path.'/takeaway-'.$table_id.'.json';
            }
            if(file_exists($file_path))
            {
                $table_data = $this->bill_screen_data($table_id);
                
                $desk = isset($table_data['desk']) ? $table_data['desk'] : array();
                $outlet_id = -1;
                if(!empty($desk))
                {
                    $outlet_id = isset($desk['warehouse']) ? $desk['warehouse'] : -1;
                    if($outlet_id == -1){
                        $outlet_id = isset($desk['warehouse_id']) ? $desk['warehouse_id'] : 0;    
                     }
                }

                do_action('op_remove_json_table_before',$table_data,$outlet_id);

                //create dump file
                $process_name = basename($file_path);
                $process_path = $this->generate_removing_file_path($process_name,$outlet_id);
                
                
                // $file_mode = $this->get_file_mode();
                // $this->_filesystem->put_contents(
                //     $process_path,
                //     '',
                //     $file_mode // predefined mode settings for WP files
                // );
                // end dump file
                
                rename($file_path, $process_path);
                if($outlet_id >= 0)
                {
                    $this->update_kitchen_data($outlet_id);
                }

            }
            
            
        }
        public function clear_takeaway($warehouse_id = -1 ){
            $result = array();
            
           
            if($warehouse_id >= 0)
            {
                $tables = $this->tables($warehouse_id);
                
                $exist_tables = array();
                foreach($tables as $t)
                {
                    $exist_tables[] = $t['id'];
                }
                if ($handle = opendir( $this->_bill_data_path)) {
    
                    while (false !== ($entry = readdir($handle))) {
    
                        if ($entry != "." && $entry != "..") {
    
                            if(strpos($entry,'.json') > 0)
                            {
                                $is_delete = false;
                                $file_path = $this->_bill_data_path.'/'.$entry;
                                $process_path = $this->generate_removing_file_path($entry,$warehouse_id);

                                
                                $data = $this->_filesystem->get_contents($file_path);
                                if($data)
                                {
                                    $result_table = json_decode($data,true);
                                    
                                    
                                    if( isset($result_table['desk']['warehouse_id']) && $result_table['desk']['warehouse_id'] != $warehouse_id ){
                                        continue;
                                    }
                                }

                                if(strpos($entry,'takeaway') !== false)
                                {
                                    $is_delete = true;
                                }else{
                                    if( ! isset($result_table['desk']['id']) && !in_array($result_table['desk']['id'],$exist_tables) ){
                                        $is_delete = true; // deleted abandone desk
                                    }
                                }
                                if($is_delete)
                                {
                                    unlink($file_path);
                                }
                            }
                        }
                    }
                    closedir($handle);
                }
                
                $this->update_kitchen_data($warehouse_id);
            }
            $this->removed_deleted_markup($warehouse_id);
            return $result;
        }
        public function getTableByKey($key){
            $key_meta = '_op_barcode_key';
            $register_meta = '_op_barcode_register';
            $args = array(
                'meta_query'        => array(
                    array(
                        'key'       => $key_meta,
                        'value'     => $key
                    )
                ),
                'post_type'         => $this->_post_type,
                'posts_per_page'    => '1',
                'post_status' => 'any'
            );
           
            // run query ##
            $posts = get_posts( $args );
            
            
            $result = null;
            foreach($posts as $p)
            {
                $result = $this->get($p->ID);

            }
            if($result != null)
            {
                $result['register_id'] = get_post_meta($result['id'],$register_meta,true);
            }
            
            return $result;
            
        }
        
        public function verify_template(){
            $file_name = 'table.txt';
            $file_path = OPENPOS_DIR.'/default/'.$file_name;
            if($this->_filesystem->is_file($file_path))
            {
                return $this->_filesystem->get_contents($file_path);
            }else{
                return '';
            }
        }
        public function kitchen_view_template($display = 'items')
        {
            $file_name = 'kitchen_view_';
            $file_name.= $display;
            $file_name.= '.txt';
            $file_path = apply_filters('kitchen_view_template_path',OPENPOS_DIR.'/default/'.$file_name,$display);
            
            if($this->_filesystem->is_file($file_path))
            {
                return $this->_filesystem->get_contents($file_path);
            }else{
                return '';
            }
        }
        public function addMessage($table_id,$messages = array()){
            $table_data = $this->bill_screen_data($table_id);
            $table_data['messages'] = $messages;
            try{
                $this->update_table_bill_screen($table_id,$table_data);
                return true;
            }catch(Exception $e)
            {
                return false;
            }
        }
        public function getMessages($table_id){
            $table_data = $this->bill_screen_data($table_id);
            
            $result = array();
            if(isset($table_data['messages']) &&  !empty($table_data['messages']))
            {
                foreach($table_data['messages'] as $key => $m)
                {
                    $result[$key] = array(
                        'id' => $key,
                        'content' => $m,
                        'desk_id' => $table_id ,
                        'desk' => $table_data['desk']['name'],
                        'time_stamp' => $key //UTC time stamp
                    );
                }
            }
            return $result;
        }
        public function clearMessages($table_id){
            $table_data = $this->bill_screen_data($table_id);
            $table_data['messages'] = array();
            try{
                $this->update_table_bill_screen($table_id,$table_data);
                return true;
            }catch(Exception $e)
            {
                return false;
            }
        }

        public function getNotifications($last_check){
            $message = false;//sprintf( __('You have new message from table %s','openpos'),'ngoai troi');
            return $message;
        }
        public function get_all_update_data($warehouse_id,$time_stamp = 0,$time_stamp_utc = 0)
        {
            //$tables_version = $op_table->tables_version($warehouse_id);
            //$ready_dish = $op_table->ready_dishes($warehouse_id);
            $tables = $this->tables($warehouse_id);

            $table_ids = array();
            foreach($tables as $t)
            {
                $table_ids[] = $t['id'];
            }
           
            

            $request_takeaway = isset($_REQUEST['takeaway']) ?  json_decode(stripslashes($_REQUEST['takeaway']),true) : array();
            

            $tables_version = array();
            $ready_dish = array();
            $desk_message = array();
            $deleted_takeaway = array();
            
            if ($handle = opendir( $this->_bill_data_path)) {

                while (false !== ($entry = readdir($handle))) {

                    if ($entry != "." && $entry != "..") {

                        if(strpos($entry,'.json') > 0)
                        {
                            $table_id = str_replace('.json','',$entry);
                            $allow = in_array($table_id,$table_ids);
                            $table_type = 'dine_in';
                            if(!$allow && strpos($entry,'takeaway') === 0)
                            {
                                $table_type = 'takeaway';
                                $allow = true;


                                $filename = $entry;
                                $deleted_path = $this->generate_removing_file_path($filename,$warehouse_id);
                                if(file_exists($deleted_path))
                                {
                                    $allow = false;
                                }

                            }

                            if($allow)
                            {
                                $file_path = $this->_bill_data_path.'/'.$entry;
                                $data = $this->_filesystem->get_contents($file_path);
                                if($data)
                                {
                                    $result_table = json_decode($data,true);
                                    if($warehouse_id >= 0)
                                    {
    
                                        if( isset($result_table['desk']['warehouse_id']) && $result_table['desk']['warehouse_id'] != $warehouse_id ){
                                            continue;
                                        }
                                    }
                                    //table version
                                    $version = isset($result_table['ver']) ? $result_table['ver'] : 0;
                                    $tables_version[$table_id] = $version;


                                    //ready item
                                    $items = isset($result_table['items']) ? $result_table['items'] : array();
                                    $table = isset($result_table['desk']) ? $result_table['desk'] : [];
                                    $table_name = isset($table['name']) ? $table['name'] : '';
                                    $table_id = isset($table['id']) ? $table['id'] : 0;
                                    if(!empty($items))
                                    {
                                        foreach ($items as $_item)
                                        {
                                            if(isset($_item['done']) && $_item['done'] == 'ready')
                                            {
                                                $item_dinning = isset($_item['dining']) ? $_item['dining'] : $table_type;
                                                if(!$item_dinning)
                                                {
                                                    $item_dinning = 'dine_in';
                                                }
                                                $ready_dish[] = array(
                                                    'id' => $_item['id'],
                                                    'table_id' => $table_id,
                                                    'table_name' => $table_name,
                                                    'table_type' => $table_type,
                                                    'item_dinning' => $item_dinning,
                                                    'item_name' => $_item['qty'].' x '.$_item['name']
                                                );
                                            }
                                        }

                                    }
                                    //desk messages
                                    $messages = isset($result_table['messages']) ? $result_table['messages'] : array();
                                    $new_messages = array();
                                    if(!empty($messages))
                                    {
                                        foreach($messages as $time_utc => $content)
                                        {
                                            if($time_stamp_utc < $time_utc)
                                            {
                                                $new_messages[] = $content;
                                            }
                                        }
                                    }
                                    if(!empty($new_messages))
                                    {
                                        $desk_message[] = $table_name;
                                    }

                                }
                            }
                            
                        }
                    }
                }
                closedir($handle);
            }

           

            foreach($request_takeaway as $takeaway_id)
            {
                $filename = 'takeaway-'.$takeaway_id.'.json';
                $deleted_path = $this->generate_removing_file_path($filename,$warehouse_id);
                if(file_exists($deleted_path))
                {
                    $deleted_takeaway[] = $takeaway_id;
                }

            }
            $result = array(
                'tables_version' => $tables_version,
                'ready_dish' => $ready_dish,
                'desk_message' => $desk_message,
                'deleted_takeaway' => $deleted_takeaway,
            );
           
            return $result;
        }
        public function kitchen_custom_action($custom_action){
            return apply_filters('op_kitchen_custom_action',array(),$custom_action,$this);
        }
        public function kitchen_data_url($outlet_id = 0){
            $upload_dir = wp_upload_dir();
            $url = $upload_dir['baseurl'];
            $url = ltrim($url,'/');
            return $url.'/openpos/kitchen/'.$outlet_id.'.json';
        }
       
        public function update_kitchen_data($outlet_id = 0)
        {
            global $op_woo;
            $warehouse_id = $outlet_id;
            $result = array();
            $result_formated = array();

            $result_orders = array();
            $result_items = array();
            $items_formated = array();
            $orders_formated = array();

            $all_area = $op_woo->getListRestaurantArea();
            $result_orders['all'] = array();
            $result_items['all'] = array();
            foreach($all_area as $a => $area)
            {
                $result_orders[$a] = array();
                $result_items[$a] = array();
            }

            $total = 0;
            if($warehouse_id >= 0)
            {
                $off_tables = $this->tables((int)$warehouse_id);
                $takeaway_tables = $this->takeawayTables((int)$warehouse_id);
                $tables = array_merge($off_tables,$takeaway_tables);

                foreach($tables as $table)
                {

                    $table_type = isset($table['dine_type'])? $table['dine_type'] :'dine_in';
                
                    $table_data = $this->bill_screen_data($table['id'],$table_type);
                    
                    
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
                            $product_id = isset($item['product_id']) ? $item['product_id'] : 0;

                            

                            if(isset($item['done']) && ($item['done'] == 'done' || $item['done'] == 'done_all'))
                            {
                                 continue;
                            }else{
                                $is_full_serverd = false;
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
                                $item_kitchen_area = array();

                                foreach($all_area as $a => $area)
                                {
                                    if($product_id)
                                    {
                                        if($op_woo->check_product_kitchen_op_type($a,$product_id,$item)){
                                            $item_kitchen_area[] = $a;
                                        }
                                    }
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
                                    'allow_action' => array(),
                                    'kitchen_area' => $item_kitchen_area,
                                );
                                
                                $dish_data = apply_filters('op_kitchen_dish_item_data',$tmp,$table_data,$item);
                                if($dish_data && !empty($dish_data) )
                                {
                                    $result_items['all'][$id] =  $dish_data;
                                    $formatted_items['all'][] = $dish_data;
                                    $kitchen_area = isset($dish_data['kitchen_area']) ? $dish_data['kitchen_area'] : array();
                                    foreach($kitchen_area as $a)
                                    {
                                        $result_items[$a][$id] =  $dish_data;
                                        $formatted_items[$a][] = $dish_data;
                                    }
                                    $total++;
                                }
                            }
                        }

                        if( !empty($formatted_items['all']) && !$is_full_serverd)
                        {
                            $table_data['items'] = $formatted_items['all'];
                            $table_data['allow_action'] = array();
                            $table_data['dining'] = isset($table_data['dining']) ? $table_data['dining'] : '';
                            $table_data['order_timestamp'] = isset($table_data['created_at_time']) && $table_data['created_at_time'] > 100 ? $table_data['created_at_time'] : $last_order_timestamp;
                            if($last_order_timestamp)
                            {
                                if(isset($result_items['all'][$last_order_timestamp]))
                                {
                                    $last_order_timestamp = $last_order_timestamp + rand(1,10);
                                }
                                $result_orders['all'][$last_order_timestamp] = apply_filters('op_kitchen_dish_table_data',$table_data);
                            }else{
                                $result_orders['all'][] = apply_filters('op_kitchen_dish_table_data',$table_data);
                            }
                            foreach($formatted_items as $a => $area_items)
                            {
                                if($a != 'all')
                                {
                                    $table_data['items'] = $formatted_items[$a];
                                    if($last_order_timestamp)
                                    {
                                        $result_orders[$a][$last_order_timestamp] = apply_filters('op_kitchen_dish_table_data_area',$table_data,$a);
                                    }else{
                                        $result_orders[$a][] = apply_filters('op_kitchen_dish_table_data_area',$table_data,$a);
                                    }
                                }
                            }
                        }
                        
                        
                    }
                }
            }
            
            foreach($result_orders as $a => $_result_orders)
            {
                if(!empty($_result_orders))
                {
                    $keys = array_keys($_result_orders);
                    sort($keys);
                    
                    foreach($keys as  $r)
                    {
                        $orders_formated[$a][] = $_result_orders[$r];
                    }
                }else{
                    $orders_formated[$a] = array();
                }
            }
            if(!empty($result_items))
            {
                foreach($result_items as $a => $_result_items)
                {
                    if(empty($_result_items))
                    {
                        $items_formated[$a] = array();
                    }else{
                        $i = 1;
                        $keys = array_keys($_result_items);
                        $min_key = min($keys);
                        foreach($_result_items as  $r)
                        {
                            $key = 1*$r['order_timestamp'] - $min_key;
                            if(isset($r['local_id']) && $r['local_id'])
                            {
                                $key = 1*$r['local_id'] - $min_key;
                            }
                            
                            $r['priority'] = round($i / $total,2) * 100;
                            $items_formated[$a][$key] = $r;
    
                            $i++;
                        }
                    }
                    

                }
            }
            $result_formated['orders'] = $orders_formated;
            $result_formated['items'] = $items_formated;
            $kitchen_data = $result_formated;
            $data_file = $this->_kitchen_data_path.'/'.$outlet_id.'.json';

           
            $final_kitchen_data = apply_filters('op_kitchen_tables_data',$kitchen_data,$outlet_id,$this);
            

            $file_mode = $this->get_file_mode();
            
            $this->_filesystem->put_contents(
                $data_file,
                json_encode($final_kitchen_data),
                $file_mode // predefined mode settings for WP files
            );
        }
        public function removed_deleted_markup($warehouse_id = 0,$table_id = ''){
            $deleted_files = array();
            $takeaway_files = array();
            $diff_files = array();
            if($table_id)
            {
                
                $file = $table_id.'.json';
                $path = $this->generate_removing_file_path($file,$warehouse_id);
                
                if(file_exists($path))
                {
                    $diff_files[] = $file; // format takeaway_1234.json
                }
                
            }else{
                if ($handle = opendir( $this->_bill_data_path_deleted.'/'.$warehouse_id)) {
    
                    while (false !== ($entry = readdir($handle))) {
    
                        if ($entry != "." && $entry != "..") {
    
                            if(strpos($entry,'.json') > 0)
                            {
                               
                                $deleted_files[] = str_replace('removing_','',$entry);
                                
                            }
                        }
                    }
                    closedir($handle);
                }
                if ($handle = opendir( $this->_bill_data_path)) {
        
                    while (false !== ($entry = readdir($handle))) {
    
                        if ($entry != "." && $entry != "..") {
    
                            if(strpos($entry,'.json') > 0)
                            {
                               
                                $takeaway_files[] = $entry;
                                
                            }
                        }
                    }
                    closedir($handle);
                }
                
                $diff_files = array_diff($deleted_files,$takeaway_files);
            }
            
            
            foreach($diff_files as $file)
            {
                $path = $this->generate_removing_file_path($file,$warehouse_id);
                
                unlink($path);
            }
        }

        public function getLastTakeawayNumber($register_id,$warehouse_id){
            $result_number = 0;
            
            if ($handle = opendir( $this->_bill_data_path)) {

                while (false !== ($entry = readdir($handle))) {

                    if ($entry != "." && $entry != ".." && strpos($entry,'takeaway-') === 0) {

                        if(strpos($entry,'.json') > 0)
                        {
                            $table_id = str_replace('.json','',$entry);
                            
                            if(strpos($table_id,'takeaway-'.$register_id) === 0){
                                $_result_number  = 1 * str_replace('takeaway-','',$table_id);
                                if($_result_number > $result_number)
                                {
                                    $result_number = $_result_number;
                                }
                            }

                        }
                    }
                }
                closedir($handle);
            }
            return $result_number;

        }
        public function getTakeawayNumber($register_id,$warehouse_id = 0){
            $takeaway_number = 1 * ($register_id . '0000');
            $last_number = $this->getLastTakeawayNumber($register_id,$warehouse_id);
            if($last_number > $register_id)
            {
                $takeaway_number = $last_number;
            }
            return ($takeaway_number + 1);
        }
    }
}
?>