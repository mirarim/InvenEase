<?php
  $page_title = 'Admin Home Page';
  require_once('includes/load.php');
  // Checkin What level user has permission to view this page
   page_require_level(1);
?>
<?php
 $c_categorie     = count_by_id('categories');
 $c_product       = count_by_id('products');
 $c_sale          = count_by_id('sales');
 $c_user          = count_by_id('users');
 $products_sold   = find_higest_saleing_product('10');
 $recent_products = find_recent_product_added('5');
 $recent_sales    = find_recent_sale_added('5');
?>
<?php include_once('layouts/header.php'); ?>

<?php 

    /**
     * Generates an array containing the years starting from the 
     * current year and going back X years. The current year is included, and 
     * the years are returned in descending order.
     * @param int $x The number of years to retrieve
     * @return array An array of integers representing the last X years.
     */
    function get_last_x_years(int $x){
        $substractor = function ($index) {return \date('Y') - $index;};
        return \array_map($substractor,\range(0, $x - 1));
    }

    function list_months(){
        return ['Jan', 'Feb', 'Mar', 'Apr', 'May' , 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    }

    /**
     * Retrieves monthly sales data for the last X years.
     * This function generates an array structured to represent a dataset 
     * compatible with chart.js, containing monthly sales figures for each 
     * of the last X years. The dataset includes labels for each month 
     * and corresponding sales values.
     * @param int $x The number of years for which to retrieve monthly sales data, 
     *               must be a positive integer.
     * @return array An array structured for chart.js dataset
     */
    function get_monthy_sales_last_x_years(int $x): array {
        global $db;
        $i = 0;
        $columns = [];
        $months = list_months();
        $years = get_last_x_years($x);
        while ($i < 12) {
            $format = "SUM(CASE WHEN MONTH(date) = %s THEN price ELSE 0 END) AS '%s'";
            \array_push($columns, \sprintf($format, $i + 1, $months[$i]));
            $i++;
        }
        $query = 'SELECT YEAR(date) AS year, ' 
                . \implode(',', $columns) 
                . 'FROM sales WHERE YEAR(date) IN ('
                . \implode(',', $years)
                . ') GROUP BY YEAR(date) ORDER BY year;';
        $datasets = [];
        $results = $db->query($query);
        if(!$db->num_rows($results)) return [];
        foreach ($results as $result) {
            $year = $result['year'];
            \array_shift($result);
            \array_push($datasets,[
                'label' => $year,
                'data' => $result
            ]);
        }
        return $datasets;
    }

    /**
     * Retrieves total sales data for the last X years.
     * This function generates an array structured to represent a dataset 
     * compatible with Chart.js, containing total sales figures for each 
     * of the last X years. The dataset includes year labels and 
     * corresponding total sales values.
     * @param int $x The number of years for which to retrieve total sales data, 
     *               must be a positive integer.
     * @return array An array structured for chart.js dataset.
     */
    function get_total_sales_last_x_years(int $x){
        global $db;
        $years = get_last_x_years($x);
        $format = "SELECT YEAR(date) AS year, SUM(price) AS total_sales FROM sales "
                . "WHERE YEAR(date) IN (%s) GROUP BY YEAR(date) ORDER BY year;";
        $query = \sprintf($format, \implode(',',$years));
        $datasets = [];
        \sort($years);
        foreach ($years as $year) {
            \array_push($datasets, [
                'label' => \strval($year),
                'data' => [
                    'Total Sales' => '0.0'
                ]
            ]);
        }
        $results = $db->query($query);
        if($db->num_rows(statement: $results)){
            foreach ($results as $result) {
                $year = $result['year'];
                foreach ($datasets as $key => $dataset) {
                    if ($dataset['label'] === $year) {
                        $datasets[$key]['data']['Total Sales'] = $result['total_sales'];
                    }
                }
            }
        }
        return $datasets;
    }


    function get_monthly_profit_last_x_years(int $x){
        global $db;
        $columns  = [];
        $months   = list_months();
        $years    = get_last_x_years($x);
        $columns  = [];
        $datasets = [];
        while($x > 0) {
            $year = $years[$x - 1];
            $format = "SUM(IF(YEAR(s.date) = %s, (p.sale_price * s.qty) - (p.buy_price * s.qty), 0)) AS '%s'";
            \array_push($columns, \sprintf($format, $year, $year));
            \array_push($datasets, [
                'label' => \strval($year),
                'data' => []
            ]);
            $x = $x - 1;
        }
        $query = "SELECT DATE_FORMAT(s.date, '%b') AS month, "
               . \implode(', ', $columns)
               . "FROM sales s JOIN products p ON s.product_id = p.id "
               . "WHERE YEAR(s.date) IN ("
               . \implode(',', $years)
               . ") GROUP BY MONTH(s.date) ORDER BY MONTH(s.date);";
        $results = $db->query($query);
        if(!$db->num_rows($results)) return [];
        $i = 0;
        foreach ($results as $result) {
            $month = $months[$i];
            foreach ($datasets as $key => $dataset) {
                $year = $dataset['label'];
                $datasets[$key]['data'][$month] = $result[$year];
            }
            $i++;
        }
        return $datasets;
    }


    function get_total_profit_last_x_years(int $x){
        global $db;
        $datasets = [];
        $years    = get_last_x_years($x);
        while($x > 0) {
            $year = $years[$x - 1];
            \array_push($datasets, [
                'label' => \strval($year),
                'data' => [
                    'Total Profit' => '0.0'
                ]
            ]);
            $x = $x - 1;
        }
        $query = "SELECT YEAR(s.date) AS year, "
               . "SUM((p.sale_price * s.qty) - (p.buy_price * s.qty)) AS total_profit "
               . "FROM sales s JOIN products p ON s.product_id = p.id "
               . "WHERE YEAR(s.date) IN ("
               . \implode(',', $years)
               . ") GROUP BY YEAR(s.date) ORDER BY YEAR(s.date);";
        $results = $db->query($query);
        if(!$db->num_rows($results)) return [];
        if($db->num_rows($results)){
            foreach ($results as $result) {
                $year = $result['year'];
                foreach ($datasets as $key => $dataset) {
                    if ($dataset['label'] === $year) {
                        $datasets[$key]['data']['Total Profit'] = $result['total_profit'];
                    }
                }
            }
        }
        return $datasets;
    }

?>

<div class="row">
    <div class="col-md-6">
        <div class="panel panel-default">
            <div class="panel-heading">
                <strong>
                    <span class="glyphicon glyphicon-object-align-top"></span>
                    <span>Monthly Sales</span>
                </strong>
            </div>
            <div class="panel-body">
                <canvas id="chart_1"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="panel panel-default">
            <div class="panel-heading">
                <strong>
                    <span class="glyphicon glyphicon-object-align-top"></span>
                    <span>Annual Sales</span>
                </strong>
            </div>
            <div class="panel-body">
                <canvas id="chart_2"></canvas>
            </div>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-6">
        <div class="panel panel-default">
            <div class="panel-heading">
                <strong>
                    <span class="glyphicon glyphicon-object-align-top"></span>
                    <span>Monthly Profit</span>
                </strong>
            </div>
            <div class="panel-body">
                <canvas id="chart_3"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="panel panel-default">
            <div class="panel-heading">
                <strong>
                    <span class="glyphicon glyphicon-object-align-top"></span>
                    <span>Annual Profit</span>
                </strong>
            </div>
            <div class="panel-body">
                <canvas id="chart_4"></canvas>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
    class Options {
        responsive = true
        plugins = { legend: { position: 'top', }, title: { display: false, text: '' } }
    }
    const RenderTotalMonthlySales = () => {
        const data = { datasets: JSON.parse(`<?php echo json_encode(get_monthy_sales_last_x_years(3)); ?>`) }
        const config = { type: 'line', data: data, options: new Options }
        new Chart(document.getElementById('chart_1'), config)
    }
    const RenderAnnualSales = () => {
        const data = { datasets: JSON.parse(`<?php echo json_encode(get_total_sales_last_x_years(3)); ?>`) }
        const config = { type: 'bar', data: data, options: new Options }
        new Chart(document.getElementById('chart_2'), config)
    }
    const RenderTotalMonthlyProfit = () => {
        const data = { datasets: JSON.parse(`<?php echo json_encode(get_monthly_profit_last_x_years(3)); ?>`) }
        const config = { type: 'line', data: data, options: new Options }
        new Chart(document.getElementById('chart_3'), config)
    }
    const RenderAnnualProfit = () => {
        const data = { datasets: JSON.parse(`<?php echo json_encode(get_total_profit_last_x_years(3)); ?>`) }
        const config = { type: 'bar', data: data, options: new Options }
        new Chart(document.getElementById('chart_4'), config)
    }
    setTimeout(()=>{
        RenderTotalMonthlySales()
        RenderAnnualSales()
        RenderTotalMonthlyProfit()
        RenderAnnualProfit()
    },100)
</script>

<?php include_once('layouts/footer.php'); ?>