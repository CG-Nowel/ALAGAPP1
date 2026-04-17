<?php
/** @var array $prescription */
$p = $prescription;
$medications = is_string($p['medications']) ? json_decode($p['medications'], true) : $p['medications'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Prescription <?= htmlspecialchars($p['prescription_number']) ?></title>
    <style>
        @page { margin: 1cm; }
        * { font-family: 'Georgia', serif; }
        body { padding: 20px; max-width: 800px; margin: 0 auto; }
        .header { border-bottom: 3px double #FF6B9A; padding-bottom: 15px; margin-bottom: 20px; }
        .header h1 { color: #FF6B9A; margin: 0; font-size: 28px; }
        .header p { margin: 2px 0; color: #666; font-size: 13px; }
        .rx-symbol { font-size: 48px; color: #FF6B9A; font-weight: bold; font-family: serif; }
        .patient-info { background: #f9f9f9; padding: 12px; border-radius: 8px; margin-bottom: 20px; }
        .patient-info p { margin: 3px 0; font-size: 14px; }
        .medications { margin: 20px 0; }
        .medications table { width: 100%; border-collapse: collapse; }
        .medications th { background: #FF6B9A; color: white; padding: 8px 12px; text-align: left; font-size: 13px; }
        .medications td { padding: 8px 12px; border-bottom: 1px solid #eee; font-size: 13px; }
        .footer { border-top: 1px solid #ddd; padding-top: 20px; margin-top: 40px; }
        .signature-line { border-top: 1px solid #333; width: 200px; text-align: center; padding-top: 5px; font-size: 12px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="no-print" style="text-align:center;margin-bottom:20px;">
        <button onclick="window.print()" style="background:#FF6B9A;color:white;border:none;padding:10px 30px;border-radius:8px;font-size:16px;cursor:pointer;">
            Print Prescription
        </button>
    </div>

    <div class="header">
        <div style="display:flex;justify-content:space-between;align-items:start;">
            <div>
                <h1>PediCare Clinic</h1>
                <p>123 Health St, Medical City, Metro Manila</p>
                <p>Tel: +63 917 123 4567 | info@pedicare.com</p>
            </div>
            <div style="text-align:right;">
                <div class="rx-symbol">Rx</div>
                <p><strong><?= htmlspecialchars($p['prescription_number']) ?></strong></p>
            </div>
        </div>
    </div>

    <div style="display:flex;justify-content:space-between;margin-bottom:10px;">
        <div>
            <strong>Prescribing Doctor:</strong> Dr. <?= htmlspecialchars($p['doctor_first_name'] . ' ' . $p['doctor_last_name']) ?><br>
            <small><?= htmlspecialchars($p['specialization'] ?? '') ?> | License: <?= htmlspecialchars($p['license_number'] ?? '') ?></small>
        </div>
        <div style="text-align:right;">
            <strong>Date:</strong> <?= date('F j, Y', strtotime($p['prescription_date'])) ?>
        </div>
    </div>

    <div class="patient-info">
        <p><strong>Patient:</strong> <?= htmlspecialchars($p['patient_first_name'] . ' ' . $p['patient_last_name']) ?></p>
        <p><strong>Date of Birth:</strong> <?= date('F j, Y', strtotime($p['patient_dob'])) ?> (Age: <?= (int)((time() - strtotime($p['patient_dob'])) / 31557600) ?> years)</p>
        <p><strong>Gender:</strong> <?= $p['patient_gender'] ?> | <strong>Weight:</strong> <?= $p['patient_weight'] ? $p['patient_weight'] . ' kg' : 'N/A' ?></p>
        <p><strong>Allergies:</strong> <?= htmlspecialchars($p['patient_allergies'] ?: 'None reported') ?></p>
        <p><strong>Parent/Guardian:</strong> <?= htmlspecialchars($p['parent_first_name'] . ' ' . $p['parent_last_name']) ?> | <?= htmlspecialchars($p['parent_phone'] ?? '') ?></p>
    </div>

    <?php if ($p['diagnosis']): ?>
    <p><strong>Diagnosis:</strong> <?= htmlspecialchars($p['diagnosis']) ?></p>
    <?php endif; ?>

    <div class="medications">
        <h3>Medications</h3>
        <?php if (is_array($medications)): ?>
        <table>
            <thead><tr><th>#</th><th>Medication</th><th>Dosage</th><th>Frequency</th><th>Duration</th><th>Instructions</th></tr></thead>
            <tbody>
            <?php foreach ($medications as $i => $med): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><strong><?= htmlspecialchars($med['name'] ?? '') ?></strong></td>
                <td><?= htmlspecialchars($med['dosage'] ?? '') ?></td>
                <td><?= htmlspecialchars($med['frequency'] ?? '') ?></td>
                <td><?= htmlspecialchars($med['duration'] ?? '') ?></td>
                <td><?= htmlspecialchars($med['instructions'] ?? '') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p><?= htmlspecialchars((string) $medications) ?></p>
        <?php endif; ?>
    </div>

    <?php if ($p['notes']): ?>
    <p><strong>Notes:</strong> <?= nl2br(htmlspecialchars($p['notes'])) ?></p>
    <?php endif; ?>

    <div class="footer">
        <div style="display:flex;justify-content:space-between;">
            <div>
                <p style="font-size:12px;color:#999;">This prescription is valid for 30 days from the date of issue.</p>
            </div>
            <div class="signature-line">
                Dr. <?= htmlspecialchars($p['doctor_first_name'] . ' ' . $p['doctor_last_name']) ?><br>
                <small>License: <?= htmlspecialchars($p['license_number'] ?? '') ?></small>
            </div>
        </div>
    </div>
</body>
</html>
