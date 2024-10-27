<?php
  $page_title = 'Damage Reports Page';
  require_once('includes/load.php');
  // Checkin What level user has permission to view this page
   page_require_level(2);
?>
<?php

    /**
     * Determines if the given input string contains 
     * a value that resembles a number.
     * @param string $input The input string to check.
     * @return bool Returns true if the input is number-like, false otherwise.
     */
    function is_numberish (string $input):bool {
        return \is_string($input) && !empty($input) && \ctype_digit($input);
    }

    /** Handles the submission of new damage reports. */
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        global $db;
        try {
            $payload = \json_decode(
                \file_get_contents('php://input'),
                TRUE
            );
            if (\json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception(
                    'Payload is not json-parsable'
                );
            }
            if (!isset($payload['productId']) || 
                \trim($payload['productId']) === ''
            ) {
                throw new \Exception(
                    'Missing or empty Product Id'
                );
            }
            if (!isset($payload['description']) || 
                \trim($payload['description']) === ''
            ) {
                throw new \Exception(
                    'Missing or empty description'
                );
            }
            $productid   = remove_junk(
                $db->escape($payload['productId'])
            );
            $description = remove_junk(
                $db->escape($payload['description'])
            );

            if (!is_numberish($productid)) {
                throw new \Exception(
                    'Product Id is in invalid format'
                );
            }

            $product = find_by_id('products', (int) $productid);
            if (!$product) {
                throw new \Exception('Product not found');
            }
            $quantity = $product['quantity'];
            if ($quantity > 1) {
                $quantity--;
                $format = "UPDATE products SET quantity = '%s' WHERE id = %s";
                $query = \sprintf($format, $quantity, $productid);
                if (!$db->query($query)){
                    throw new \Exception('Product update failed');
                }
            }
            $query = "INSERT INTO damage_reports (product_id, description) "
                . "VALUES (".$productid.", '".$description."')";
            if(!$db->query($query)){
                throw new \Exception(message: 'Failed to save damage report data');
            }
            \http_response_code(200);
            $session->msg("s","Damage report has been added successfully.");
        } catch (\Exception $e) {
            \http_response_code(500);
            echo $e->getMessage();
        }
        /** @NOTE short-circuit */
        exit();
    }

    $view    = $_GET['view'] ?? 'default';
    $mode    = null;
    $product = [];
    $reports = [];
    $page    = $_GET['page'] ?? '1';
    
    $pagination = [
        'display:previous' => false, 
        'display:next' => false,
        'pages' => [],
        'items' => 5
    ];

    /**
     * Initializes the create report view
     * @param string|null $mode - either locked or null
     * @param array $product - contains the product data
     */
    function __init_create_view (string | null &$mode, array &$product): void {
        $product_id = $_GET['id'] ?? null;
        if ($product_id === null) return;
        $result = find_by_id('products', (int) $product_id);
        if (!$result) return;
        $product = $result;
        $mode = 'locked';
    }

    /**
     * Initializes the search product result
     * @return void
     */
    function __init_search_view (): void {
        global $db;
        \header('Content-Type: application/json');
        $name = $_GET['name'] ?? null;

        if ($name === null) {
            echo '[]';
            return;
        }
        $query = 'SELECT name, id FROM products WHERE name LIKE "%'.$db->escape($name).'%" LIMIT 10';
        $results = $db->query($query);
        if(!$db->num_rows($results)) {
            echo '[]';
            return;
        }
        $data = [];
        foreach ($results as $key => $result) {
            $data[$result['name']] = $result['id'];
        }
        echo \json_encode($data);
    }

    /**
     * Initializes the pagination component
     * @param int $page - current page as defined in query parameters
     * @param array $pagination - contains all pagination configs
     * @return void
     */
    function __init_paginator_view(int $page, array &$pagination) {
        global $db;
        $query  = "SELECT count(id) as total FROM damage_reports;";
        $results =  $db->query($query);
        if(!$db->num_rows($results)) {
            return;
        }
        $total = 0;
        foreach ($results as $key => $result) {
            $total = (int) $result['total'];
        }
        $items = \array_map(function($item){
            return (int) $item;
        }, \range(1, \ceil($total / $pagination['items'])));
        $pagination['pages'] = trim_viewable_pages($items, $page, $pagination);
        $pagination['display:previous'] = $page > 1; 
        $total_viewable_pages = \count($pagination['pages']);
        if ($total_viewable_pages > 0) {
            $pagination['display:next'] = $page !== $pagination['pages'][\count($pagination['pages']) - 1];
        }   
    }

    function trim_viewable_pages (array $nodes, int $page, array $pagination){
        $lnode = (int) $nodes[count($nodes) - 1];
        $margin_size = (int) floor($pagination['items'] / 2);

        $left_margins  = create_pagination_margin(1, $margin_size);
        $right_margins = create_pagination_margin(($lnode - $margin_size) + 1, $lnode);

        if (\in_array($page, $left_margins)) {
            return \array_slice($nodes, 0, $pagination['items']);
        }

        if (\in_array($page, $right_margins)) {
            return \array_slice($nodes, -1 * $pagination['items']);
        }

        if ($page > $lnode) return [];

        $left_margins =  create_pagination_margin($page - $margin_size, $page - 1);
        $right_margins = create_pagination_margin($page + 1, $page + $margin_size);

        return \array_merge(
            $left_margins,
            [$page],
            $right_margins
        );
        
    }

    function create_pagination_margin(int $start, int $end){
        return \array_map(function($item){
            return (int) $item;
        }, \range($start, $end));
    }

    /**
     * Initializes listing of all the data. 
     * @param int $page - current page as defined in query parameters
     * @param array $reports - contains all the data
     * @param array $pagination - contains all pagination configs
     * @return void
     */
    function __init_list_view (int &$page, array &$reports, array &$pagination): void {
        global $db;
        if (!is_numberish($page)) $page = '1';
        $offset = ((int) $page - 1) * 5;
        __init_paginator_view($page, $pagination);
        $query = "SELECT DR.id, DR.description, DR.date as report_date, "
                ."P.name as product_name, P.id as product_id "
                ."FROM damage_reports DR JOIN products P ON P.id = DR.product_id "
                ."ORDER BY DR.date DESC "
                ."LIMIT 5 OFFSET ".$offset.";";
        $results =  $db->query($query);
        if(!$db->num_rows($results)) {
            $reports = [];
            return;
        }
        foreach ($results as $key => $result) {
            \array_push($reports, $result);
        }
    }

    switch ($view) {
        case 'create': 
            __init_create_view($mode, $product);
            break;
        case 'search': 
            __init_search_view();
            /** @NOTE short-circuit */
            exit();
            break; 
        default: 
            $view = 'list';
            __init_list_view($page, $reports, $pagination);
            break;
    }

