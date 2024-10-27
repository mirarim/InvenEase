<?php
  $page_title = 'All Product';
  require_once('includes/load.php');
  // Checkin What level user has permission to view this page
   page_require_level(2);
  $products = join_product_table();
?>


<?php 
#######################  
# ADD THIS ENTIRE BLOCK OF PHP CODE
#######################  
$product_count_trigger_below = 2;
$out_of_stock_products       = [];
function __init_out_of_stock_alert(
    int $count_trigger,
    array &$out_of_stock_products
): void {
    global $db;
    $query = "SELECT * FROM products "
            ."WHERE CAST(quantity AS UNSIGNED) < "
            .$count_trigger.";";
    $results = $db->query($query);
    if(!$db->num_rows($results)) return;
    foreach ($results as $key => $result){
        \array_push($out_of_stock_products, $result);
    }
}

__init_out_of_stock_alert(
$product_count_trigger_below,
$out_of_stock_products
);

#######################
?>


<?php include_once('layouts/header.php'); ?>
  <div class="row">
     <div class="col-md-12">
       <?php echo display_msg($msg); ?>
     </div>

    <!--  
      ################################
      ADD THIS ENTIRE BLOCK FOR ALERTS
      ################################
    -->
     <div class="col-md-12">
        <?php foreach ($out_of_stock_products as $product):?>
          <div class="alert alert-danger" role="alert">
            <strong><?php echo $product['name']; ?></strong> is getting out of stock. Please update the stock <a href="edit_product.php?id=<?php echo $product['id'] ?>">here</a>.
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
        <?php endforeach; ?>
     </div>


    <div class="col-md-12">
      <div class="panel panel-default">
        <div class="panel-heading clearfix">
         <div class="pull-right">
           <a href="add_product.php" class="btn btn-primary">Add New</a>
         </div>
        </div>
        <div class="panel-body">
          <table class="table table-bordered">
            <thead>
              <tr>
                <th class="text-center" style="width: 50px;">#</th>
                <th> Photo</th>
                <th> Product Title </th>
                <th class="text-center" style="width: 10%;"> Categories </th>
                <th class="text-center" style="width: 10%;"> In-Stock </th>
                <th class="text-center" style="width: 10%;"> Buying Price </th>
                <th class="text-center" style="width: 10%;"> Selling Price </th>
                <th class="text-center" style="width: 10%;"> Product Added </th>
                <th class="text-center" style="width: 100px;"> Actions </th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($products as $product):?>
              <tr>
                <td class="text-center"><?php echo count_id();?></td>
                <td>
                  <?php if($product['media_id'] === '0'): ?>
                    <img class="img-avatar img-circle" src="uploads/products/no_image.png" alt="">
                  <?php else: ?>
                  <img class="img-avatar img-circle" src="uploads/products/<?php echo $product['image']; ?>" alt="">
                <?php endif; ?>
                </td>
                <td> <?php echo remove_junk($product['name']); ?></td>
                <td class="text-center"> <?php echo remove_junk($product['categorie']); ?></td>
                <td class="text-center"> <?php echo remove_junk($product['quantity']); ?></td>
                <td class="text-center"> <?php echo remove_junk($product['buy_price']); ?></td>
                <td class="text-center"> <?php echo remove_junk($product['sale_price']); ?></td>
                <td class="text-center"> <?php echo read_date($product['date']); ?></td>
                <td class="text-center">
                  <div class="btn-group">
                    <a href="edit_product.php?id=<?php echo (int)$product['id'];?>" class="btn btn-info btn-xs"  title="Edit" data-toggle="tooltip">
                      <span class="glyphicon glyphicon-edit"></span>
                    </a>

                    <!--
                       ################################ 
                        ADD THIS ENTIRE BLOCK FOR BUTTON LINK TO REPORT DAMAGES 
                       ################################
                    -->
                    <a href="reports.php?view=create&id=<?php echo (int)$product['id'];?>" class="btn btn-info btn-xs"  title="Report Damages" data-toggle="tooltip">
                      <span class="glyphicon glyphicon-wrench"></span>
                    </a>



                    <a href="delete_product.php?id=<?php echo (int)$product['id'];?>" class="btn btn-danger btn-xs"  title="Delete" data-toggle="tooltip">
                      <span class="glyphicon glyphicon-trash"></span>
                    </a>
                  </div>
                </td>
              </tr>
             <?php endforeach; ?>
            </tbody>
          </tabel>
        </div>
      </div>
    </div>
  </div>
  <?php include_once('layouts/footer.php'); ?>
