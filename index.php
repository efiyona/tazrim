<?php
    require('path.php');
    include(ROOT_PATH . "/app/database/db.php");
    date_default_timezone_set('Asia/Jerusalem');

    $responses = selectAll('responses');
    $target = selectOne('setup', ['description' => "target"]);
?>

<html lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>מערכת תזרים</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css" integrity="sha300000000000000000000000000000000000000000000084-wvfXpqpZZVQGK6TAh5PVlGOfQNHSoD2xbE+QkPxCAFlNEevoEH3Sl0sibVcOQVnN" crossorigin="anonymous">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <script src="https://kit.fontawesome.com/9a47092d09.js" crossorigin="anonymous"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.1/jquery.min.js"></script>
    <?php require(ROOT_PATH . "/assets/includes/setup_meta_data.php"); ?>
</head>
<body dir="rtl">
    <div class="container hero">
        <div class="row-start hero-content">
            <div class="flex flex-column gap-30">
                <h1>העפלנו,<br>אל גב ההר<br><span>העפלנו</span></h1>
            </div>
        </div>
    </div>
  
    <img src="<?php echo BASE_URL . 'assets/images/canvas/14.jpg'; ?>" alt="">

</body>
