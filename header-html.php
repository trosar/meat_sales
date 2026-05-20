<?php
// header-html.php - Include this in the <body> section of your page
// IMPORTANT: Set $page_title BEFORE including this file
// Usage: < ?php $page_title = "Your Page Title"; include 'header-html.php'; ? >

$page_title = $page_title ?? 'Plant Sales'; // Default title if not set
?>
<head>
    <title>Scout Fundraiser</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <link rel="stylesheet" href="styles.css?v=<?php echo $styles_version; ?>">
    <link rel="icon" type="image/x-icon" href="media/favicon.svg">
</head>
<body>
<div class="header">
    <a href="https://www.troop60.co/"><img class="troop_logo" src="media/Troop_60_Logo.png" alt="Troop 60 Logo"></a>
    <h2><?php echo htmlspecialchars($page_title); ?></h2>
</div>
