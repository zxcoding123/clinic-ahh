<?php
// This will be captured as PDF content
?>
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; }
        .header { text-align: center; margin-bottom: 20px; }
        .university { font-weight: bold; font-size: 18px; }
        .patient-info { margin-bottom: 20px; }
        .rx { font-size: 24px; font-weight: bold; margin: 10px 0; }
        .prescription { margin-bottom: 30px; }
        .footer { margin-top: 50px; text-align: right; }
        .signature { margin-top: 50px; border-top: 1px solid #000; width: 200px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="university">WESTERN MINDANAO STATE UNIVERSITY</div>
        <div>Zamboanga City</div>
        <div>UNIVERSITY HEALTH SERVICES CENTER</div>
    </div>
    
    <div class="patient-info">
        <div><strong>Name:</strong> <?php echo htmlspecialchars($appointment['first_name'].' '.$appointment['last_name']); ?></div>
        <div><strong>Age:</strong> <?php echo htmlspecialchars($age); ?></div>
        <div><strong>Sex:</strong> <?php echo htmlspecialchars(ucfirst($appointment['sex'])); ?></div>
        <div><strong>Date:</strong> <?php echo date('F j, Y'); ?></div>
    </div>
    
    <div class="rx">Rx</div>
    
    <div class="prescription">
        <?php echo nl2br(htmlspecialchars($prescription_text)); ?>
    </div>
    
    <div class="footer">
        <div class="signature"></div>
        <div>FELICITAS ASUNCION C. ELAGO, MD</div>
        <div>MEDICAL OFFICER III</div>
    </div>
</body>
</html>