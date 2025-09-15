<?php
// Start output buffering
ob_start();

// Set error logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'error_log.txt');
error_reporting(E_ALL);

// Set JSON header
header('Content-Type: application/json');

function sendResponse($success, $message) {
    echo json_encode(['success' => $success, 'message' => $message]);
    ob_end_flush();
    exit();
}

// Check vendor/autoload.php
if (!file_exists('vendor/autoload.php')) {
    error_log("test_tcpdf.php: vendor/autoload.php not found");
    sendResponse(false, 'Server configuration error: Missing autoload.');
}

// Check TCPDF
if (!file_exists('vendor/tecnickcom/tcpdf/tcpdf.php')) {
    error_log("test_tcpdf.php: vendor/tecnickcom/tcpdf/tcpdf.php not found");
    sendResponse(false, 'Server configuration error: Missing TCPDF.');
}

require 'vendor/autoload.php';
require 'vendor/tecnickcom/tcpdf/tcpdf.php';

try {
    // Initialize TCPDF
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('WMSU Health Services');
    $pdf->SetTitle('Test PDF');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(15, 5, 15);
    $pdf->SetAutoPageBreak(true, 10);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->AddPage();

    // Simple HTML content
    $content = '<h1>Test PDF</h1><p>This is a test PDF to verify TCPDF functionality.</p>';

    // Write HTML to PDF
    $pdf->writeHTML($content);
    $pdf->Output('test.pdf', 'D'); // Download PDF
    error_log("test_tcpdf.php: PDF generated successfully");
    sendResponse(true, 'PDF generated successfully.');
} catch (Exception $e) {
    error_log("test_tcpdf.php: PDF generation failed: " . $e->getMessage());
    sendResponse(false, 'PDF generation failed: ' . $e->getMessage());
} finally {
    ob_end_flush();
}
?>