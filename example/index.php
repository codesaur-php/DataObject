<?php

/**
 * DataObject жишээ кодууд
 *   - index_mysql.php
 *   - index_postgres.php
 */

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>codesaur/dataobject – Examples</title>
<style>
    body {
        font-family: Arial, sans-serif;
        background: #f4f6f8;
        padding: 40px;
    }
    .wrap {
        max-width: 600px;
        margin: auto;
        background: #fff;
        padding: 30px;
        border-radius: 12px;
        box-shadow: 0 0 12px rgba(0,0,0,0.08);
    }
    h1 {
        font-size: 22px;
        text-align: center;
        margin-bottom: 25px;
        color: #333;
        text-transform: uppercase;
    }
    .links {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }
    a {
        text-decoration: none;
        padding: 14px 18px;
        display: block;
        border-radius: 8px;
        border: 2px solid #007bff;
        color: #007bff;
        font-weight: bold;
        font-size: 16px;
        text-align: center;
        transition: 0.2s;
    }
    a:hover {
        background: #007bff;
        color: white;
    }
    .footer {
        margin-top: 25px;
        font-size: 13px;
        text-align: center;
        color: #888;
    }
</style>
</head>
<body>

<div class="wrap">
    <h1>DataObject Example Runner</h1>

    <div class="links">
        <a href="mysql.php">▶ Run MySQL Example</a>
        <a href="postgres.php">▶ Run PostgreSQL Example</a>
    </div>

    <div class="footer">
        <p>codesaur/dataobject &copy; <?= date('Y') ?></p>
    </div>
</div>

</body>
</html>
