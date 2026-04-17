<?php
require_once 'db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'PARENT') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$child_id = intval($_GET['child_id'] ?? 0);
$parent_id = intval($_SESSION['user_id']);
$type = $_GET['type'] ?? 'consultations';

// Validate type to prevent unexpected behavior
$allowed_types = ['consultations', 'prescriptions', 'vaccinations'];
if (!in_array($type, $allowed_types, true)) {
    echo json_encode(['error' => 'Invalid type']);
    exit;
}

// Verify child belongs to parent (prepared statement)
$check_stmt = mysqli_prepare($conn, "SELECT id FROM patients WHERE id = ? AND parent_id = ?");
mysqli_stmt_bind_param($check_stmt, "ii", $child_id, $parent_id);
mysqli_stmt_execute($check_stmt);
$check_result = mysqli_stmt_get_result($check_stmt);
if (mysqli_num_rows($check_result) == 0) {
    echo json_encode(['error' => 'Child not found']);
    exit;
}
mysqli_stmt_close($check_stmt);

$data = [];

switch ($type) {
    case 'consultations':
        $query = "SELECT mr.*, u.first_name as doctor_first_name, u.last_name as doctor_last_name
                  FROM medical_records mr
                  JOIN users u ON mr.doctor_id = u.id
                  WHERE mr.patient_id = ?
                  ORDER BY mr.record_date DESC";
        break;

    case 'prescriptions':
        $query = "SELECT p.*, u.first_name as doctor_first_name, u.last_name as doctor_last_name
                  FROM prescriptions p
                  JOIN users u ON p.doctor_id = u.id
                  WHERE p.patient_id = ?
                  ORDER BY p.prescription_date DESC";
        break;

    case 'vaccinations':
        $query = "SELECT vr.*, u.first_name as doctor_first_name, u.last_name as doctor_last_name
                  FROM vaccination_records vr
                  LEFT JOIN users u ON vr.administered_by = u.id
                  WHERE vr.patient_id = ?
                  ORDER BY vr.administration_date DESC";
        break;
}

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $child_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $data[] = $row;
}
mysqli_stmt_close($stmt);

echo json_encode($data);
?>
