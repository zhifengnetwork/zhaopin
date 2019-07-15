<?php
require_once 'AopSdk.php';
?>
<!DOCTYPE html>
<html lang="zh-CN">
  <head>
    <?php include './base/common.php';?>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- 上述3个meta标签*必须*放在最前面，任何其他内容都*必须*跟随其后！ -->
    <title>开发者工具</title>
    <link href="./static/css/main.css" rel="stylesheet">
  </head>
  <body>
    <?php include './base/head.php';?>
    <div class="container">
      <!-- body begin -->
      <div class="container-body">
        <!-- Tabs -->
        <div id="tabs">
          <!-- Nav tabs -->
          
        </div>

      </div>
    </div>
    <!-- footer begin -->
    <?php include './base/foot.php';?>
  </body>
  <script src="./static/js/main.js"></script>
  <script src="./static/js/tabPanel.js"></script>
  <script type="text/javascript">
    var url = "./demo/service/MainService.php";
    $.post(url,function(json){
      $('#tabs').tabPanel({
        data:json
      })
    });

  </script>
</html>