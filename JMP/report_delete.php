<?php
  require_once('includes/load.php');
  // Checkin What level user has permission to view this page
  page_require_level(2);
?>
<?php
  $report = find_by_id('damage_reports',(int)$_GET['id']);
  if(!$report){
    $session->msg("d","Missing report id.");
    redirect('reports.php');
  }
?>
<?php
  $delete_id = delete_by_id('damage_reports',(int)$report['id']);
  if($delete_id){
      $session->msg("s","Damage report has been deleted successfully.");
      redirect('reports.php');
  } else {
      $session->msg("d","Damage report deletion failed.");
      redirect('reports.php');
  }
?>