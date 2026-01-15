<?php
// PHP runs FIRST (on server)
$server_time = date('Y-m-d H:i:s');
$random_number = rand(1, 100);
?>
<!DOCTYPE html>
<html>
<body>
    <!-- HTML runs SECOND (in browser) -->
    <h1>PHP Flow Test</h1>
    
    <!-- PHP inserts values into HTML -->
    <p>Server time: <?php echo $server_time; ?></p>
    <p>Random number: <?php echo $random_number; ?></p>
    
    <!-- This is pure HTML/JS -->
    <p>Browser time: <script>document.write(new Date());</script></p>
    
    <button onclick="alert('Clicked!')">Click Me (JS)</button>
</body>
</html>
