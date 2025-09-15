<?php
session_start();
require_once 'config.php';


header('Content-Type: application/json');


// Read raw POST data
$rawData = file_get_contents("php://input");
$data = json_decode($rawData, true);

// Validate required fields
$required = [
    'parentName',
    'childName',
    'residence',
    'procedure',
    'witnessPrintedName',
    'parentPrintedName',
    'patientPrintedName',
    'witnessDate',
    'parentDate',
    'patientDate',
    'witnessSignature',
    'parentSignature',
    'patientSignature'
];

foreach ($required as $field) {
    if (empty($data[$field])) {
        http_response_code(400);
        echo json_encode(["error" => "Missing field: $field"]);
        exit;
    }
}

// Save signature images
function saveSignature($base64, $prefix)
{
    // Remove data URL prefix
    $img = str_replace('data:image/png;base64,', '', $base64);
    $img = str_replace(' ', '+', $img);
    $data = base64_decode($img);

    if (!file_exists('signatures')) {
        mkdir('signatures', 0777, true);
    }

    $fileName = "signatures/{$prefix}_" . time() . ".png";
    file_put_contents($fileName, $data);
    return $fileName;
}

$witnessSigPath = saveSignature($data['witnessSignature'], 'witness');
$parentSigPath = saveSignature($data['parentSignature'], 'parent');
$patientSigPath = saveSignature($data['patientSignature'], 'patient');

$witness_date = date('Y-m-d');
$parent_date = date('Y-m-d');
$patient_date = date('Y-m-d');

// Insert into DB
$stmt = $conn->prepare("
    INSERT INTO consent_forms 
    (parent_name, child_name, residence, consent_procedure, 
     witness_printed_name, parent_printed_name, patient_printed_name,
     witness_date, parent_date, patient_date,
     witness_signature, parent_signature, patient_signature)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "sssssssssssss",
    $data['parentName'],
    $data['childName'],
    $data['residence'],
    $data['procedure'],
    $data['witnessPrintedName'],
    $data['parentPrintedName'],
    $data['patientPrintedName'],
    $witness_date,
    $parent_date,
    $patient_date,
    $witnessSigPath,
    $parentSigPath,
    $patientSigPath
);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Consent form saved successfully"]);
} else {
    http_response_code(500);
    echo json_encode(["error" => "Failed to save form: " . $stmt->error]);
}

$stmt->close();
$conn->close();
