<?php
session_start();

// Simple session test
$_SESSION['test'] = "Session is working";
error_log("Test session set: " . json_encode($_SESSION));

// Simple redirect test
if (isset($_POST['submit'])) {
    error_log("Test form submitted");
    $_SESSION['error'] = "Test error message";
    header("Location: /test_login.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Test Login</title>
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate">
</head>
<body>
    <h1>Test Login</h1>
    <form action="/test_login.php" method="post">
        <input type="text" name="test_input" placeholder="Test Input">
        <button type="submit" name="submit">Submit</button>
    </form>

    <?php if (isset($_SESSION['error'])): ?>
        <p style="color: red;"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></p>
    <?php endif; ?>
</body>
</html>