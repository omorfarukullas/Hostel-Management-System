<?php
/**
 * admin/repair_costs_pdf.php — Generate Repair Costs PDF Report
 * Hostel Management System
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

// Set month/year filter
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year  = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

if ($month < 1 || $month > 12) $month = (int)date('m');
if ($year < 2000 || $year > 2100) $year = (int)date('Y');

$dateObj = DateTime::createFromFormat('!m', $month);
$monthName = $dateObj->format('F');

// Fetch repair costs for this month
$stmt = $conn->prepare("
    SELECT r.*, c.title as complaint_title, c.category, sm.name as supervisor_name
    FROM repair_costs r
    LEFT JOIN complaints c ON r.complaint_id = c.complaint_id
    LEFT JOIN supervisors s ON r.approved_by = s.supervisor_id
    LEFT JOIN users sm ON s.user_id = sm.user_id
    WHERE MONTH(r.repair_date) = ? AND YEAR(r.repair_date) = ?
    ORDER BY r.repair_date DESC
");
$stmt->bind_param('ii', $month, $year);
$stmt->execute();
$costs = $stmt->get_result();

$total_cost = 0;

// Include FPDF
require_once __DIR__ . '/../lib/fpdf/fpdf.php';

class PDF extends FPDF {
    // Page header
    function Header() {
        // Logo
        // $this->Image('logo.png',10,6,30);
        
        $this->SetFont('Arial', 'B', 16);
        $this->SetTextColor(37, 99, 235); // Primary Blue
        $this->Cell(0, 10, 'Hostel Management System', 0, 1, 'C');
        
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(51, 65, 85); // Slate
        $this->Cell(0, 8, 'Monthly Maintenance & Repair Costs', 0, 1, 'C');
        $this->Ln(5);
    }

    // Page footer
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(148, 163, 184); // Slate 400
        $this->Cell(0, 10, 'Generated on ' . date('Y-m-d H:i:s') . ' | Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();

// Filter Info
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetTextColor(100, 116, 139);
$pdf->Cell(30, 8, 'Report Period: ', 0, 0);
$pdf->SetFont('Arial', '', 11);
$pdf->SetTextColor(15, 23, 42);
$pdf->Cell(100, 8, $monthName . ' ' . $year, 0, 1);
$pdf->Ln(5);

// Table Header
$pdf->SetFillColor(241, 245, 249); // slate-100
$pdf->SetTextColor(30, 41, 59); // slate-800
$pdf->SetDrawColor(203, 213, 225); // slate-300
$pdf->SetFont('Arial', 'B', 10);

$pdf->Cell(25, 10, 'Date', 1, 0, 'C', true);
$pdf->Cell(55, 10, 'Complaint / Description', 1, 0, 'L', true);
$pdf->Cell(30, 10, 'Category', 1, 0, 'C', true);
$pdf->Cell(45, 10, 'Vendor', 1, 0, 'L', true);
$pdf->Cell(35, 10, 'Amount ($)', 1, 1, 'R', true);

// Table Body
$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(51, 65, 85);

if ($costs && $costs->num_rows > 0) {
    while ($c = $costs->fetch_assoc()) {
        $total_cost += $c['amount'];
        $title = $c['complaint_title'] ?: 'General Maintenance';
        $title = mb_strimwidth($title, 0, 30, '...');
        $vendor = mb_strimwidth($c['vendor_name'] ?? 'N/A', 0, 25, '...');
        $cat = ucfirst($c['category'] ?? 'N/A');
        
        $pdf->Cell(25, 10, date('d M Y', strtotime($c['repair_date'])), 1, 0, 'C');
        $pdf->Cell(55, 10, $title, 1, 0, 'L');
        $pdf->Cell(30, 10, $cat, 1, 0, 'C');
        $pdf->Cell(45, 10, $vendor, 1, 0, 'L');
        $pdf->Cell(35, 10, number_format($c['amount'], 2), 1, 1, 'R');
    }
    
    // Total Row
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetFillColor(248, 250, 252);
    $pdf->Cell(155, 10, 'Total Expenses for ' . $monthName . ' ' . $year . ':', 1, 0, 'R', true);
    $pdf->SetTextColor(37, 99, 235);
    $pdf->Cell(35, 10, '$' . number_format($total_cost, 2), 1, 1, 'R', true);

} else {
    $pdf->Cell(190, 15, 'No repair costs recorded for this month.', 1, 1, 'C');
}

// Output
$pdf->Output('I', 'Repair_Costs_' . $monthName . '_' . $year . '.pdf');
