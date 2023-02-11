





<div class="container">
    <div class="header-container">
        <div class="row">
            <div class="col-md-12 text-center">
                <h3><?php echo __('KitChen View','openpos'); ?></h3>
            </div>
        </div>
        <div class="row">
            <div class="col-sm-2 col-md-2 pull-left grid-view-control" >
                        <a href="javascript:void(0);" data-id="items" class="grid-view <?php echo $grid_type == 'items' ? 'selected':'' ; ?>">
                            <span class="glyphicon glyphicon-list-alt" aria-hidden="true"></span>
                        </a>
                        <a href="javascript:void(0);" data-id="orders" class="grid-view <?php echo $grid_type == 'orders' ? 'selected':'' ; ?>">
                            <span class="glyphicon glyphicon-th-large" aria-hidden="true"></span>
                        </a>
            </div>
            <div class="col-md-8">
                <div class="col-md-6 col-md-offset-3">
                    <form class="form-horizontal"  action="<?php echo $kitchen_url ; ?>" id="kitchen-form" method="get">
                        <div class="form-group">
                            <label for="inputEmail3" class="col-sm-3 control-label"><?php echo __('Area','openpos'); ?></label>
                            <div class="col-sm-8">
                                    <select class="form-control" name="type">
                                        <option value="all" <?php echo ($kitchen_type == 'all') ? 'selected':'';?> > <?php echo __('All','openpos'); ?></option>
                                        <?php foreach($all_area as $a_code => $area): ?>
                                            <option value="<?php echo esc_attr($a_code); ?>" <?php echo ($kitchen_type == $a_code ) ? 'selected':'';?> ><?php echo $area['label']; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="hidden" name="display" value="<?php echo $grid_type; ?>"  />
                                    <input type="hidden" name="id" value="<?php echo $id ; ?>"  />
                                    
                                    <input type="submit" style="display:none;" />
                            </div>
                            
                        </div>

                    </form>
                </div>
            </div>
            <div class="col-sm-2 col-md-2 pull-right" style="text-align:right;">
                        <a href="javascript:void(0);" data-id="<?php echo $id; ?>" id="refresh-kitchen"> <span class="glyphicon glyphicon-retweet" aria-hidden="true"></span> </a>
            </div>
        </div>
    </div>
    <div  id="bill-content">
        <div id="bill-content-orders" class="bill-content-container" style="display:none;">
            comming soon
        </div>
        <div id="bill-content-items" class="bill-content-container">
            <?php if($grid_type == 'items'): ?>
                        <table class="table table-bordered">
                            <thead>
                            <tr>
                                <th class="text-center">#</th>
                                <th><?php echo __('Item','openpos'); ?></th>
                                <th class="text-center"><?php echo __('Qty','openpos'); ?></th>
                                <th><?php echo __('Order Time','openpos'); ?></th>
                                <th><?php echo __('Table / Order','openpos'); ?></th>
                                <th class="text-center"><?php echo __('Ready ?','openpos'); ?></th>
                            </tr>
                            </thead>
                            <tbody id="kitchen-table-body">

                            </tbody>
                        </table>
            <?php else: ?>
                <div id="kitchen-table-body"></div>
            <?php endif; ?>
        </div>
        
    </div>

</div>