?>
<?php include_once('layouts/header.php'); ?>

<?php if ($view === 'create'): ?>
    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <strong>
                        <span class="glyphicon glyphicon-object-align-top"></span>
                        <span>Create New Damage Report</span>
                    </strong>
                </div>
                <div class="panel-body">
                    <form id="create_report_form" method="POST" autocomplete="off">
                        <input type="hidden" id="product_id" value="<?php echo $product['id'] ?? null; ?>">
                        <?php if ($mode !== null): ?>
                            <div class="form-group">
                                <label for="product_name">Product Name</label>
                                <div class="input-group">
                                    <span class="input-group-addon">
                                        <i class="glyphicon glyphicon-th-large"></i>
                                    </span>
                                    <input id="product_name" disabled type="text" class="form-control" name="product-title" value="<?php echo remove_junk($product['name']);?>">
                                </div>
                            </div>
                            <input id="search_product_bar" style="display:none;">
                        <?php endif; ?>
                        <?php if ($mode === null): ?>
                            <div class="form-group">
                                <label for="product_name">Product Name</label>
                                <div class="input-group">
                                    <span class="input-group-addon">
                                        <i class="glyphicon glyphicon-search"></i>
                                    </span>
                                    <input id="search_product_bar" type="text" class="form-control" name="product-title" value="">
                                </div>
                                <div id="search_result"></div>
                            </div>
                        <?php endif; ?>
                        <div class="form-group">
                            <label for="exampleFormControlTextarea1">Damage Description</label>
                            <textarea id="damage_description" class="form-control" id="exampleFormControlTextarea1" rows="3"></textarea>
                        </div>
                        <button id="submit" disabled type="submit" name="product" class="btn btn-danger">Submit</button>
                        <button id="cancel" class="btn btn-light">Cancel</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script type="text/javascript" src="libs/js/reports.js"></script>
    <style>
        #search_result {
            position: absolute;
            background-color: white;
            width: 500px;
            margin-left: 40px;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.2);
            z-index: 1;
        }
        .search-product-class {
            padding: 7px 8px;
            border-bottom: solid 1px #e9e9e9;
            cursor: pointer;
        }
        .search-product-class:hover {
            background-color: #f3f3f3;
        }
    </style>
