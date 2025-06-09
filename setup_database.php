<?php
require_once 'config/database.php';

echo "<h2>CartHub Database Setup</h2>";
echo "<p>Setting up required database tables...</p>";

try {
    // Read and execute the SQL file
    $sql = file_get_contents('database_setup.sql');
    
    // Split the SQL into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    $success_count = 0;
    $error_count = 0;
    
    foreach ($statements as $statement) {
        if (empty($statement)) continue;
        
        try {
            $pdo->exec($statement);
            $success_count++;
            echo "<p style='color: green;'>✓ Executed successfully</p>";
        } catch (PDOException $e) {
            $error_count++;
            echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
        }
    }
    
    echo "<hr>";
    echo "<h3>Setup Complete!</h3>";
    echo "<p><strong>Successful operations:</strong> $success_count</p>";
    echo "<p><strong>Errors:</strong> $error_count</p>";
    
    if ($error_count == 0) {
        echo "<p style='color: green; font-weight: bold;'>All tables created successfully! Your CartHub database is ready.</p>";
        echo "<p><a href='index.php'>Go to Homepage</a> | <a href='buyer/dashboard.php'>Go to Buyer Dashboard</a></p>";
    } else {
        echo "<p style='color: orange;'>Some errors occurred, but the setup might still be functional. Check the errors above.</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>Fatal Error:</strong> " . $e->getMessage() . "</p>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>CartHub Database Setup</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        h2 { color: #333; }
        p { margin: 10px 0; }
        hr { margin: 30px 0; }
    </style>
</head>
<body>
</body>
</html>