<?php endif; ?>

<?php if ($view === 'list'): ?>
    <div class="row">
        <div class="col-md-12">
            <?php echo display_msg($msg); ?>
        </div>
        <div class="col-md-12">
            <div class="panel panel-default">
                <div class="panel-heading clearfix">
                    <div class="pull-left">
                        <div class="panel-heading">
                            <strong>
                                <span class="glyphicon glyphicon-object-align-top"></span>
                                <span>Damage Reports</span>
                            </strong>
                        </div>
                    </div>
                    <div class="pull-right">
                        <a href="reports.php?view=create" class="btn btn-primary">Add New</a>
                    </div>
                </div>
                <div class="panel-body">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th class="text-center" style="width: 50px;">#</th>
                                <th> Product Title </th>
                                <th> Description </th>
                                <th class="text-center" style="width: 10%;"> Report Date </th>
                                <th class="text-center" style="width: 100px;"> Actions </th>
                            </tr>
                        </thead>
                        <tbody> 
                            <?php foreach ($reports as $report):?> 
                                <tr>
                                    <td class="text-center"> <?php echo $report['id'];?> </td>
                                    <td> <?php echo remove_junk($report['product_name']); ?> </td>
                                    <td> <?php echo remove_junk($report['description']); ?> </td>
                                    <td> <?php echo explode(' ',remove_junk($report['report_date']))[0]; ?> </td>
                                    <td class="text-center">
                                        <a href="report_delete.php?id=<?php echo (int)$report['id'];?>" class="btn btn-danger btn-xs"  title="Delete" data-toggle="tooltip">
                                            <span class="glyphicon glyphicon-trash"></span>
                                        </a>
                                    </td>
                                </tr> 
                            <?php endforeach; ?> 
                        </tbody>
                    </table>

                    <?php if (count($reports) === 0): ?>
                        <div class="w-100 text-center">You have no damage reports yet.</div>
                    <?php endif; ?> 
                    <?php if (count($reports) > 0): ?>
                        <nav aria-label="...">
                            <ul class="pagination">
                                <li class="page-item <?php echo $pagination['display:previous'] ? '' : 'disabled' ?>">
                                    <a class="page-link" <?php if ($pagination['display:previous']) {echo 'href=?page=' . $page - 1;} ?> tabindex="-1">Previous</a>
                                </li>
                                <?php 
                                    foreach ($pagination['pages'] as $pageitem) {
                                        if ($pageitem !== $page) {
                                            echo '<li class="page-item"><a class="page-link" href="?page='.$pageitem.'">'.$pageitem.'</a></li>';
                                            continue;
                                        }
                                        echo '<li class="page-item active">';
                                        echo '<a class="page-link" href="?page='.$pageitem.'">'.$pageitem.'<span class="sr-only">(current)</span></a>';
                                        echo '</li>';
                                    }
                                ?>
                                <li class="page-item <?php echo $pagination['display:next'] ? '' : 'disabled' ?>">
                                    <a class="page-link" <?php if ($pagination['display:next']) {echo 'href=?page=' . $page + 1;} ?>>Next</a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>             



                </div>
            </div>
        </div>
    </div>
<?php endif; ?>   


<?php include_once('layouts/footer.php'); ?>